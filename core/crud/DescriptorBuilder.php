<?php

namespace core\crud;

class DescriptorBuilder
{
    protected array $systemFields = ['create_time', 'update_time'];

    public function build(array $schema, GeneratorOptions $options): array
    {
        $fields = [];
        foreach ($schema['columns'] as $column) {
            $fields[$column['name']] = $this->buildField($column, $options->spec['fields'][$column['name']] ?? []);
        }

        $pk = $this->primaryKey($fields);
        $sortField = $options->sortField ?: (isset($fields['sort']) ? 'sort' : (isset($fields['weigh']) ? 'weigh' : ''));
        if ($sortField && !isset($fields[$sortField])) {
            throw new CrudException("Sort field [$sortField] does not exist in table [{$schema['physicalTable']}].");
        }

        $quickSearch = $this->quickSearchFields($fields, $options->quickSearch, $pk);
        $title = $options->title ?: $this->cleanComment((string) $schema['comment']);
        if ($title === '') {
            throw new CrudException('The title option is required when the table has no comment.');
        }

        return [
            'app' => $options->app,
            'table' => $schema['table'],
            'physicalTable' => $schema['physicalTable'],
            'connection' => $schema['connection'],
            'title' => $title,
            'path' => $options->path,
            'parent' => $options->parent,
            'pk' => $pk,
            'sortField' => $sortField,
            'quickSearch' => $quickSearch,
            'fields' => $fields,
            'hasCreateTime' => isset($fields['create_time']),
            'hasUpdateTime' => isset($fields['update_time']),
        ];
    }

    protected function buildField(array $column, array $override): array
    {
        $dictData = $this->parseDictComment((string) $column['comment']);
        $name = $column['name'];
        if ($name === 'status' && !$dictData['dict'] && $this->isNumericColumn($column)) {
            $dictData = [
                'label' => '状态',
                'dict' => ['1' => '启用', '0' => '禁用'],
            ];
        }

        $label = (string) ($override['label'] ?? $dictData['label'] ?: $this->humanize($name));
        $dict = $override['dict'] ?? $dictData['dict'];
        $input = (string) ($override['input'] ?? $this->inferInputType($column, $dict));
        $tableRender = (string) ($override['tableRender'] ?? $this->inferTableRender($column, $input, $dict));
        $required = array_key_exists('required', $override)
            ? (bool) $override['required']
            : (!$column['nullable'] && !$column['autoIncrement'] && $column['default'] === null && !in_array($name, $this->systemFields, true));

        return [
            'name' => $name,
            'type' => strtolower((string) $column['type']),
            'dataType' => strtolower((string) $column['dataType']),
            'default' => $this->normalizeDefault($column['default']),
            'nullable' => (bool) $column['nullable'],
            'primary' => (bool) $column['primary'],
            'autoIncrement' => (bool) $column['autoIncrement'],
            'comment' => (string) $column['comment'],
            'label' => $label,
            'enLabel' => $this->humanize($name),
            'dict' => $dict,
            'input' => $input,
            'tableRender' => $tableRender,
            'required' => $required,
            'formVisible' => !$column['primary'] && !in_array($name, $this->systemFields, true),
            'tableVisible' => !in_array($input, ['password', 'textarea', 'editor', 'array', 'file', 'files'], true),
            'operator' => $this->operator($input, $dict),
            'modelType' => $this->modelType($column),
        ];
    }

    protected function parseDictComment(string $comment): array
    {
        $comment = trim(str_replace(['，', '：'], [',', ':'], $comment));
        if ($comment === '') {
            return ['label' => '', 'dict' => []];
        }

        if (!str_contains($comment, ':') || !str_contains($comment, '=')) {
            return ['label' => $this->cleanComment($comment), 'dict' => []];
        }

        [$label, $items] = explode(':', $comment, 2);
        $dict = [];
        foreach (explode(',', $items) as $item) {
            if (!str_contains($item, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $item, 2);
            $key = trim($key);
            if ($key !== '') {
                $dict[$key] = trim($value);
            }
        }

        return ['label' => $this->cleanComment($label), 'dict' => $dict];
    }

    protected function inferInputType(array $column, array $dict): string
    {
        $name = strtolower((string) $column['name']);
        $type = strtolower((string) $column['type']);
        $dataType = strtolower((string) $column['dataType']);

        if ($dict) {
            return in_array($type, ['set'], true) || str_ends_with($name, '_ids') ? 'checkbox' : 'radio';
        }
        if ($name === 'password') {
            return 'password';
        }
        if (in_array($name, ['status', 'state', 'type'], true) && preg_match('/^(tinyint|smallint|int|integer|bool|boolean)/', $type)) {
            return 'radio';
        }
        if (str_ends_with($name, 'switch') || str_ends_with($name, 'toggle')) {
            return 'switch';
        }
        if (in_array($type, ['datetime', 'timestamp', 'timestamptz'], true)) {
            return 'datetime';
        }
        if ($this->isNumericColumn($column) && (str_ends_with($name, 'time') || str_ends_with($name, 'datetime'))) {
            return 'datetime';
        }
        if ($type === 'date') {
            return 'date';
        }
        if ($type === 'time') {
            return 'time';
        }
        if ($type === 'year') {
            return 'year';
        }
        if (str_ends_with($name, 'content') || str_ends_with($name, 'editor')) {
            return in_array($type, ['text', 'mediumtext', 'longtext', 'tinytext'], true) ? 'editor' : 'textarea';
        }
        if (in_array($type, ['text', 'mediumtext', 'longtext', 'tinytext', 'json'], true)) {
            return 'textarea';
        }
        if (str_ends_with($name, 'images') || str_ends_with($name, 'avatars')) {
            return 'images';
        }
        if (str_ends_with($name, 'image') || str_ends_with($name, 'avatar')) {
            return 'image';
        }
        if (str_ends_with($name, 'files')) {
            return 'files';
        }
        if (str_ends_with($name, 'file')) {
            return 'file';
        }
        if (str_ends_with($name, 'icon')) {
            return 'icon';
        }
        if (str_ends_with($name, 'color')) {
            return 'color';
        }
        if (str_ends_with($name, '_ids')) {
            return 'remoteSelects';
        }
        if (str_ends_with($name, '_id')) {
            return 'remoteSelect';
        }
        if ($this->isNumericColumn($column)) {
            return 'number';
        }
        return 'string';
    }

    protected function inferTableRender(array $column, string $input, array $dict): string
    {
        if ($dict && in_array($input, ['radio', 'select'], true)) {
            return 'tag';
        }
        return match ($input) {
            'switch' => 'switch',
            'datetime', 'date', 'time', 'year' => 'datetime',
            'image' => 'image',
            'images' => 'images',
            'icon' => 'icon',
            'color' => 'color',
            default => '',
        };
    }

    protected function operator(string $input, array $dict): string|false
    {
        if (in_array($input, ['textarea', 'editor', 'image', 'images', 'file', 'files', 'array'], true)) {
            return false;
        }
        if ($dict || in_array($input, ['radio', 'checkbox', 'switch', 'select', 'selects'], true)) {
            return '=';
        }
        if ($input === 'number') {
            return 'RANGE';
        }
        if (in_array($input, ['datetime', 'date', 'time', 'year'], true)) {
            return 'RANGE';
        }
        return 'LIKE';
    }

    protected function modelType(array $column): string
    {
        $type = strtolower((string) $column['type']);
        return match (true) {
            in_array($type, ['datetime', 'timestamp', 'date'], true) => 'datetime',
            in_array($type, ['json'], true) => 'json',
            default => '',
        };
    }

    protected function primaryKey(array $fields): string
    {
        foreach ($fields as $field) {
            if ($field['primary']) {
                return $field['name'];
            }
        }
        return isset($fields['id']) ? 'id' : array_key_first($fields);
    }

    protected function quickSearchFields(array $fields, array $provided, string $pk): array
    {
        if ($provided) {
            foreach ($provided as $field) {
                if (!isset($fields[$field])) {
                    throw new CrudException("Quick search field [$field] does not exist.");
                }
            }
            return $provided;
        }

        $preferred = ['name', 'title', 'username', 'nickname', 'mobile', 'email'];
        $result = [];
        foreach ($preferred as $field) {
            if (isset($fields[$field])) {
                $result[] = $field;
            }
        }
        return $result ?: [$pk];
    }

    protected function normalizeDefault(mixed $default): mixed
    {
        if (!is_string($default)) {
            return $default;
        }
        $upper = strtoupper($default);
        if (str_contains($upper, 'CURRENT_TIMESTAMP') || str_contains($default, 'nextval(')) {
            return null;
        }
        return $default;
    }

    protected function isNumericColumn(array $column): bool
    {
        $type = strtolower((string) $column['type']);
        $dataType = strtolower((string) $column['dataType']);
        return (bool) preg_match('/^(tinyint|smallint|mediumint|int|integer|bigint|decimal|double|float|real|numeric|bool|boolean)/', $type . ' ' . $dataType);
    }

    protected function cleanComment(string $comment): string
    {
        $comment = trim($comment);
        if (str_contains($comment, ':')) {
            $comment = explode(':', $comment, 2)[0];
        }
        return trim($comment);
    }

    protected function humanize(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }
}
