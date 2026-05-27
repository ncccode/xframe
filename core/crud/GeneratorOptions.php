<?php

namespace core\crud;

class GeneratorOptions
{
    public string $app = 'admin';

    public string $table = '';

    public string $title = '';

    public string $path = '';

    public string $parent = '';

    public string $connection = '';

    public array $quickSearch = [];

    public string $sortField = '';

    public array $spec = [];

    public bool $force = false;

    public bool $dryRun = false;

    public bool $delete = false;

    public bool $noMenu = false;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        $this->app = self::normalizeApp($this->app ?: 'admin');
        $this->table = self::normalizeTable($this->table);
        $this->path = self::normalizePath($this->path ?: $this->table);
        $this->parent = trim($this->parent, '/');
        $this->connection = trim($this->connection);
        $this->sortField = trim($this->sortField);
        $this->quickSearch = array_values(array_filter(array_map(
            static fn($field) => self::normalizeField((string) $field),
            $this->quickSearch
        )));
    }

    public static function normalizeApp(string $app): string
    {
        $app = trim($app);
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $app)) {
            throw new CrudException('The app option must be a valid PHP namespace segment.');
        }
        return $app;
    }

    public static function normalizeTable(string $table): string
    {
        $table = trim($table);
        if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new CrudException('The table option is required and may only contain letters, numbers and underscores.');
        }
        return $table;
    }

    public static function normalizeField(string $field): string
    {
        $field = trim($field);
        if ($field === '' || !preg_match('/^[A-Za-z0-9_]+$/', $field)) {
            return '';
        }
        return $field;
    }

    public static function normalizePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === '') {
            throw new CrudException('The path option can not be empty.');
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            $segment = strtolower(str_replace('-', '_', trim($segment)));
            $segment = preg_replace('/[^a-z0-9_]/', '', $segment);
            if ($segment === '') {
                continue;
            }
            $segments[] = $segment;
        }

        if (!$segments) {
            throw new CrudException('The path option must contain at least one valid segment.');
        }

        return implode('/', $segments);
    }

    public function manifestKey(): string
    {
        return $this->app . '-' . str_replace('/', '-', $this->path);
    }
}
