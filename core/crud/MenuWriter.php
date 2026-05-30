<?php

namespace core\crud;

use support\think\Db;
use Throwable;

class MenuWriter
{
    protected array $buttons = [
        'index' => '查看',
        'add' => '添加',
        'edit' => '编辑',
        'del' => '删除',
        'sortable' => '排序',
    ];

    public function plan(array $descriptor, array $names): array
    {
        $menuName = $names['menuName'];
        $items = [
            [
                'type' => 'menu',
                'title' => $descriptor['title'],
                'name' => $menuName,
                'path' => $menuName,
                'component' => '/src/views/' . $descriptor['app'] . '/' . $descriptor['path'] . '/index.vue',
            ],
        ];

        foreach ($this->buttons as $action => $title) {
            $items[] = [
                'type' => 'button',
                'title' => $title,
                'name' => $menuName . '/' . $action,
                'path' => '',
                'component' => '',
            ];
        }

        return $items;
    }

    public function write(array $descriptor, array $names): array
    {
        $menuName = $names['menuName'];
        $now = date('Y-m-d H:i:s');
        $parentId = $this->resolveParentId($descriptor['app'], $descriptor['parent'], $descriptor['path']);
        $ruleTable = $this->ruleTable($descriptor['app']);

        $db = Db::connect();
        $db->startTrans();
        try {
            $menuId = $this->upsertRule([
                'pid' => $parentId,
                'type' => 'menu',
                'title' => $descriptor['title'],
                'name' => $menuName,
                'path' => $menuName,
                'icon' => 'fa fa-circle-o',
                'menu_type' => 'tab',
                'url' => '',
                'component' => '/src/views/' . $descriptor['app'] . '/' . $descriptor['path'] . '/index.vue',
                'keepalive' => 1,
                'extend' => 'none',
                'remark' => '',
                'sort' => 0,
                'status' => 1,
                'create_time' => $now,
                'update_time' => $now,
            ], $ruleTable);

            $namesWritten = [$menuName];
            foreach ($this->buttons as $action => $title) {
                $buttonName = $menuName . '/' . $action;
                $this->upsertRule([
                    'pid' => $menuId,
                    'type' => 'button',
                    'title' => $title,
                    'name' => $buttonName,
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
                ], $ruleTable);
                $namesWritten[] = $buttonName;
            }

            if ($parentId > 0) {
                $this->autoAssignPermission($descriptor['app'], $menuId, $parentId);
            }
            $children = Db::name($ruleTable)->where('pid', $menuId)->select()->toArray();
            foreach ($children as $child) {
                $this->autoAssignPermission($descriptor['app'], (int) $child['id'], $menuId);
            }

            $db->commit();
            return $namesWritten;
        } catch (Throwable $e) {
            $db->rollback();
            throw new CrudException('Unable to write menu rules: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function resolveParentId(string $app, string $parent, string $path): int
    {
        $ruleTable = $this->ruleTable($app);
        if ($parent !== '') {
            $id = Db::name($ruleTable)->where('name', $parent)->value('id');
            if (!$id) {
                throw new CrudException("Parent menu [$parent] was not found in [$ruleTable].");
            }
            return (int) $id;
        }

        $segments = explode('/', $path);
        array_pop($segments);
        $pid = 0;
        $current = [];
        foreach ($segments as $segment) {
            $current[] = $segment;
            $name = implode('/', $current);
            $row = Db::name($ruleTable)->where('name', $name)->find();
            if ($row) {
                $pid = (int) $row['id'];
                continue;
            }
            $pid = $this->upsertRule([
                'pid' => $pid,
                'type' => 'menu_dir',
                'title' => ucwords(str_replace('_', ' ', $segment)),
                'name' => $name,
                'path' => $name,
                'icon' => 'fa fa-folder-o',
                'menu_type' => 'tab',
                'url' => '',
                'component' => '',
                'keepalive' => 0,
                'extend' => 'none',
                'remark' => '',
                'sort' => 0,
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ], $ruleTable);
        }

        return $pid;
    }

    protected function upsertRule(array $data, string $ruleTable): int
    {
        $row = Db::name($ruleTable)->where('name', $data['name'])->find();
        if ($row) {
            $update = $data;
            unset($update['id'], $update['create_time']);
            $update['update_time'] = date('Y-m-d H:i:s');
            Db::name($ruleTable)->where('id', $row['id'])->update($update);
            return (int) $row['id'];
        }

        return (int) Db::name($ruleTable)->insertGetId($data);
    }

    protected function autoAssignPermission(string $app, int $id, int $pid): void
    {
        $groupTable = $this->groupTable($app);
        if (!$this->tableExists($groupTable)) {
            return;
        }
        try {
            $groups = Db::name($groupTable)->where('rules', '<>', '*')->select()->toArray();
        } catch (Throwable) {
            return;
        }
        foreach ($groups as $group) {
            $rules = array_filter(explode(',', (string) $group['rules']));
            if (in_array((string) $pid, $rules, true) && !in_array((string) $id, $rules, true)) {
                $rules[] = (string) $id;
                Db::name($groupTable)->where('id', $group['id'])->update(['rules' => implode(',', $rules)]);
            }
        }
    }

    protected function ruleTable(string $app): string
    {
        return $app === 'admin' ? 'admin_rule' : $app . '_rule';
    }

    protected function groupTable(string $app): string
    {
        return $app === 'admin' ? 'admin_group' : $app . '_group';
    }

    protected function tableExists(string $table): bool
    {
        try {
            return Db::name($table)->limit(1)->fetchSql(false)->count() >= 0;
        } catch (Throwable) {
            return false;
        }
    }
}
