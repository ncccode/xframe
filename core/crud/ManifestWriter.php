<?php

namespace core\crud;

use support\think\Db;

class ManifestWriter
{
    public function path(GeneratorOptions $options): string
    {
        return runtime_path() . '/crud/manifests/' . $options->manifestKey() . '.json';
    }

    public function exists(GeneratorOptions $options): bool
    {
        return is_file($this->path($options));
    }

    public function read(GeneratorOptions $options): array
    {
        $path = $this->path($options);
        if (!is_file($path)) {
            throw new CrudException('CRUD manifest was not found: ' . $path);
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            throw new CrudException('CRUD manifest is not valid JSON: ' . $path);
        }
        return $data;
    }

    public function write(GeneratorOptions $options, array $manifest): void
    {
        $path = $this->path($options);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    }

    public function delete(GeneratorOptions $options, bool $dryRun = false): array
    {
        $manifest = $this->read($options);
        $removedFiles = [];
        foreach (($manifest['files'] ?? []) as $file) {
            $absolute = base_path($file);
            if (is_file($absolute)) {
                $removedFiles[] = $file;
                if (!$dryRun) {
                    unlink($absolute);
                    $this->removeEmptyDirs(dirname($absolute));
                }
            }
        }

        $removedMenus = [];
        $ruleTable = $this->ruleTable((string) ($manifest['app'] ?? $options->app));
        foreach (($manifest['menu_names'] ?? []) as $name) {
            $removedMenus[] = $name;
            if (!$dryRun) {
                Db::name($ruleTable)->where('name', $name)->delete();
            }
        }

        if (!$dryRun && is_file($this->path($options))) {
            unlink($this->path($options));
        }

        return [
            'files' => $removedFiles,
            'menu_names' => $removedMenus,
            'manifest' => $this->path($options),
        ];
    }

    protected function removeEmptyDirs(string $dir): void
    {
        $base = base_path();
        while (str_starts_with($dir, $base) && $dir !== $base && is_dir($dir)) {
            $items = array_diff(scandir($dir) ?: [], ['.', '..']);
            if ($items) {
                return;
            }
            rmdir($dir);
            $dir = dirname($dir);
        }
    }

    protected function ruleTable(string $app): string
    {
        return $app === 'admin' ? 'admin_rule' : $app . '_rule';
    }
}
