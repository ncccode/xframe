<?php

namespace core\crud;

use support\think\Db;
use Throwable;

class SchemaIntrospector
{
    public function inspect(string $table, string $connection = ''): array
    {
        $connection = $connection ?: (string) config('think-orm.default', 'mysql');
        $config = config("think-orm.connections.$connection", []);
        if (!$config) {
            throw new CrudException("Database connection [$connection] is not configured.");
        }

        $driver = (string) ($config['type'] ?? 'mysql');
        $prefix = (string) ($config['prefix'] ?? '');
        $modelTable = $prefix && str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : $table;
        $physicalTable = $prefix . $modelTable;

        return match ($driver) {
            'pgsql' => $this->inspectPostgres($connection, $config, $physicalTable, $modelTable),
            default => $this->inspectMysql($connection, $config, $physicalTable, $modelTable),
        };
    }

    protected function inspectMysql(string $connection, array $config, string $physicalTable, string $modelTable): array
    {
        $database = (string) ($config['database'] ?? '');
        if ($database === '') {
            throw new CrudException("Database name for connection [$connection] is not configured.");
        }

        try {
            $tables = Db::connect($connection)->query(
                'SELECT TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [$database, $physicalTable]
            );
        } catch (Throwable $e) {
            throw new CrudException('Unable to inspect database: ' . $e->getMessage(), 0, $e);
        }

        if (!$tables) {
            throw new CrudException("Table [$physicalTable] was not found on connection [$connection].");
        }

        $rows = Db::connect($connection)->query(
            'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE, COLUMN_KEY, EXTRA, COLUMN_COMMENT, ORDINAL_POSITION
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$database, $physicalTable]
        );

        $columns = [];
        foreach ($rows as $row) {
            $columns[] = [
                'name' => $row['COLUMN_NAME'],
                'type' => strtolower((string) $row['DATA_TYPE']),
                'dataType' => strtolower((string) $row['COLUMN_TYPE']),
                'default' => $row['COLUMN_DEFAULT'],
                'nullable' => $row['IS_NULLABLE'] === 'YES',
                'primary' => $row['COLUMN_KEY'] === 'PRI',
                'autoIncrement' => str_contains(strtolower((string) $row['EXTRA']), 'auto_increment'),
                'unsigned' => str_contains(strtolower((string) $row['COLUMN_TYPE']), 'unsigned'),
                'comment' => (string) $row['COLUMN_COMMENT'],
                'ordinal' => (int) $row['ORDINAL_POSITION'],
            ];
        }

        return [
            'connection' => $connection,
            'driver' => 'mysql',
            'table' => $modelTable,
            'physicalTable' => $physicalTable,
            'comment' => (string) ($tables[0]['TABLE_COMMENT'] ?? ''),
            'columns' => $columns,
        ];
    }

    protected function inspectPostgres(string $connection, array $config, string $physicalTable, string $modelTable): array
    {
        $schema = (string) ($config['schema'] ?? 'public');

        try {
            $tables = Db::connect($connection)->query(
                "SELECT c.relname, obj_description(c.oid) AS table_comment
                 FROM pg_class c
                 JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = ? AND c.relname = ? AND c.relkind IN ('r', 'p')",
                [$schema, $physicalTable]
            );
        } catch (Throwable $e) {
            throw new CrudException('Unable to inspect database: ' . $e->getMessage(), 0, $e);
        }

        if (!$tables) {
            throw new CrudException("Table [$schema.$physicalTable] was not found on connection [$connection].");
        }

        $primaryRows = Db::connect($connection)->query(
            "SELECT a.attname
             FROM pg_index i
             JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
             WHERE i.indrelid = (? || '.' || ?)::regclass AND i.indisprimary",
            [$schema, $physicalTable]
        );
        $primaryFields = array_column($primaryRows, 'attname');

        $rows = Db::connect($connection)->query(
            "SELECT column_name, data_type, udt_name, column_default, is_nullable,
                    character_maximum_length, numeric_precision, numeric_scale, ordinal_position,
                    col_description((? || '.' || ?)::regclass, ordinal_position) AS column_comment
             FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ?
             ORDER BY ordinal_position",
            [$schema, $physicalTable, $schema, $physicalTable]
        );

        $columns = [];
        foreach ($rows as $row) {
            $dataType = $this->postgresDataType($row);
            $default = $row['column_default'] ?? null;
            $columns[] = [
                'name' => $row['column_name'],
                'type' => strtolower((string) ($row['udt_name'] ?: $row['data_type'])),
                'dataType' => $dataType,
                'default' => $default,
                'nullable' => $row['is_nullable'] === 'YES',
                'primary' => in_array($row['column_name'], $primaryFields, true),
                'autoIncrement' => is_string($default) && (str_contains($default, 'nextval(') || str_contains($default, 'identity')),
                'unsigned' => false,
                'comment' => (string) ($row['column_comment'] ?? ''),
                'ordinal' => (int) $row['ordinal_position'],
            ];
        }

        return [
            'connection' => $connection,
            'driver' => 'pgsql',
            'table' => $modelTable,
            'physicalTable' => $physicalTable,
            'comment' => (string) ($tables[0]['table_comment'] ?? ''),
            'columns' => $columns,
        ];
    }

    protected function postgresDataType(array $row): string
    {
        $type = strtolower((string) ($row['data_type'] ?: $row['udt_name']));
        if ($row['character_maximum_length']) {
            return $type . '(' . (int) $row['character_maximum_length'] . ')';
        }
        if ($row['numeric_precision']) {
            $precision = (int) $row['numeric_precision'];
            $scale = (int) ($row['numeric_scale'] ?? 0);
            return $scale > 0 ? "$type($precision,$scale)" : "$type($precision)";
        }
        return $type;
    }
}
