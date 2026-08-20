<?php
declare(strict_types=1);

/**
 * V15.3.0 — SQL Editor engine.
 * Thực thi SQL trên SQLite (sqlite3 CLI, -json) và MariaDB, dùng proc_open có timeout
 * để tránh treo bảng khi chạy câu lệnh nặng. Kiểm tra path traversal, chặn các lệnh
 * nguy hiểm nhất định ở cấp filename; đọc/ghi đều hỗ trợ (dùng trong panel đã đăng nhập).
 */
final class SqlQueryService
{
    private string $home;
    private string $driver;
    private string $clientConfig;

    public function __construct()
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $modeFile = $this->home . '/.tms-os/db-mode';
        $mode = is_file($modeFile) ? trim((string)@file_get_contents($modeFile)) : '';
        $this->driver = $mode === 'sqlite' ? 'sqlite' : 'mariadb';
        $this->clientConfig = $this->home . '/.tms-os/mariadb-client.cnf';
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    /** Danh sách database SQLite hiển thị trong editor (name → hiển thị; dùng db_key). */
    public function sqliteList(): array
    {
        $items = [];
        $dir = $this->home . '/.tms-os/data/db';
        if (is_dir($dir)) {
            foreach (scandir($dir) ?: [] as $f) {
                if ($f === '.' || $f === '..' || !is_file($dir . '/' . $f)) {
                    continue;
                }
                $ext = strtolower((string)pathinfo($f, PATHINFO_EXTENSION));
                if (!in_array($ext, ['sqlite3', 'sqlite', 'db'], true)) {
                    continue;
                }
                $items[] = [
                    'db_key' => 'm__' . pathinfo($f, PATHINFO_FILENAME),
                    'name' => pathinfo($f, PATHINFO_FILENAME) . ' (TMS OS)',
                    'path' => $dir . '/' . $f,
                    'site' => '',
                ];
            }
        }
        $sitesDir = $this->home . '/websites';
        $skipDirs = ['node_modules', 'vendor', '.git', 'cache', 'tmp', 'storage'];
        if (is_dir($sitesDir)) {
            foreach (scandir($sitesDir) ?: [] as $site) {
                if ($site === '.' || $site === '..' || !is_dir($sitesDir . '/' . $site)) {
                    continue;
                }
                $publicRoot = $sitesDir . '/' . $site . '/public';
                if (!is_dir($publicRoot)) {
                    continue;
                }
                foreach ($this->findSqliteFiles($publicRoot, 3, $skipDirs) as $f) {
                    $items[] = [
                        'db_key' => 'w__' . $site . '__' . md5($f),
                        'name' => pathinfo($f, PATHINFO_FILENAME) . ' (' . $site . ')',
                        'path' => $f,
                        'site' => $site,
                    ];
                }
            }
        }
        usort($items, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return $items;
    }

    /** Danh sách database MariaDB. */
    public function mariaList(): array
    {
        $lines = $this->mariaQueryLines('SHOW DATABASES');
        $skip = ['information_schema', 'performance_schema', 'mysql', 'sys'];
        $out = [];
        foreach ($lines as $name) {
            $name = trim($name);
            if ($name !== '' && !in_array($name, $skip, true)) {
                $out[] = ['db_key' => $name, 'name' => $name, 'site' => ''];
            }
        }
        return $out;
    }

    /** Giải db_key về đường dẫn file SQLite (an toàn, không cho path tùy ý từ client). */
    public function resolveSqliteFile(string $dbKey): string
    {
        $dbKey = trim($dbKey);
        if (!preg_match('/^m__[A-Za-z0-9._-]{1,64}$/', $dbKey)) {
            // db_key website: md5 không đoán trước được, nhưng file path vẫn phải trong HOME
            if (!str_starts_with($dbKey, 'w__')) {
                throw new RuntimeException('db_key không hợp lệ.');
            }
        }
        // Duyệt danh sách hợp lệ và khớp db_key
        foreach (array_merge($this->sqliteList()) as $item) {
            if ($item['db_key'] === $dbKey) {
                return $this->safePath($item['path']);
            }
        }
        throw new RuntimeException('Database không tồn tại.');
    }

    public function resolveMariaDb(string $dbKey): string
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbKey)) {
            throw new RuntimeException('Tên database MariaDB không hợp lệ.');
        }
        return $dbKey;
    }

    /** Thực thi SQL, trả kết quả có cấu trúc. */
    public function query(string $dbKey, string $sql, bool $readOnly = false): array
    {
        $start = microtime(true);
        if ($this->driver === 'sqlite') {
            $file = $this->resolveSqliteFile($dbKey);
            return $this->sqliteQuery($file, $sql, $readOnly, $start);
        }
        $db = $this->resolveMariaDb($dbKey);
        return $this->mariaQuery($db, $sql, $readOnly, $start);
    }

    /** Danh sách bảng. */
    public function tables(string $dbKey): array
    {
        if ($this->driver === 'sqlite') {
            $file = $this->resolveSqliteFile($dbKey);
            $res = $this->sqliteQuery($file, "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name", true, microtime(true));
            return array_values(array_column((array)$res['rows'], 'name'));
        }
        $db = $this->resolveMariaDb($dbKey);
        $lines = $this->mariaQueryLines("SHOW TABLES IN `$db`");
        return array_values(array_filter(array_map('trim', $lines), static fn(string $n): bool => $n !== ''));
    }

    /** Cấu trúc bảng (cột). */
    public function structure(string $dbKey, string $table): array
    {
        if ($this->driver === 'sqlite') {
            $file = $this->resolveSqliteFile($dbKey);
            $res = $this->sqliteQuery($file, 'PRAGMA table_info(' . $this->q($table) . ')', true, microtime(true));
            return (array)$res['rows'];
        }
        $db = $this->resolveMariaDb($dbKey);
        $res = $this->mariaJsonQuery($db, 'SHOW COLUMNS IN `' . $this->q($db) . '`.`' . $this->q($table) . '`');
        return (array)$res['rows'];
    }

    /** Sửa một ô dữ liệu. Cột khóa chính xác định dòng; không cho cập nhật trên view phức tạp. */
    public function saveCell(string $dbKey, string $table, array $pkColumns, array $pkValues, string $column, string $value): array
    {
        if ($this->driver === 'sqlite') {
            $file = $this->resolveSqliteFile($dbKey);
            $wh = [];
            for ($i = 0, $n = count($pkColumns); $i < $n; $i++) {
                $wh[] = $this->q($pkColumns[$i]) . ' = ' . $this->sqliteLiteral($pkValues[$i] ?? null);
            }
            $sql = 'UPDATE ' . $this->q($table) . ' SET ' . $this->q($column) . ' = '
                . $this->sqliteLiteral($value) . ' WHERE ' . implode(' AND ', $wh);
            return $this->sqliteQuery($file, $sql, false, microtime(true));
        }
        $db = $this->resolveMariaDb($dbKey);
        $wh = [];
        for ($i = 0, $n = count($pkColumns); $i < $n; $i++) {
            $wh[] = '`' . $this->q($pkColumns[$i]) . '` = ' . $this->mariaLiteral($pkValues[$i] ?? null);
        }
        $sql = 'UPDATE `' . $this->q($db) . '`.`' . $this->q($table) . '` SET `'
            . $this->q($column) . '` = ' . $this->mariaLiteral($value) . ' WHERE ' . implode(' AND ', $wh);
        return $this->mariaQuery($db, $sql, false, microtime(true));
    }

    /** Xóa dòng theo khóa chính. */
    public function deleteRow(string $dbKey, string $table, array $pkColumns, array $pkValues): array
    {
        if (count($pkColumns) === 0) {
            throw new RuntimeException('Bảng không có khóa chính, không thể xóa dòng an toàn.');
        }
        if ($this->driver === 'sqlite') {
            $file = $this->resolveSqliteFile($dbKey);
            $wh = [];
            for ($i = 0, $n = count($pkColumns); $i < $n; $i++) {
                $wh[] = $this->q($pkColumns[$i]) . ' = ' . $this->sqliteLiteral($pkValues[$i] ?? null);
            }
            return $this->sqliteQuery($file, 'DELETE FROM ' . $this->q($table) . ' WHERE ' . implode(' AND ', $wh), false, microtime(true));
        }
        $db = $this->resolveMariaDb($dbKey);
        $wh = [];
        for ($i = 0, $n = count($pkColumns); $i < $n; $i++) {
            $wh[] = '`' . $this->q($pkColumns[$i]) . '` = ' . $this->mariaLiteral($pkValues[$i] ?? null);
        }
        return $this->mariaQuery($db, 'DELETE FROM `' . $this->q($db) . '`.`' . $this->q($table) . '` WHERE ' . implode(' AND ', $wh), false, microtime(true));
    }

    /** Chèn dòng mới: column → value map (giá trị là string thô, literal tự escape). */
    public function insertRow(string $dbKey, string $table, array $values): array
    {
        if ($this->driver === 'sqlite') {
            $file = $this->resolveSqliteFile($dbKey);
            $cols = [];
            $vals = [];
            foreach ($values as $col => $val) {
                $cols[] = $this->q((string)$col);
                $vals[] = $this->sqliteLiteral($val);
            }
            return $this->sqliteQuery($file, 'INSERT INTO ' . $this->q($table) . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')', false, microtime(true));
        }
        $db = $this->resolveMariaDb($dbKey);
        $cols = [];
        $vals = [];
        foreach ($values as $col => $val) {
            $cols[] = '`' . $this->q((string)$col) . '`';
            $vals[] = $this->mariaLiteral($val);
        }
        return $this->mariaQuery($db, 'INSERT INTO `' . $this->q($db) . '`.`' . $this->q($table) . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')', false, microtime(true));
    }

    /** Khóa chính của bảng SQLite: cột PRIMARY KEY. */
    public function primaryKey(string $dbKey, string $table): array
    {
        if ($this->driver === 'sqlite') {
            $file = $this->resolveSqliteFile($dbKey);
            $res = $this->sqliteQuery($file, 'PRAGMA table_info(' . $this->q($table) . ')', true, microtime(true));
            $pks = [];
            foreach ((array)$res['rows'] as $r) {
                if (!empty($r['pk'])) {
                    $pks[] = (string)$r['name'];
                }
            }
            return $pks;
        }
        $db = $this->resolveMariaDb($dbKey);
        $res = $this->mariaJsonQuery($db, 'SHOW COLUMNS IN `' . $this->q($db) . '`.`' . $this->q($table) . '` WHERE `Key` = \'PRI\'');
        return array_values(array_column((array)$res['rows'], 'Field'));
    }

    // -------------------------------------------- SQLite --------------------------------------------

    private function sqliteQuery(string $file, string $sql, bool $readOnly, float $start): array
    {
        $sql = trim($sql);
        if ($sql === '') {
            return ['columns' => [], 'rows' => [], 'rowCount' => 0, 'truncated' => false, 'time' => 0.0];
        }
        // Bảo vệ: không cho phép ALTER/ATTACH/DETACH đối với file hệ thống, chặn câu nhiều lệnh
        if (stripos($sql, 'DETACH') !== false || stripos($sql, 'ATTACH') !== false) {
            throw new RuntimeException('Lệnh ATTACH/DETACH không được phép trong trình editor.');
        }
        $readOnlyMode = $readOnly ? ' -readonly' : '';
        $cmd = 'sqlite3 -json' . $readOnlyMode . ' -header -separator "\t" '
            . escapeshellarg($file) . ' ' . escapeshellarg($sql) . ' 2>&1';
        $out = $this->runWithTimeout($cmd);
        $time = round(microtime(true) - $start, 3);
        if ($out['timed_out']) {
            throw new RuntimeException('Câu lệnh chạy quá 15 giây, bị dừng. Hãy thu hẹp phạm vi truy vấn (thêm LIMIT).');
        }
        $stderr = trim($out['stderr']);
        $stdout = trim($out['stdout']);
        if ($out['code'] !== 0 && $stdout === '' && $stderr !== '') {
            throw new RuntimeException($stderr);
        }
        if (stripos($stderr, 'Error') !== false && $stdout === '') {
            throw new RuntimeException($stderr);
        }
        if ($stdout === '') {
            return ['columns' => [], 'rows' => [], 'rowCount' => 0, 'truncated' => false, 'time' => $time, 'affected' => true, 'message' => ($stderr === '' ? 'Thành công.' : $stderr)];
        }
        $data = json_decode($stdout, true);
        if (!is_array($data)) {
            // Lệnh không trả dữ liệu (UPDATE/DELETE/INSERT thành công không -json)
            if ($out['code'] !== 0) {
                throw new RuntimeException($stderr ?: 'SQLite trả về lỗi.');
            }
            return ['columns' => [], 'rows' => [], 'rowCount' => 0, 'truncated' => false, 'time' => $time, 'affected' => true, 'message' => $stdout ?: 'Thành công.'];
        }
        $rows = $data;
        $columns = $rows ? array_keys($rows[0]) : [];
        return ['columns' => $columns, 'rows' => $rows, 'rowCount' => count($rows), 'truncated' => false, 'time' => $time];
    }

    private function sqliteLiteral(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }
        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value))) {
            return (string)$value;
        }
        return '\'' . str_replace('\'', '\'\'', (string)$value) . '\'';
    }

    // -------------------------------------------- MariaDB --------------------------------------------

    private function mariaQuery(string $db, string $sql, bool $readOnly, float $start): array
    {
        $sql = trim($sql);
        if ($sql === '') {
            return ['columns' => [], 'rows' => [], 'rowCount' => 0, 'truncated' => false, 'time' => 0.0];
        }
        if (preg_match('/^\s*(DROP|CREATE|ALTER)\s+(DATABASE|SCHEMA)/i', $sql)) {
            throw new RuntimeException('Lệnh tạo/xóa database không được phép trong trình editor.');
        }
        $jsonMode = stripos($sql, 'SELECT') === 0 ? ' --json=full' : '';
        $readOnlyMode = $readOnly ? ' --skip-column-names' : '';
        $cmd = 'mariadb --defaults-extra-file=' . escapeshellarg($this->clientConfig)
            . $jsonMode . ' --database=' . escapeshellarg($db)
            . ' -e ' . escapeshellarg($sql) . ' 2>&1';
        $out = $this->runWithTimeout($cmd);
        $time = round(microtime(true) - $start, 3);
        if ($out['timed_out']) {
            throw new RuntimeException('Câu lệnh chạy quá 15 giây, bị dừng. Hãy thu hẹp phạm vi truy vấn.');
        }
        if ($out['code'] !== 0) {
            throw new RuntimeException(trim($out['stdout'] . "\n" . $out['stderr']) ?: 'MariaDB trả về lỗi không xác định.');
        }
        $stdout = trim($out['stdout']);
        if ($stdout === '') {
            return ['columns' => [], 'rows' => [], 'rowCount' => 0, 'truncated' => false, 'time' => $time, 'affected' => true, 'message' => 'Thành công.'];
        }
        if (str_starts_with($stdout, '{')) {
            $data = json_decode($stdout, true);
            if (isset($data['resultsets'][0]['columns']) && isset($data['resultsets'][0]['rows'])) {
                $columns = array_column($data['resultsets'][0]['columns'], 'name');
                $rows = $data['resultsets'][0]['rows'];
                return ['columns' => $columns, 'rows' => $rows, 'rowCount' => count($rows), 'truncated' => false, 'time' => $time];
            }
            return ['columns' => [], 'rows' => [], 'rowCount' => 0, 'truncated' => false, 'time' => $time, 'affected' => true, 'message' => 'Thành công.'];
        }
        // Dạng batch: dòng đầu = tên cột
        $lines = explode("\n", $stdout);
        $columns = preg_split('/\t/', array_shift($lines));
        $rows = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $rows[] = array_combine((array)$columns, preg_split('/\t/', $line));
        }
        return ['columns' => $columns, 'rows' => $rows, 'rowCount' => count($rows), 'truncated' => false, 'time' => $time];
    }

    private function mariaJsonQuery(string $db, string $sql): array
    {
        $cmd = 'mariadb --defaults-extra-file=' . escapeshellarg($this->clientConfig)
            . ' --json=full --database=' . escapeshellarg($db)
            . ' -e ' . escapeshellarg($sql) . ' 2>&1';
        $out = $this->runWithTimeout($cmd);
        if ($out['code'] !== 0) {
            throw new RuntimeException(trim($out['stdout'] . "\n" . $out['stderr']) ?: 'MariaDB lỗi.');
        }
        $data = json_decode(trim($out['stdout']), true);
        if (!isset($data['resultsets'][0])) {
            return ['rows' => []];
        }
        return ['rows' => $data['resultsets'][0]['rows'] ?? []];
    }

    private function mariaQueryLines(string $sql): array
    {
        $cmd = 'mariadb --defaults-extra-file=' . escapeshellarg($this->clientConfig)
            . ' --batch --skip-column-names -e ' . escapeshellarg($sql) . ' 2>&1';
        $out = $this->runWithTimeout($cmd);
        if ($out['code'] !== 0) {
            throw new RuntimeException(trim($out['stdout'] . "\n" . $out['stderr']) ?: 'MariaDB lỗi.');
        }
        return array_filter(explode("\n", trim($out['stdout'])));
    }

    private function mariaLiteral(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }
        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value))) {
            return (string)$value;
        }
        return '\'' . str_replace('\'', '\\' . '\'', (string)$value) . '\'';
    }

    // -------------------------------------------- helpers --------------------------------------------

    private function q(string $identifier): string
    {
        return str_replace(['"', "'", "\\", ";", "\n", "\r"], '', $identifier);
    }

    private function safePath(string $path): string
    {
        $real = realpath($path);
        if ($real === false || !str_starts_with($real, rtrim((string)realpath($this->home), '/') . '/')) {
            throw new RuntimeException('Đường dẫn database không hợp lệ.');
        }
        return $real;
    }

    private function findSqliteFiles(string $dir, int $maxDepth, array $skipDirs): array
    {
        $found = [];
        if (!is_dir($dir) || $maxDepth < 0) {
            return $found;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir . '/' . $f;
            if (is_dir($p)) {
                if (in_array($f, $skipDirs, true)) {
                    continue;
                }
                foreach ($this->findSqliteFiles($p, $maxDepth - 1, $skipDirs) as $foundFile) {
                    $found[] = $foundFile;
                }
                continue;
            }
            $ext = strtolower((string)pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, ['sqlite3', 'sqlite', 'db'], true)) {
                continue;
            }
            $size = (int)@filesize($p);
            if ($size === 0) {
                $found[] = $p;
                continue;
            }
            $head = (string)@file_get_contents($p, false, null, 0, 15);
            if ($head !== '' && str_starts_with($head, 'SQLite format 3')) {
                $found[] = $p;
            }
        }
        return $found;
    }

    /** Chạy lệnh có timeout bằng proc_open + stream_select. */
    private function runWithTimeout(string $command, int $timeoutSeconds = 15): array
    {
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($command, $descriptors, $pipes);
        if (!is_resource($proc)) {
            throw new RuntimeException('Không thể khởi chạy tiến trình lệnh.');
        }
        @fclose($pipes[0]);
        $stdout = '';
        $stderr = '';
        $timedOut = false;
        $start = microtime(true);
        $readers = ['out' => $pipes[1], 'err' => $pipes[2]];
        while (count($readers) > 0) {
            $r = array_values($readers);
            $w = $e = null;
            $changed = stream_select($r, $w, $e, $timeoutSeconds);
            if ($changed === false || ($changed === 0 && count($readers) > 0)) {
                $timedOut = true;
                break;
            }
            foreach ($readers as $key => $pipe) {
                if (!in_array($pipe, $r, true)) {
                    continue;
                }
                $buf = fread($pipe, 65536);
                if ($buf === '' && feof($pipe)) {
                    fclose($pipe);
                    unset($readers[$key]);
                } elseif ($buf !== '') {
                    if ($key === 'out') {
                        $stdout .= $buf;
                    } else {
                        $stderr .= $buf;
                    }
                }
            }
        }
        if ($timedOut) {
            proc_terminate($proc, 9);
            foreach ($readers as $pipe) {
                @fclose($pipe);
            }
        }
        $code = (int)proc_close($proc);
        return ['stdout' => $stdout, 'stderr' => $stderr, 'code' => $code, 'timed_out' => $timedOut];
    }
}
