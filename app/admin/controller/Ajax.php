<?php

namespace app\admin\controller;

use Throwable;
use support\Response;
use support\think\Db;
use support\think\Cache;
use Webman\Event\Event;
use app\admin\model\AdminLog;
use core\utils\Terminal;
use core\service\Upload;
use app\common\controller\Admin;

class Ajax extends Admin
{
    protected array $noNeedPermission = ['upload', 'area', 'buildSuffixSvg'];
    protected array $noNeedLogin = ['terminal', 'buildSuffixSvg'];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function upload(): Response
    {
        AdminLog::instance()->setTitle(__('upload'));
        $file   = $this->request->file('file');
        $driver = $this->request->input('driver', 'local');
        $topic  = $this->request->input('topic', 'default');
        $categoryId = $this->request->input('category_id', $this->request->input('cid', null));
        $mediaType = (string)($this->request->input('media_type', $this->request->input('type', '')));
        try {
            $upload = (new Upload())
                ->setFile($file)
                ->setDriver($driver)
                ->setTopic($topic)
                ->setMediaType($mediaType);
            if ($categoryId !== null && $categoryId !== '') {
                $upload->setCategoryId((int)$categoryId);
            }
            $attachment = $upload->upload(null, $this->auth->id);
            unset($attachment['create_time'], $attachment['quote']);
        } catch (Throwable $e) {
            return $this->error($e->getMessage());
        }

        return $this->success(__('File uploaded successfully'), [
            'file' => $attachment ?? []
        ]);
    }

    /**
     * 获取省市区数据
     * @throws Throwable
     */
    public function area(): Response
    {
        return $this->success('', \get_area());
    }

    public function buildSuffixSvg(): Response
    {
        $suffix     = $this->request->input('suffix', 'file');
        $background = $this->request->input('background');
        $content    = \build_suffix_svg((string)$suffix, (string)$background);
        return response($content, 200, [
            'Content-Length' => strlen($content),
            'Content-Type'   => 'image/svg+xml',
        ]);
    }

    /**
     * 获取已脱敏的数据库连接配置列表
     * @throws Throwable
     */
    public function getDatabaseConnectionList(): Response
    {
        $quickSearch     = $this->request->get("quickSearch", '');
        $connections     = config('think-orm.connections', []);
        $desensitization = [];
        foreach ($connections as $key => $connection) {
            $desensitization[] = [
                'type'     => $connection['type'],
                'database' => substr_replace($connection['database'], '****', 1, strlen($connection['database']) > 4 ? 2 : 1),
                'key'      => $key,
            ];
        }

        if ($quickSearch) {
            $quickSearch = preg_quote((string)$quickSearch, '/');
            $desensitization = array_filter($desensitization, function ($item) use ($quickSearch) {
                return preg_match("/$quickSearch/i", $item['key']);
            });
            $desensitization = array_values($desensitization);
        }

        return $this->success('', [
            'list' => $desensitization,
        ]);
    }

    /**
     * 获取表主键字段
     * @param ?string $table
     * @param ?string $connection
     * @throws Throwable
     */
    public function getTablePk(?string $table = null, ?string $connection = null): Response
    {
        if (!$table || !$this->isValidTableName($table) || !$this->isValidConnectionName($connection)) {
            return $this->error(__('Parameter error'));
        }

        $tablePk = Db::connect($connection ?: null)
            ->table($table)
            ->getPk();
        return $this->success('', ['pk' => $tablePk]);
    }

    /**
     * 获取数据表列表
     * @throws Throwable
     */
    public function getTableList(): Response
    {
        $quickSearch  = $this->request->get("quickSearch", '');
        $connection   = $this->request->input('connection');// 数据库连接配置标识
        $samePrefix   = $this->request->input('samePrefix', true);// 是否仅返回项目数据表（前缀同项目一致的）
        $excludeTable = $this->request->input('excludeTable', []);// 要排除的数据表数组（表名无需带前缀）

        if (!$this->isValidConnectionName($connection)) {
            return $this->error(__('Parameter error'));
        }

        $outTables = [];
        $dbConfig = config('think-orm.connections.' . ($connection ?: config('think-orm.default')), []);
        $db       = Db::connect($connection ?: null);
        if (($dbConfig['type'] ?? 'mysql') === 'pgsql') {
            $rows = $db->query("select tablename as name from pg_tables where schemaname = 'public'");
            $tables = array_column($rows, 'name', 'name');
        } else {
            $rows = $db->query('SHOW TABLE STATUS');
            $tables = [];
            foreach ($rows as $row) {
                $name = $row['Name'] ?? $row['name'] ?? '';
                if ($name !== '') {
                    $tables[$name] = $row['Comment'] ?? $name;
                }
            }
        }

        if ($quickSearch) {
            $quickSearch = preg_quote((string)$quickSearch, '/');
            $tables = array_filter($tables, function ($comment) use ($quickSearch) {
                return preg_match("/$quickSearch/i", $comment);
            });
        }

        $pattern = '/^' . $dbConfig['prefix'] . '/i';
        foreach ($tables as $table => $comment) {
            if ($samePrefix && !preg_match($pattern, $table)) continue;

            $table = preg_replace($pattern, '', $table);
            if (!in_array($table, $excludeTable)) {
                $outTables[] = [
                    'table'      => $table,
                    'comment'    => $comment,
                    'connection' => $connection,
                    'prefix'     => $dbConfig['prefix'],
                ];
            }
        }

        return $this->success('', [
            'list' => $outTables,
        ]);
    }

    /**
     * 获取数据表字段列表
     * @throws Throwable
     */
    public function getTableFieldList(): Response
    {
        $table      = $this->request->input('table');
        $clean      = $this->request->input('clean', true);
        $connection = $this->request->input('connection');
        if (!$table || !$this->isValidTableName($table) || !$this->isValidConnectionName($connection)) {
            return $this->error(__('Parameter error'));
        }

        $dbConfig = config('think-orm.connections.' . ($connection ?: config('think-orm.default')), []);
        $db = Db::connect($connection ?: null);
        $tablePk = $db->name($table)->getPk();
        if (($dbConfig['type'] ?? 'mysql') === 'pgsql') {
            $rows = $db->query('select column_name as field, data_type as type from information_schema.columns where table_name = ?', [$table]);
        } else {
            $rows = $db->query('SHOW FULL COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        }
        $fieldList = [];
        foreach ($rows as $row) {
            $fieldList[] = [
                'name'    => $row['Field'] ?? $row['field'] ?? '',
                'type'    => $row['Type'] ?? $row['type'] ?? '',
                'comment' => $row['Comment'] ?? '',
            ];
        }
        return $this->success('', [
            'pk'        => $tablePk,
            'fieldList' => $fieldList,
        ]);
    }

    public function changeTerminalConfig(): Response
    {
        AdminLog::instance()->setTitle(__('Change terminal config'));
        if (Terminal::changeTerminalConfig()) {
            return $this->success();
        }
        return $this->error(__('Failed to modify the terminal configuration. Please modify the configuration file manually:%s', ['/config/terminal.php']));
    }

    public function terminalTicket(): Response
    {
        if (!$this->auth->isSuperAdmin()) {
            return $this->error(__('You have no permission'), [], 401);
        }

        $ticket = bin2hex(random_bytes(32));
        Cache::set('terminal_ticket_' . $ticket, [
            'token' => $this->auth->getToken(),
            'admin_id' => $this->auth->id,
        ], 60);

        return $this->success('', ['ticket' => $ticket]);
    }

    public function clearCache(): Response
    {
        AdminLog::instance()->setTitle(__('Clear cache'));
        $type = $this->request->post('type');
        if ($type == 'tp' || $type == 'all') {
            Cache::clear();
        } else {
            return $this->error(__('Parameter error'));
        }
        Event::emit('cacheClearAfter', $this->request);
        return $this->success(__('Cache cleaned~'));
    }

    /**
     * Web 终端，内部会校验超级管理员权限。
     *
     * @throws Throwable
     */
    public function terminal(): Response
    {
        return (new Terminal())->exec();
    }

    /**
     * 校验数据表名称是否合法（仅允许字母、数字、下划线）
     * @param mixed $table
     * @return bool
     */
    protected function isValidTableName(mixed $table): bool
    {
        return is_string($table) && (bool)preg_match('/^[A-Za-z0-9_]+$/', $table);
    }

    /**
     * 校验数据库连接标识是否合法（空表示默认连接，否则必须是已配置的连接名）
     * @param mixed $connection
     * @return bool
     */
    protected function isValidConnectionName(mixed $connection): bool
    {
        if ($connection === null || $connection === '') {
            return true;
        }
        if (!is_string($connection) || !preg_match('/^[A-Za-z0-9_.-]+$/', $connection)) {
            return false;
        }
        return array_key_exists($connection, (array)config('think-orm.connections', []));
    }
}
