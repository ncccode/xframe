<?php

$connection = getenv('DB_CONNECTION') ?: 'mysql';
$defaultPort = $connection === 'pgsql' ? '5432' : '3306';

return [
    'default' => $connection,
    'connections' => [
        $connection => [
            // 数据库类型
            'type' => $connection,
            // 服务器地址
            'hostname' => getenv('DB_HOST') ?: '127.0.0.1',
            // 数据库名
            'database' => getenv('DB_DATABASE') ?: 'xframe',
            // 数据库用户名
            'username' => getenv('DB_USERNAME') ?: ($connection === 'pgsql' ? 'postgres' : 'root'),
            // 数据库密码
            'password' => getenv('DB_PASSWORD') ?: '',
            // 数据库连接端口
            'hostport' => getenv('DB_PORT') ?: $defaultPort,
            // 数据库连接参数
            'params' => [
                // 连接超时3秒
                \PDO::ATTR_TIMEOUT => 3,
            ],
            // 数据库编码默认采用utf8
            'charset' => 'utf8',
            // 数据库表前缀
            'prefix' => '',
            // 断线重连
            'break_reconnect' => true,
            // 连接池配置
            'pool' => [
                'max_connections' => 5, // 最大连接数
                'min_connections' => 1, // 最小连接数
                'wait_timeout' => 3,    // 从连接池获取连接等待超时时间
                'idle_timeout' => 60,   // 连接最大空闲时间，超过该时间会被回收
                'heartbeat_interval' => 50, // 心跳检测间隔，需要小于60秒
            ],
        ],
    ],
    // 自定义分页类
    'paginator' =>  '',
];
