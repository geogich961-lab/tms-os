<?php
declare(strict_types=1);

final class DatabaseService
{
    private string $home;
    private string $driver;
    private string $clientConfig;
    private string $sqliteDb;

    public function __construct()
    {
        $this->home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->clientConfig = $this->home . '/.tms-os/mariadb-client.cnf';
        $this->sqliteDb = $this->home . '/.tms-os/data/tms.db';

        // V14.0.3: dual-mode database — sqlite (khuyến nghị cho mini VPS) hoặc mariadb.
        $modeFile = $this->home . '/.tms-os/db-mode';
        $mode = is_file($modeFile) ? trim((string)@file_get_contents($modeFile)) : '';
        if ($mode === 'sqlite') {
            $this->driver = 'sqlite';
            return;
        }
        $this->driver = 'mariadb';

        if (!is_file($this->clientConfig)) {
            throw new RuntimeException(
                'Chưa có cấu hình MariaDB. Hãy chạy lại bộ cài hoặc scripts/repair.sh.'
            );
        }
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function all(): array
    {
        if ($this->driver === 'sqlite') {
            return $this->allSqlite();
        }
        $output = $this->queryLines('SHOW DATABASES');
        $skip = ['information_schema', 'performance_schema', 'mysql', 'sys'];

        return array_values(array_filter(
            array_map('trim', $output),
            static fn(string $name): bool => $name !== '' && !in_array($name, $skip, true)
        ));
    }

    /**
     * V15.2.1: quét SQLite quản lý (.tms-os/data/db) + file SQLite trong website
     * (Typecho, ứng dụng người dùng tự tạo).
     */
    public function allSqlite(): array
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
                    'name' => pathinfo($f, PATHINFO_FILENAME),
                    'source' => 'managed',
                    'path' => $dir . '/' . $f,
                    'site' => '',
                    'size' => is_file($dir . '/' . $f) ? (int)@filesize($dir . '/' . $f) : 0,
                    'db_key' => 'm__' . pathinfo($f, PATHINFO_FILENAME),
                ];
            }
        }
        // Quét website: ~/websites/<site>/... — hợp lệ: usr/*.db, *.sqlite3, *.sqlite (không đệ quy quá 3 cấp)
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
                $files = $this->findSqliteFiles($publicRoot, 3, $skipDirs);
                foreach ($files as $f) {
                    $items[] = [
                        'name' => pathinfo($f, PATHINFO_FILENAME),
                        'source' => 'website',
                        'path' => $f,
                        'site' => $site,
                        'size' => (int)@filesize($f),
                        'db_key' => 'w__' . $site . '__' . md5($f),
                    ];
                }
            }
        }
        usort($items, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return $items;
    }

    /**
     * Tìm file SQLite trong thư mục, giới hạn độ sâu — tránh thư mục nặng.
     */
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
            // Chỉ nhận file hợp lệ: file SQLite thật có header "SQLite format 3" hoặc mới tạo (0 byte)
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

    /**
     * Mang một file SQLite của website về thư mục quản lý của TMS OS.
     */
    public function moveToManaged(string $sourcePath): array
    {
        if ($this->driver !== 'sqlite') {
            throw new RuntimeException('Chức năng này chỉ dùng trong chế độ SQLite.');
        }
        $sourcePath = $this->safePath((string)$sourcePath);
        if (!is_file($sourcePath)) {
            throw new RuntimeException('File database không tồn tại.');
        }
        $dir = $this->home . '/.tms-os/data/db';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Không thể tạo thư mục database.');
        }
        $base = pathinfo($sourcePath, PATHINFO_BASENAME);
        $dst = $dir . '/' . $base;
        if (is_file($dst)) {
            throw new RuntimeException("File '{$base}' đã tồn tại trong thư mục quản lý.");
        }
        if (!@copy($sourcePath, $dst)) {
            throw new RuntimeException('Không thể sao chép file database.');
        }
        @chmod($dst, 0600);
        return ['name' => pathinfo($base, PATHINFO_FILENAME), 'path' => $dst];
    }

    /**
     * Chặn path traversal — file SQLite phải nằm trong HOME của người dùng.
     */
    private function safePath(string $path): string
    {
        $real = realpath($path);
        if ($real === false || !str_starts_with($real, rtrim((string)realpath($this->home), '/') . '/')) {
            throw new RuntimeException('Đường dẫn database không hợp lệ.');
        }
        return $real;
    }

    public function create(string $name): void
    {
        if ($this->driver === 'sqlite') {
            $name = $this->name($name);
            // SQLite mode: mỗi database là một file trong thư mục data/db/.
            $dir = $this->home . '/.tms-os/data/db';
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new RuntimeException('Không thể tạo thư mục database.');
            }
            $file = $dir . '/' . $name . '.sqlite3';
            if (is_file($file)) {
                return; // đã tồn tại
            }
            $this->sqliteExec($file, 'SELECT 1');
            return;
        }
        $name = $this->name($name);
        $this->execute("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function drop(string $name): void
    {
        if ($this->driver === 'sqlite') {
            $name = $this->name($name);
            $file = $this->home . '/.tms-os/data/db/' . $name . '.sqlite3';
            if (is_file($file)) {
                @unlink($file);
            }
            return;
        }
        $name = $this->name($name);
        $this->execute("DROP DATABASE IF EXISTS `{$name}`");
    }

    public function createUserForDatabase(string $database, string $user, string $password): void
    {
        if ($this->driver === 'sqlite') {
            // SQLite không có khái niệm user riêng — lưu thông tin đăng nhập
            // vào file cấu hình để ghi nhớ (không bảo mật thực, chỉ để ghi nhận).
            if (strlen($password) < 8) {
                throw new RuntimeException('Mật khẩu database phải có ít nhất 8 ký tự.');
            }
            $creds = $this->home . '/.tms-os/data/sqlite-users.ini';
            $data = is_file($creds) ? (array)@parse_ini_file($creds) : [];
            $data[$this->name($user)] = [
                'database' => $this->name($database),
                'password' => (string)password_hash($password, PASSWORD_DEFAULT),
            ];
            $lines = [];
            foreach ($data as $u => $v) {
                $lines[] = "[{$u}]";
                $lines[] = "database = {$v['database']}";
                $lines[] = "password = {$v['password']}";
            }
            file_put_contents($creds, implode("\n", $lines));
            @chmod($creds, 0600);
            return;
        }
        $database = $this->name($database);
        $user = $this->name($user);

        if (strlen($password) < 8) {
            throw new RuntimeException('Mật khẩu database phải có ít nhất 8 ký tự.');
        }

        $quotedPassword = $this->quote($password);
        $escapedUser = str_replace('`', '``', $user);

        $sql = "CREATE USER IF NOT EXISTS `{$escapedUser}`@'localhost' IDENTIFIED BY {$quotedPassword};"
             . "ALTER USER `{$escapedUser}`@'localhost' IDENTIFIED BY {$quotedPassword};"
             . "GRANT ALL PRIVILEGES ON `{$database}`.* TO `{$escapedUser}`@'localhost';"
             . "FLUSH PRIVILEGES;";

        $this->execute($sql);
    }

    public function dropUser(string $user): void
    {
        $user = $this->name($user);
        $escapedUser = str_replace('`', '``', $user);
        $this->execute("DROP USER IF EXISTS `{$escapedUser}`@'localhost'; FLUSH PRIVILEGES;");
    }

    /**
     * V15.2.1: xuất SQLite theo đường dẫn file bất kỳ (database của website).
     */
    public function exportByPath(string $sourcePath): string
    {
        $src = $this->safePath((string)$sourcePath);
        if (!is_file($src)) {
            throw new RuntimeException('File database không tồn tại.');
        }
        $dir = $this->home . '/backups/database';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Không thể tạo thư mục sao lưu database.');
        }
        $base = pathinfo($src, PATHINFO_FILENAME);
        $file = $dir . '/' . $base . '_' . date('Ymd_His') . '.sql';
        $command = 'sqlite3 ' . escapeshellarg($src) . ' ".dump" > ' . escapeshellarg($file) . ' 2>&1';
        exec($command, $output, $code);
        if ($code !== 0) {
            @unlink($file);
            throw new RuntimeException("Xuất database SQLite thất bại:\n" . implode("\n", $output));
        }
        @chmod($file, 0600);
        return $file;
    }

    public function export(string $name): string
    {
        $name = $this->name($name);
        $dir = $this->home . '/backups/database';

        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Không thể tạo thư mục sao lưu database.');
        }

        $file = $dir . '/' . $name . '_' . date('Ymd_His') . '.sql';
        if ($this->driver === 'sqlite') {
            $src = $this->home . '/.tms-os/data/db/' . $name . '.sqlite3';
            if (!is_file($src)) {
                throw new RuntimeException("Database SQLite không tồn tại: {$name}");
            }
            $command = 'sqlite3 ' . escapeshellarg($src) . ' ".dump" > ' . escapeshellarg($file) . ' 2>&1';
            exec($command, $output, $code);
            if ($code !== 0) {
                @unlink($file);
                throw new RuntimeException("Xuất database SQLite thất bại:\n" . implode("\n", $output));
            }
        } else {
            $command = 'mariadb-dump '
                . '--defaults-extra-file=' . escapeshellarg($this->clientConfig) . ' '
                . '--single-transaction --quick --routines --triggers '
                . escapeshellarg($name)
                . ' > ' . escapeshellarg($file) . ' 2>&1';

            exec($command, $output, $code);

            if ($code !== 0) {
                @unlink($file);
                throw new RuntimeException("Xuất database thất bại:\n" . implode("\n", $output));
            }
        }

        @chmod($file, 0600);
        return $file;
    }

    public function import(string $name, array $file): void
    {
        $name = $this->name($name);

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Chưa chọn file SQL hoặc quá trình tải lên bị lỗi.');
        }

        $tmp = (string)($file['tmp_name'] ?? '');

        if ($tmp === '' || !is_file($tmp)) {
            throw new RuntimeException('Không tìm thấy file SQL tạm.');
        }

        if ($this->driver === 'sqlite') {
            $dir = $this->home . '/.tms-os/data/db';
            if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new RuntimeException('Không thể tạo thư mục database.');
            }
            $dst = $dir . '/' . $name . '.sqlite3';
            $command = 'sqlite3 ' . escapeshellarg($dst) . ' ".read ' . escapeshellarg($tmp) . '" 2>&1';
            exec($command, $output, $code);
            if ($code !== 0) {
                @unlink($dst);
                throw new RuntimeException("Nhập database SQLite thất bại:\n" . implode("\n", $output));
            }
            return;
        }
        $command = 'mariadb '
            . '--defaults-extra-file=' . escapeshellarg($this->clientConfig) . ' '
            . escapeshellarg($name)
            . ' < ' . escapeshellarg($tmp) . ' 2>&1';

        exec($command, $output, $code);

        if ($code !== 0) {
            throw new RuntimeException("Nhập database thất bại:\n" . implode("\n", $output));
        }
    }

    public function testConnection(): bool
    {
        try {
            $this->queryLines('SELECT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function execute(string $sql): void
    {
        if ($this->driver === 'sqlite') {
            $this->sqliteExec($this->sqliteDb, $sql);
            return;
        }
        $command = 'mariadb '
            . '--defaults-extra-file=' . escapeshellarg($this->clientConfig) . ' '
            . '--batch --skip-column-names '
            . '-e ' . escapeshellarg($sql) . ' 2>&1';

        exec($command, $output, $code);

        if ($code !== 0) {
            throw new RuntimeException(
                trim(implode("\n", $output)) ?: 'MariaDB trả về lỗi không xác định.'
            );
        }
    }

    private function sqliteExec(string $dbFile, string $sql): void
    {
        $dir = dirname($dbFile);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Không thể tạo thư mục chứa database SQLite.');
        }
        if (!is_file($dbFile)) {
            touch($dbFile);
            chmod($dbFile, 0600);
        }
        $command = 'sqlite3 ' . escapeshellarg($dbFile) . ' ' . escapeshellarg($sql) . ' 2>&1';
        exec($command, $output, $code);
        if ($code !== 0) {
            throw new RuntimeException(
                trim(implode("\n", $output)) ?: 'SQLite trả về lỗi không xác định.'
            );
        }
    }

    private function queryLines(string $sql): array
    {
        if ($this->driver === 'sqlite') {
            $dbFile = $this->sqliteDb;
            if (!is_file($dbFile)) {
                if (!touch($dbFile) || !chmod($dbFile, 0600)) {
                    throw new RuntimeException('Không thể tạo file database SQLite.');
                }
            }
            $command = 'sqlite3 ' . escapeshellarg($dbFile) . ' ' . escapeshellarg($sql) . ' 2>&1';
            exec($command, $output, $code);
            if ($code !== 0) {
                throw new RuntimeException(
                    trim(implode("\n", $output)) ?: 'Không truy cập được database SQLite.'
                );
            }
            return $output;
        }
        $command = 'mariadb '
            . '--defaults-extra-file=' . escapeshellarg($this->clientConfig) . ' '
            . '--batch --skip-column-names '
            . '-e ' . escapeshellarg($sql) . ' 2>&1';

        exec($command, $output, $code);

        if ($code !== 0) {
            throw new RuntimeException(
                trim(implode("\n", $output)) ?: 'Không kết nối được MariaDB.'
            );
        }

        return $output;
    }

    private function name(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,48}$/', $name)) {
            throw new RuntimeException('Tên database hoặc tài khoản database không hợp lệ.');
        }

        return $name;
    }

    private function quote(string $value): string
    {
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
    }
}
