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
            $dir = $this->home . '/.tms-os/data/db';
            if (!is_dir($dir)) {
                return [];
            }
            return array_values(array_filter(
                array_map(static fn(string $f): string => pathinfo($f, PATHINFO_FILENAME), scandir($dir)),
                static fn(string $name): bool => $name !== '' && $name !== '.' && $name !== '..'
            ));
        }
        $output = $this->queryLines('SHOW DATABASES');
        $skip = ['information_schema', 'performance_schema', 'mysql', 'sys'];

        return array_values(array_filter(
            array_map('trim', $output),
            static fn(string $name): bool => $name !== '' && !in_array($name, $skip, true)
        ));
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
