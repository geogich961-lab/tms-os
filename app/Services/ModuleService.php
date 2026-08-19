<?php
declare(strict_types=1);

final class ModuleService
{
    private string $basePath;
    private string $modulePath;
    private string $stateFile;

    public function __construct()
    {
        $this->basePath = dirname(__DIR__, 2);
        $this->modulePath = $this->basePath . '/app/Modules';
        $home = getenv('HOME') ?: '/data/data/com.termux/files/home';
        $stateDir = $home . '/.tms-os';
        @mkdir($stateDir, 0700, true);
        $this->stateFile = $stateDir . '/modules-v11.json';
    }

    public function catalog(): array
    {
        $state = $this->readState();
        $modules = [];
        foreach (glob($this->modulePath . '/*/module.json') ?: [] as $manifestFile) {
            $manifest = json_decode((string)file_get_contents($manifestFile), true);
            if (!is_array($manifest)) continue;
            $validation = $this->validateManifest($manifest);
            $id = (string)($manifest['id'] ?? basename(dirname($manifestFile)));
            $core = (bool)($manifest['core'] ?? false);
            $enabled = $core || !array_key_exists($id, $state['enabled']) || (bool)$state['enabled'][$id];
            $dependencies = array_values(array_filter((array)($manifest['dependencies'] ?? []), 'is_string'));
            $missing = [];
            foreach ($dependencies as $dependency) {
                if (!$this->manifestExists($dependency)) $missing[] = $dependency;
            }
            $modules[] = array_merge($manifest, [
                'id' => $id,
                'core' => $core,
                'enabled' => $enabled,
                'manifest_file' => $manifestFile,
                'valid' => $validation['ok'],
                'errors' => $validation['errors'],
                'missing_dependencies' => $missing,
                'health' => !$validation['ok'] ? 'error' : (!empty($missing) ? 'warning' : ($enabled ? 'healthy' : 'disabled')),
                'updated_at' => $state['updated_at'][$id] ?? null,
            ]);
        }
        usort($modules, static function(array $a, array $b): int {
            if (($a['core'] ?? false) !== ($b['core'] ?? false)) return ($a['core'] ?? false) ? -1 : 1;
            return strcasecmp((string)($a['name'] ?? $a['id']), (string)($b['name'] ?? $b['id']));
        });
        return $modules;
    }

    public function summary(): array
    {
        $items = $this->catalog();
        $summary = ['total'=>count($items),'enabled'=>0,'core'=>0,'healthy'=>0,'warning'=>0,'error'=>0];
        foreach ($items as $item) {
            if (!empty($item['enabled'])) $summary['enabled']++;
            if (!empty($item['core'])) $summary['core']++;
            $health = (string)($item['health'] ?? 'error');
            if (isset($summary[$health])) $summary[$health]++;
        }
        return $summary;
    }

    public function setEnabled(string $id, bool $enabled): array
    {
        $module = $this->find($id);
        if (!$module) throw new RuntimeException('Không tìm thấy module.');
        if (!empty($module['core']) && !$enabled) throw new RuntimeException('Module lõi được bảo vệ và không thể tắt.');
        if (!$module['valid']) throw new RuntimeException('Manifest module không hợp lệ.');
        if ($enabled && !empty($module['missing_dependencies'])) {
            throw new RuntimeException('Thiếu module phụ thuộc: ' . implode(', ', $module['missing_dependencies']));
        }
        $state = $this->readState();
        $state['enabled'][$id] = $enabled;
        $state['updated_at'][$id] = date(DATE_ATOM);
        $this->writeState($state);
        return ['ok'=>true,'message'=>($enabled?'Đã bật ':'Đã tắt ') . ($module['name'] ?? $id) . '.'];
    }

    public function repairState(): array
    {
        $known = [];
        foreach ($this->catalog() as $module) $known[$module['id']] = true;
        $state = $this->readState();
        $state['enabled'] = array_intersect_key((array)$state['enabled'], $known);
        $state['updated_at'] = array_intersect_key((array)$state['updated_at'], $known);
        $this->writeState($state);
        return ['ok'=>true,'message'=>'Đã làm sạch trạng thái module và đồng bộ lại registry.'];
    }

    public function isEnabled(string $id): bool
    {
        $module = $this->find($id);
        return $module ? (bool)$module['enabled'] : false;
    }

    private function find(string $id): ?array
    {
        foreach ($this->catalog() as $module) if ($module['id'] === $id) return $module;
        return null;
    }

    private function validateManifest(array $manifest): array
    {
        $errors = [];
        foreach (['id','name','version','description','category'] as $field) {
            if (!isset($manifest[$field]) || trim((string)$manifest[$field]) === '') $errors[] = 'Thiếu trường ' . $field;
        }
        $id = (string)($manifest['id'] ?? '');
        if ($id !== '' && !preg_match('/^[a-z][a-z0-9-]{1,48}$/', $id)) $errors[] = 'ID module không hợp lệ';
        if (isset($manifest['route']) && !str_starts_with((string)$manifest['route'], '/')) $errors[] = 'Route phải bắt đầu bằng /';
        return ['ok'=>empty($errors),'errors'=>$errors];
    }

    private function manifestExists(string $id): bool
    {
        return is_file($this->modulePath . '/' . $id . '/module.json');
    }

    private function readState(): array
    {
        $data = @json_decode((string)@file_get_contents($this->stateFile), true);
        return is_array($data) ? array_merge(['schema'=>1,'enabled'=>[],'updated_at'=>[]], $data) : ['schema'=>1,'enabled'=>[],'updated_at'=>[]];
    }

    private function writeState(array $state): void
    {
        $state['schema'] = 1;
        $state['saved_at'] = date(DATE_ATOM);
        $json = json_encode($state, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($this->stateFile, $json, LOCK_EX) === false) throw new RuntimeException('Không thể lưu trạng thái module.');
        @chmod($this->stateFile, 0600);
    }
}
