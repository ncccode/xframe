<?php

use Phinx\Seed\AbstractSeed;

final class XFrameSeeder extends AbstractSeed
{
    public function run(): void
    {
        require_once dirname(__DIR__, 2) . '/app/functions.php';

        $now = date('Y-m-d H:i:s');

        $this->seedAdmin($now);
        $this->seedGroups($now);
        $this->seedGroupAccess();
        $this->seedRules($now);
        $this->seedConfig($now);
        $this->resetPostgresSequences();
    }

    private function seedAdmin(string $now): void
    {
        if ($this->fetchRow('SELECT id FROM admin WHERE id = 1')) {
            return;
        }

        $this->table('admin')->insert([
            [
                'id' => 1,
                'username' => 'admin',
                'nickname' => 'Admin',
                'avatar' => '',
                'email' => 'admin@xframe.local',
                'mobile' => '',
                'password' => hash_password((string) (getenv('XFRAME_DEFAULT_PASSWORD') ?: '123456')),
                'motto' => 'XFrame',
                'status' => 1,
                'login_failure' => 0,
                'last_login_time' => null,
                'last_login_ip' => '',
                'create_time' => $now,
                'update_time' => $now,
            ],
        ])->saveData();
    }

    private function seedGroups(string $now): void
    {
        if ($this->fetchRow('SELECT id FROM admin_group WHERE id = 1')) {
            return;
        }

        $this->table('admin_group')->insert([
            [
                'id' => 1,
                'pid' => 0,
                'name' => '超级管理组',
                'rules' => '*',
                'status' => 1,
                'sort' => 100,
                'create_time' => $now,
                'update_time' => $now,
            ],
        ])->saveData();
    }

    private function seedGroupAccess(): void
    {
        if ($this->fetchRow('SELECT uid FROM admin_group_access WHERE uid = 1 AND group_id = 1')) {
            return;
        }

        $this->table('admin_group_access')->insert([
            ['uid' => 1, 'group_id' => 1],
        ])->saveData();
    }

    private function seedRules(string $now): void
    {
        $rows = [
            [1, 0, 'menu', '控制台', 'dashboard/dashboard', 'dashboard', 'fa fa-dashboard', 'tab', '', '/src/views/admin/dashboard.vue', 1, 999, '后台首页'],
            [2, 0, 'menu_dir', '权限管理', 'system', 'system', 'fa fa-group', 'tab', '', '', 0, 100, ''],
            [3, 2, 'menu', '管理员', 'system/admin', 'admin', 'fa fa-user-o', 'tab', '', '/src/views/admin/system/admin/index.vue', 1, 99, ''],
            [4, 3, 'button', '查看', 'system/admin/index', '', '', 'tab', '', '', 0, 0, ''],
            [5, 3, 'button', '添加', 'system/admin/add', '', '', 'tab', '', '', 0, 0, ''],
            [6, 3, 'button', '编辑', 'system/admin/edit', '', '', 'tab', '', '', 0, 0, ''],
            [7, 3, 'button', '删除', 'system/admin/del', '', '', 'tab', '', '', 0, 0, ''],
            [8, 3, 'button', '排序', 'system/admin/sortable', '', '', 'tab', '', '', 0, 0, ''],
            [9, 2, 'menu', '角色组', 'system/group', 'group', 'fa fa-group', 'tab', '', '/src/views/admin/system/group/index.vue', 1, 98, ''],
            [10, 9, 'button', '查看', 'system/group/index', '', '', 'tab', '', '', 0, 0, ''],
            [11, 9, 'button', '添加', 'system/group/add', '', '', 'tab', '', '', 0, 0, ''],
            [12, 9, 'button', '编辑', 'system/group/edit', '', '', 'tab', '', '', 0, 0, ''],
            [13, 9, 'button', '删除', 'system/group/del', '', '', 'tab', '', '', 0, 0, ''],
            [14, 9, 'button', '排序', 'system/group/sortable', '', '', 'tab', '', '', 0, 0, ''],
            [15, 2, 'menu', '菜单规则', 'system/rule', 'rule', 'fa fa-list', 'tab', '', '/src/views/admin/system/rule/index.vue', 1, 97, ''],
            [16, 15, 'button', '查看', 'system/rule/index', '', '', 'tab', '', '', 0, 0, ''],
            [17, 15, 'button', '添加', 'system/rule/add', '', '', 'tab', '', '', 0, 0, ''],
            [18, 15, 'button', '编辑', 'system/rule/edit', '', '', 'tab', '', '', 0, 0, ''],
            [19, 15, 'button', '删除', 'system/rule/del', '', '', 'tab', '', '', 0, 0, ''],
            [20, 15, 'button', '排序', 'system/rule/sortable', '', '', 'tab', '', '', 0, 0, ''],
            [21, 2, 'menu', '操作日志', 'system/adminLog', 'system/adminLog', 'el-icon-List', 'tab', '', '/src/views/admin/system/adminLog/index.vue', 1, 96, ''],
            [22, 21, 'button', '查看', 'system/adminLog/index', '', '', 'tab', '', '', 0, 0, ''],
            [23, 21, 'button', '删除', 'system/adminLog/del', '', '', 'tab', '', '', 0, 0, ''],
            [24, 2, 'menu', '系统配置', 'system/config/menu', 'system/config', 'el-icon-Tools', 'tab', '', '/src/views/admin/system/config/index.vue', 1, 95, '', 'add_menu_only'],
            [25, 2, 'menu', '系统配置（路由）', 'system/config', 'system/config', 'el-icon-Tools', 'tab', '', '/src/views/admin/system/config/index.vue', 1, 94, '', 'add_rules_only'],
            [26, 25, 'button', '查看', 'system/config/index', '', '', 'tab', '', '', 0, 0, ''],
            [27, 25, 'button', '编辑', 'system/config/edit', '', '', 'tab', '', '', 0, 0, ''],
            [28, 25, 'button', '添加', 'system/config/add', '', '', 'tab', '', '', 0, 0, ''],
            [29, 25, 'button', '删除', 'system/config/del', '', '', 'tab', '', '', 0, 0, ''],
            [30, 2, 'menu', '附件管理', 'system/attachment', 'system/attachment', 'fa fa-folder', 'tab', '', '/src/views/admin/system/attachment/index.vue', 1, 93, '同一文件被多次上传时，只会保存一份至磁盘和增加一条附件记录；删除附件记录，将自动删除对应文件！'],
            [31, 30, 'button', '查看', 'system/attachment/index', '', '', 'tab', '', '', 0, 0, ''],
            [32, 30, 'button', '编辑', 'system/attachment/edit', '', '', 'tab', '', '', 0, 0, ''],
            [33, 30, 'button', '删除', 'system/attachment/del', '', '', 'tab', '', '', 0, 0, ''],
            [34, 30, 'button', '移动', 'system/attachment/move', '', '', 'tab', '', '', 0, 0, ''],
            [35, 30, 'button', '分组查看', 'system/attachmentCategory/index', '', '', 'tab', '', '', 0, 0, ''],
            [36, 30, 'button', '分组添加', 'system/attachmentCategory/add', '', '', 'tab', '', '', 0, 0, ''],
            [37, 30, 'button', '分组编辑', 'system/attachmentCategory/edit', '', '', 'tab', '', '', 0, 0, ''],
            [38, 30, 'button', '分组删除', 'system/attachmentCategory/del', '', '', 'tab', '', '', 0, 0, ''],
            [39, 2, 'menu', '个人资料', 'system/adminInfo', 'system/adminInfo', 'fa fa-user', 'tab', '', '/src/views/admin/system/adminInfo.vue', 1, 92, ''],
            [40, 39, 'button', '查看', 'system/adminInfo/index', '', '', 'tab', '', '', 0, 0, ''],
            [41, 39, 'button', '编辑', 'system/adminInfo/edit', '', '', 'tab', '', '', 0, 0, ''],
            [42, 2, 'menu', '错误日志', 'system/errorLog', 'system/errorLog', 'el-icon-CircleCloseFilled', 'tab', '', '/src/views/admin/system/errorLog/index.vue', 1, 91, ''],
            [43, 42, 'button', '查看', 'system/errorLog/index', '', '', 'tab', '', '', 0, 0, ''],
            [44, 42, 'button', '查看详情', 'system/errorLog/item', '', '', 'tab', '', '', 0, 0, ''],
            [45, 42, 'button', '下载', 'system/errorLog/download', '', '', 'tab', '', '', 0, 0, ''],
            [46, 42, 'button', '删除', 'system/errorLog/del', '', '', 'tab', '', '', 0, 0, ''],
            [47, 42, 'button', '全部删除', 'system/errorLog/delall', '', '', 'tab', '', '', 0, 0, ''],
        ];

        $insert = [];
        foreach ($rows as $row) {
            [$id, $pid, $type, $title, $name, $path, $icon, $menuType, $url, $component, $keepalive, $sort, $remark] = $row;
            $extend = $row[13] ?? 'none';
            if ($this->adminRuleRowExists($id, $name)) {
                continue;
            }
            $insert[] = [
                'id' => $id,
                'pid' => $pid,
                'type' => $type,
                'title' => $title,
                'name' => $name,
                'path' => $path,
                'icon' => $icon,
                'menu_type' => $menuType,
                'url' => $url,
                'component' => $component,
                'keepalive' => $keepalive,
                'extend' => $extend,
                'remark' => $remark,
                'sort' => $sort,
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ];
        }

        if ($insert) {
            $this->table('admin_rule')->insert($insert)->saveData();
        }
    }

    private function adminRuleRowExists(int $id, string $name): bool
    {
        $adapter = $this->getAdapter();
        $stmt = $this->query(sprintf(
            'SELECT id FROM %s WHERE %s = ? OR %s = ?',
            $adapter->quoteTableName('admin_rule'),
            $adapter->quoteColumnName('id'),
            $adapter->quoteColumnName('name')
        ), [$id, $name]);

        return is_object($stmt) && method_exists($stmt, 'fetch') && (bool) $stmt->fetch();
    }

    private function seedConfig(string $now): void
    {
        $defaults = [
            'tip' => '',
            'value' => null,
            'content' => null,
            'rule' => '',
            'extend' => '',
            'allow_del' => 0,
            'sort' => 0,
            'create_time' => $now,
            'update_time' => $now,
        ];

        $rows = [
            [
                'name' => 'config_group',
                'group' => 'basics',
                'title' => 'Config group',
                'type' => 'array',
                'value' => '[{"key":"basics","value":"Basics"}]',
                'rule' => 'required',
                'sort' => -1,
            ],
            [
                'name' => 'site_name',
                'group' => 'basics',
                'title' => 'Site Name',
                'type' => 'string',
                'value' => 'XFrame',
                'rule' => 'required',
                'sort' => 99,
            ],
            [
                'name' => 'record_number',
                'group' => 'basics',
                'title' => 'Record number',
                'tip' => '域名备案号',
                'type' => 'string',
                'value' => '',
                'sort' => 98,
            ],
            [
                'name' => 'version',
                'group' => 'basics',
                'title' => 'Version number',
                'tip' => '系统版本号',
                'type' => 'string',
                'value' => 'v1.0.0',
                'rule' => 'required',
                'sort' => 97,
            ],
            [
                'name' => 'time_zone',
                'group' => 'basics',
                'title' => 'time zone',
                'type' => 'string',
                'value' => 'Asia/Shanghai',
                'rule' => 'required',
                'sort' => 96,
            ],
            [
                'name' => 'no_access_ip',
                'group' => 'basics',
                'title' => 'No access ip',
                'tip' => '禁止访问站点的ip列表,一行一个',
                'type' => 'textarea',
                'sort' => 95,
            ],
            [
                'name' => 'admin_entrance',
                'group' => 'basics',
                'title' => 'Admin entrance',
                'type' => 'string',
                'value' => '/admin',
                'rule' => 'required',
                'sort' => 1,
            ],
        ];

        $insert = [];
        foreach ($rows as $row) {
            if ($this->configRowExists($row['name'])) {
                $this->updateConfigMetadata($row, $defaults, $now);
                continue;
            }
            $insert[] = array_merge($defaults, $row);
        }

        if ($insert) {
            $this->table('config')->insert($insert)->saveData();
        }
    }

    private function configRowExists(string $name): bool
    {
        $adapter = $this->getAdapter();
        $stmt = $this->query(sprintf(
            'SELECT id FROM %s WHERE %s = ?',
            $adapter->quoteTableName('config'),
            $adapter->quoteColumnName('name')
        ), [$name]);

        return is_object($stmt) && method_exists($stmt, 'fetch') && (bool) $stmt->fetch();
    }

    private function updateConfigMetadata(array $row, array $defaults, string $now): void
    {
        $data = array_merge($defaults, $row, ['update_time' => $now]);
        unset($data['name'], $data['value'], $data['create_time']);

        $adapter = $this->getAdapter();
        $sets = [];
        $params = [];
        foreach ($data as $field => $value) {
            $sets[] = $adapter->quoteColumnName($field) . ' = ?';
            $params[] = $value;
        }
        $params[] = $row['name'];

        $this->execute(sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            $adapter->quoteTableName('config'),
            implode(', ', $sets),
            $adapter->quoteColumnName('name')
        ), $params);
    }

    private function resetPostgresSequences(): void
    {
        if (($this->getAdapter()->getOptions()['adapter'] ?? '') !== 'pgsql') {
            return;
        }

        foreach (['admin', 'admin_group', 'admin_rule', 'config', 'attachment', 'attachment_category', 'user'] as $table) {
            if (!$this->hasTable($table)) {
                continue;
            }
            $this->execute(sprintf(
                "SELECT setval(pg_get_serial_sequence('%s', 'id'), COALESCE((SELECT MAX(id) FROM %s), 1), (SELECT COUNT(*) > 0 FROM %s))",
                $table,
                $table,
                $table
            ));
        }
    }
}
