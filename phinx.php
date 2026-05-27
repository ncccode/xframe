<?php

require_once __DIR__ . '/vendor/autoload.php';

if (class_exists(Dotenv\Dotenv::class) && file_exists(__DIR__ . '/.env')) {
    if (method_exists(Dotenv\Dotenv::class, 'createUnsafeImmutable')) {
        Dotenv\Dotenv::createUnsafeImmutable(__DIR__)->load();
    } else {
        Dotenv\Dotenv::createMutable(__DIR__)->load();
    }
}

$adapter = (string) (getenv('DB_CONNECTION') ?: 'mysql');
$defaultPort = $adapter === 'pgsql' ? '5432' : '3306';

return [
    'paths' => [
        'migrations' => 'database/migrations',
        'seeds' => 'database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'default',
        'default' => [
            'adapter' => $adapter,
            'host' => (string) (getenv('DB_HOST') ?: '127.0.0.1'),
            'name' => (string) (getenv('DB_DATABASE') ?: 'xframe'),
            'user' => (string) (getenv('DB_USERNAME') ?: ($adapter === 'pgsql' ? 'postgres' : 'root')),
            'pass' => (string) (getenv('DB_PASSWORD') ?: ''),
            'port' => (string) (getenv('DB_PORT') ?: $defaultPort),
            'charset' => $adapter === 'pgsql' ? 'utf8' : 'utf8mb4',
        ],
    ],
    'version_order' => 'creation',
];
