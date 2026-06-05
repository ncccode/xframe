<?php

use Phinx\Migration\AbstractMigration;

final class HardenAdminSecurityDefaults extends AbstractMigration
{
    public function up(): void
    {
        require_once dirname(__DIR__, 2) . '/app/functions.php';

        $this->insertAjaxPermissionRules();
        $this->rotateDefaultAdminPassword();
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
            'ajax/get_database_connection_list',
            'ajax/get_table_pk',
            'ajax/get_table_list',
            'ajax/get_table_field_list',
            'ajax/change_terminal_config',
            'ajax/clear_cache',
        ];
        $placeholders = implode(', ', array_fill(0, count($names), '?'));
        $this->execute("DELETE FROM $table WHERE $column IN ($placeholders)", $names);
    }

    private function insertAjaxPermissionRules(): void
    {
        if (!$this->hasTable('admin_rule') || $this->ruleCount() === 0) {
            return;
        }

        $pid = $this->ruleId('system/config') ?: 0;
        $now = date('Y-m-d H:i:s');
        $rules = [
            ['数据库连接列表', 'ajax/get_database_connection_list'],
            ['数据表主键', 'ajax/get_table_pk'],
            ['数据表列表', 'ajax/get_table_list'],
            ['数据表字段列表', 'ajax/get_table_field_list'],
            ['修改终端配置', 'ajax/change_terminal_config'],
            ['清理缓存', 'ajax/clear_cache'],
        ];

        $rows = [];
        foreach ($rules as [$title, $name]) {
            if ($this->ruleId($name)) {
                continue;
            }
            $rows[] = [
                'pid' => $pid,
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

    private function rotateDefaultAdminPassword(): void
    {
        if (!$this->hasTable('admin')) {
            return;
        }

        $adapter = $this->getAdapter();
        $stmt = $this->query(sprintf(
            'SELECT %s, %s FROM %s WHERE %s = 1 AND %s = ?',
            $adapter->quoteColumnName('id'),
            $adapter->quoteColumnName('password'),
            $adapter->quoteTableName('admin'),
            $adapter->quoteColumnName('id'),
            $adapter->quoteColumnName('username')
        ), ['admin']);
        if (!is_object($stmt) || !method_exists($stmt, 'fetch')) {
            return;
        }

        $row = $stmt->fetch();
        if (!$row || !verify_password('123456', (string)($row['password'] ?? ''))) {
            return;
        }

        $newPassword = bin2hex(random_bytes(12));
        $table = $adapter->quoteTableName('admin');
        $passwordColumn = $adapter->quoteColumnName('password');
        $idColumn = $adapter->quoteColumnName('id');
        $updateTimeColumn = $adapter->quoteColumnName('update_time');
        $this->execute(
            "UPDATE $table SET $passwordColumn = ?, $updateTimeColumn = ? WHERE $idColumn = 1",
            [hash_password($newPassword), date('Y-m-d H:i:s')]
        );

        echo PHP_EOL . 'XFrame admin default password was rotated.' . PHP_EOL;
        echo 'New admin password: ' . $newPassword . PHP_EOL . PHP_EOL;
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
