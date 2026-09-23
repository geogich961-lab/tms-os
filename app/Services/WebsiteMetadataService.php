<?php
declare(strict_types=1);

final class WebsiteMetadataService
{
    private string $dir;

    public function __construct()
    {
        $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $this->dir = $home . '/.tms-os/sites';
        @mkdir($this->dir, 0700, true);
    }

    public function get(string $name): array
    {
        $path = $this->path($name);
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    public function ensure(string $name, int $port, string $root, string $type = 'php'): array
    {
        $current = $this->get($name);
        if ($current !== []) {
            $changed = false;
            foreach (['port' => $port, 'root' => $root] as $key => $value) {
                if (($current[$key] ?? null) !== $value) {
                    $current[$key] = $value;
                    $changed = true;
                }
            }
            if ($changed) {
                $current['updated_at'] = date('c');
                $this->write($name, $current);
            }
            return $current;
        }

        $metadata = [
            'schema' => 1,
            'name' => $name,
            'type' => $type,
            'port' => $port,
            'root' => $root,
            'health_path' => '/',
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
        $this->write($name, $metadata);
        return $metadata;
    }

    public function update(string $name, array $changes): array
    {
        $metadata = $this->get($name);
        if ($metadata === []) {
            throw new RuntimeException('Metadata website chưa tồn tại.');
        }
        foreach ($changes as $key => $value) {
            if (in_array((string)$key, ['name', 'schema', 'created_at'], true)) {
                continue;
            }
            $metadata[(string)$key] = $value;
        }
        $metadata['updated_at'] = date('c');
        $this->write($name, $metadata);
        return $metadata;
    }

    public function delete(string $name): void
    {
        $path = $this->path($name);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function write(string $name, array $data): void
    {
        $path = $this->path($name);
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
            @unlink($tmp);
            throw new RuntimeException('Không thể ghi metadata website.');
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Không thể kích hoạt metadata website.');
        }
    }

    private function path(string $name): string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{2,40}$/', $name)) {
            throw new RuntimeException('Tên website không hợp lệ.');
        }
        return $this->dir . '/' . $name . '.json';
    }
}
