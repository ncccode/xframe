<?php

use Phinx\Migration\AbstractMigration;

final class AddModuleStoreRule extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('admin_rule')) {
            return;
        }
        if ($this->ruleCount() === 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $menuId = $this->ruleId('moduleStore/moduleStore');
        if (!$menuId) {
            $this->table('admin_rule')->insert([[
                'pid' => 0,
                'type' => 'menu',
                'title' => '模块市场',
                'name' => 'moduleStore/moduleStore',
                'path' => 'moduleStore',
                'icon' => 'el-icon-GoodsFilled',
                'menu_type' => 'tab',
                'url' => '',
                'component' => '/src/views/admin/module/index.vue',
                'keepalive' => 1,
                'extend' => 'none',
                'remark' => '',
                'sort' => 90,
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ]])->saveData();
            $menuId = $this->ruleId('moduleStore/moduleStore');
        }

        if (!$menuId) {
            return;
        }

        $buttons = [
            ['查看', 'module/index'],
            ['安装', 'module/install'],
            ['调整状态', 'module/change_state'],
            ['卸载', 'module/uninstall'],
            ['上传安装', 'module/upload'],
            ['完成依赖安装', 'module/dependent_install_complete'],
        ];
        $rows = [];
        foreach ($buttons as [$title, $name]) {
            if ($this->ruleId($name)) {
                continue;
            }
            $rows[] = [
                'pid' => $menuId,
                'type' => 'button',
                'title' => $title,
                'name' => $name,
                'path' => '',
                'icon' => '',
                'menu_type' => 'tab',
                'url' => '',
                'component' => '',
                'keepalive' => 0,
                'extend' => 'none',
                'remark' => '',
                'sort' => 0,
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ];
        }

        if ($rows) {
            $this->table('admin_rule')->insert($rows)->saveData();
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('admin_rule')) {
            return;
        }

        $adapter = $this->getAdapter();
        $table = $adapter->quoteTableName('admin_rule');
        $column = $adapter->quoteColumnName('name');
        $names = [
            'moduleStore/moduleStore',
            'module/index',
            'module/install',
            'module/change_state',
            'module/uninstall',
            'module/upload',
            'module/dependent_install_complete',
        ];
        $placeholders = implode(', ', array_fill(0, count($names), '?'));
        $this->execute("DELETE FROM $table WHERE $column IN ($placeholders)", $names);
    }

    private function ruleId(string $name): int
    {
        $adapter = $this->getAdapter();
        $stmt = $this->query(sprintf(
            'SELECT %s FROM %s WHERE %s = ?',
            $adapter->quoteColumnName('id'),
            $adapter->quoteTableName('admin_rule'),
            $adapter->quoteColumnName('name')
        ), [$name]);

        if (!is_object($stmt) || !method_exists($stmt, 'fetch')) {
            return 0;
        }

        $row = $stmt->fetch();
        return $row ? (int)($row['id'] ?? 0) : 0;
    }

    private function ruleCount(): int
    {
        $adapter = $this->getAdapter();
        $stmt = $this->query(sprintf(
            'SELECT COUNT(*) AS total FROM %s',
            $adapter->quoteTableName('admin_rule')
        ));
        if (!is_object($stmt) || !method_exists($stmt, 'fetch')) {
            return 0;
        }
        $row = $stmt->fetch();
        return $row ? (int)($row['total'] ?? 0) : 0;
    }
}
