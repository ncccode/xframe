<?php

use Phinx\Migration\AbstractMigration;

final class CreateXframeTable extends AbstractMigration
{
    public function change(): void
    {
        $this->admin();
        $this->adminGroup();
        $this->adminGroupAccess();
        $this->adminRule();
        $this->adminLog();
        $this->token();
        $this->captcha();
        $this->dataexport();
        $this->config();
        $this->attachmentCategory();
        $this->attachment();
        $this->user();
    }

    private function admin(): void
    {
        if ($this->hasTable('admin')) {
            return;
        }

        $this->table('admin')
            ->addColumn('username', 'string', ['limit' => 50, 'null' => false])
            ->addColumn('nickname', 'string', ['limit' => 80, 'default' => '', 'null' => false])
            ->addColumn('avatar', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('email', 'string', ['limit' => 120, 'default' => '', 'null' => false])
            ->addColumn('mobile', 'string', ['limit' => 30, 'default' => '', 'null' => false])
            ->addColumn('password', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('motto', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '状态:1=启用,0=禁用', 'null' => false])
            ->addColumn('login_failure', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('last_login_time', 'biginteger', ['null' => true])
            ->addColumn('last_login_ip', 'string', ['limit' => 80, 'default' => '', 'null' => false])
            ->addColumn('create_time', 'datetime', ['null' => true])
            ->addColumn('update_time', 'datetime', ['null' => true])
            ->addIndex(['username'], ['unique' => true])
            ->create();
    }

    private function adminGroup(): void
    {
        if ($this->hasTable('admin_group')) {
            return;
        }

        $this->table('admin_group')
            ->addColumn('pid', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('rules', 'text', ['null' => true])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '状态:1=启用,0=禁用', 'null' => false])
            ->addColumn('sort', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('create_time', 'datetime', ['null' => true])
            ->addColumn('update_time', 'datetime', ['null' => true])
            ->create();
    }

    private function adminGroupAccess(): void
    {
        if ($this->hasTable('admin_group_access')) {
            return;
        }

        $this->table('admin_group_access', ['id' => false])
            ->addColumn('uid', 'integer', ['null' => false])
            ->addColumn('group_id', 'integer', ['null' => false])
            ->addIndex(['uid'])
            ->addIndex(['group_id'])
            ->create();
    }

    private function adminRule(): void
    {
        if ($this->hasTable('admin_rule')) {
            return;
        }

        $this->table('admin_rule')
            ->addColumn('pid', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('type', 'string', ['limit' => 20, 'default' => 'menu', 'null' => false])
            ->addColumn('title', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 120, 'null' => false])
            ->addColumn('path', 'string', ['limit' => 160, 'default' => '', 'null' => false])
            ->addColumn('icon', 'string', ['limit' => 80, 'default' => '', 'null' => false])
            ->addColumn('menu_type', 'string', ['limit' => 20, 'default' => 'tab', 'null' => false])
            ->addColumn('url', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('component', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('keepalive', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('extend', 'string', ['limit' => 40, 'default' => 'none', 'null' => false])
            ->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('sort', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '状态:1=启用,0=禁用', 'null' => false])
            ->addColumn('create_time', 'datetime', ['null' => true])
            ->addColumn('update_time', 'datetime', ['null' => true])
            ->addIndex(['name'], ['unique' => true])
            ->addIndex(['pid'])
            ->create();
    }

    private function adminLog(): void
    {
        if ($this->hasTable('admin_log')) {
            return;
        }

        $this->table('admin_log')
            ->addColumn('admin_id', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('username', 'string', ['limit' => 50, 'default' => '', 'null' => false])
            ->addColumn('url', 'string', ['limit' => 1500, 'default' => '', 'null' => false])
            ->addColumn('title', 'string', ['limit' => 120, 'default' => '', 'null' => false])
            ->addColumn('data', 'text', ['null' => true])
            ->addColumn('ip', 'string', ['limit' => 80, 'default' => '', 'null' => false])
            ->addColumn('useragent', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('create_time', 'datetime', ['null' => true])
            ->addIndex(['admin_id'])
            ->create();
    }

    private function token(): void
    {
        if ($this->hasTable('token')) {
            return;
        }

        $this->table('token')
            ->addColumn('token', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('type', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('expire_time', 'biginteger', ['null' => false])
            ->addColumn('create_time', 'biginteger', ['null' => true])
            ->addIndex(['token'], ['unique' => true])
            ->addIndex(['type', 'user_id'])
            ->create();
    }

    private function captcha(): void
    {
        if ($this->hasTable('captcha')) {
            return;
        }

        $this->table('captcha', [
                'id'          => false,
                'primary_key' => 'key',
            ])
            ->addColumn('key', 'string', ['limit' => 32, 'default' => '', 'null' => false])
            ->addColumn('code', 'string', ['limit' => 32, 'default' => '', 'null' => false])
            ->addColumn('captcha', 'text', ['null' => true])
            ->addColumn('create_time', 'biginteger', ['null' => true])
            ->addColumn('expire_time', 'biginteger', ['null' => true])
            ->create();
    }

    private function dataexport(): void
    {
        if ($this->hasTable('dataexport')) {
            return;
        }

        $this->table('dataexport')
            ->addColumn('admin_id', 'integer', ['default' => 0, 'null' => false, 'comment' => '导出人'])
            ->addColumn('name', 'string', ['limit' => 100, 'default' => '', 'null' => false, 'comment' => '任务名称'])
            ->addColumn('columns', 'text', ['null' => true, 'comment' => '字段配置'])
            ->addColumn('memory_limit', 'integer', ['default' => 128, 'null' => false, 'comment' => '脚本内存限制(MB)'])
            ->addColumn('xls_max_number', 'integer', ['default' => 10000, 'null' => false, 'comment' => '单个xlsx最大记录数'])
            ->addColumn('export_number', 'integer', ['default' => 0, 'null' => false, 'comment' => '导出记录数'])
            ->addColumn('subtask', 'text', ['null' => true, 'comment' => '子任务数据'])
            ->addColumn('subtask_count', 'integer', ['default' => 1, 'null' => false, 'comment' => '任务数'])
            ->addColumn('progress', 'integer', ['default' => 0, 'null' => false, 'comment' => '导出进度'])
            ->addColumn('file', 'string', ['limit' => 255, 'default' => '', 'null' => false, 'comment' => '导出文件'])
            ->addColumn('export_time', 'datetime', ['null' => true, 'comment' => '导出时间'])
            ->addColumn('create_time', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['admin_id'])
            ->create();
    }

    private function config(): void
    {
        if ($this->hasTable('config')) {
            return;
        }

        $this->table('config')
            ->addColumn('name', 'string', ['limit' => 80, 'null' => false])
            ->addColumn('group', 'string', ['limit' => 50, 'default' => 'basics', 'null' => false])
            ->addColumn('title', 'string', ['limit' => 120, 'default' => '', 'null' => false])
            ->addColumn('tip', 'string', ['limit' => 100, 'default' => '', 'null' => false])
            ->addColumn('type', 'string', ['limit' => 40, 'default' => 'string', 'null' => false])
            ->addColumn('value', 'text', ['null' => true])
            ->addColumn('content', 'text', ['null' => true])
            ->addColumn('rule', 'string', ['limit' => 100, 'default' => '', 'null' => false])
            ->addColumn('extend', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('allow_del', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('sort', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('create_time', 'datetime', ['null' => true])
            ->addColumn('update_time', 'datetime', ['null' => true])
            ->addIndex(['name'], ['unique' => true])
            ->create();
    }

    private function attachmentCategory(): void
    {
        if ($this->hasTable('attachment_category')) {
            return;
        }

        $this->table('attachment_category')
            ->addColumn('name', 'string', ['limit' => 80, 'default' => '', 'null' => false])
            ->addColumn('media_type', 'string', ['limit' => 20, 'default' => 'image', 'null' => false])
            ->addColumn('sort', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('create_time', 'biginteger', ['null' => true, 'default' => null])
            ->addColumn('update_time', 'biginteger', ['null' => true, 'default' => null])
            ->addIndex(['media_type'])
            ->create();
    }

    private function attachment(): void
    {
        if ($this->hasTable('attachment')) {
            return;
        }

        $this->table('attachment')
            ->addColumn('topic', 'string', ['limit' => 20, 'default' => '', 'null' => false])
            ->addColumn('category_id', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('media_type', 'string', ['limit' => 20, 'default' => 'file', 'null' => false])
            ->addColumn('admin_id', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('user_id', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('url', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('width', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('height', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 120, 'default' => '', 'null' => false])
            ->addColumn('size', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('mimetype', 'string', ['limit' => 100, 'default' => '', 'null' => false])
            ->addColumn('quote', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('storage', 'string', ['limit' => 50, 'default' => '', 'null' => false])
            ->addColumn('sha1', 'string', ['limit' => 40, 'default' => '', 'null' => false])
            ->addColumn('create_time', 'biginteger', ['null' => true, 'default' => null])
            ->addColumn('last_upload_time', 'biginteger', ['null' => true, 'default' => null])
            ->addIndex(['category_id'])
            ->addIndex(['media_type'])
            ->addIndex(['admin_id'])
            ->addIndex(['user_id'])
            ->addIndex(['sha1', 'topic', 'storage'])
            ->create();
    }

    private function user(): void
    {
        if ($this->hasTable('user')) {
            return;
        }

        $this->table('user')
            ->addColumn('group_id', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('username', 'string', ['limit' => 50, 'default' => '', 'null' => false])
            ->addColumn('nickname', 'string', ['limit' => 80, 'default' => '', 'null' => false])
            ->addColumn('email', 'string', ['limit' => 120, 'default' => '', 'null' => false])
            ->addColumn('mobile', 'string', ['limit' => 30, 'default' => '', 'null' => false])
            ->addColumn('avatar', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('gender', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('birthday', 'date', ['null' => true])
            ->addColumn('money', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('score', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('last_login_time', 'biginteger', ['null' => true, 'default' => null])
            ->addColumn('last_login_ip', 'string', ['limit' => 80, 'default' => '', 'null' => false])
            ->addColumn('login_failure', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('join_ip', 'string', ['limit' => 80, 'default' => '', 'null' => false])
            ->addColumn('join_time', 'biginteger', ['null' => true, 'default' => null])
            ->addColumn('motto', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('password', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('salt', 'string', ['limit' => 30, 'default' => '', 'null' => false])
            ->addColumn('status', 'integer', ['default' => 1, 'comment' => '状态:1=启用,0=禁用', 'null' => false])
            ->addColumn('create_time', 'datetime', ['null' => true])
            ->addColumn('update_time', 'datetime', ['null' => true])
            ->addIndex(['username'], ['unique' => true])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['mobile'], ['unique' => true])
            ->create();
    }
}
