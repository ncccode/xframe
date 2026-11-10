<?php
// +----------------------------------------------------------------------
// | XFrame设置
// +----------------------------------------------------------------------

return [
    // 后台应用入口配置，安装后台模块时会自动写入
    'backend_apps'          => [],
    // 允许跨域访问的域名
    'cors_request_domain'   => 'localhost,127.0.0.1',
    // 是否开启管理员登录验证码
    'admin_login_captcha'   => false,
    // 是否启用旧版图形验证码接口（/api/common/captcha），默认关闭，避免被用于绕过逻辑验证码
    'graphic_captcha'       => false,
    // 管理员登录失败可重试次数,false则无限
    'admin_login_retry'     => 10,
    // 开启管理员单处登录它处失效
    'admin_sso'             => false,
    // 管理员登录态保持时间（非刷新token，3天）
    'admin_token_keep_time' => 60 * 60 * 24 * 3,
    // 模块纯净安装（安装时移动模块文件而不是复制）
    'module_pure_install'   => true,
    // 模块包签名校验。生产环境建议保持开启，并配置 XFRAME_MODULE_SIGNING_PUBLIC_KEY 公钥。
    'module_signature'      => [
        'required'   => filter_var(getenv('XFRAME_MODULE_SIGNATURE_REQUIRED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'public_key' => getenv('XFRAME_MODULE_SIGNING_PUBLIC_KEY') ?: '',
    ],
    // 点选验证码配置
    'click_captcha'         => [
        // 模式:text=文字,icon=图标(若只有icon则适用于国际化站点)
        'mode'           => ['text', 'icon'],
        // 长度
        'length'         => 2,
        // 混淆点长度
        'confuse_length' => 2,
    ],
    // 验证码接口限流（同一 IP 每分钟每个验证码接口的请求上限，<=0 表示不限制）
    'captcha_rate_limit'    => 60,
    // 代理服务器IP（\think\Request 类将尝试获取这些代理服务器发送过来的真实IP）
    'proxy_server_ip'       => [],
    // 单个 xlsx 最大记录数
    'xls_max_number'        => 10000,
    // 导出任务脚本内存限制(MB)
    'memory_limit'          => 1024,
    // Token 配置
    'token'                 => [
        // 默认驱动方式
        'default' => 'mysql',
        // 加密key：优先读取环境变量 XFRAME_TOKEN_KEY，生产环境必须在 .env 中配置随机值，
        // 不要使用此处的内置默认值（该默认值已公开于源码，仅用于本地开发兜底）。
        'key'     => getenv('XFRAME_TOKEN_KEY') ?: 'tcbDgmqLVzuAdNH39o0QnhOisvSCFZ7I',
        // 加密方式
        'algo'    => 'ripemd160',
        // 驱动
        'stores'  => [
            'mysql' => [
                'type'   => 'Mysql',
                // 留空表示使用默认的 Mysql 数据库，也可以填写其他数据库连接配置的`name`
                'name'   => '',
                // 存储token的表名
                'table'  => 'token',
                // 默认 token 有效时间
                'expire' => 2592000,
            ],
            'redis' => [
                'type'       => 'Redis',
                'host'       => '127.0.0.1',
                'port'       => 6379,
                'password'   => '',
                // Db索引，非 0 以避免数据被意外清理
                'select'     => 1,
                'timeout'    => 0,
                // 默认 token 有效时间
                'expire'     => 2592000,
                'persistent' => false,
                'prefix'     => 'tk:',
            ],
        ]
    ],
    // 自动写入管理员操作日志
    'auto_write_admin_log'  => true,
    // 缺省头像图片路径
    'default_avatar'        => 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 128 128%22%3E%3Crect width=%22128%22 height=%22128%22 rx=%2264%22 fill=%22%23eef3f8%22/%3E%3Ccircle cx=%2264%22 cy=%2248%22 r=%2222%22 fill=%22%238aa1b5%22/%3E%3Cpath d=%22M24 112c6-24 22-38 40-38s34 14 40 38%22 fill=%22%238aa1b5%22/%3E%3C/svg%3E',
    // 内容分发网络URL，末尾不带`/`
    'cdn_url'               => '',
    // 内容分发网络URL参数，将自动添加 `?`，之后拼接到 cdn_url 的结尾（例如 `imageMogr2/format/heif`）
    'cdn_url_params'        => '',
    // 版本号
    'version'               => 'v1.0.0',
    // 中心接口地址（用于请求模块市场的数据等用途）
    'api_url'               => getenv('XFRAME_API_URL') ?: 'http://localhost:8787',
];
