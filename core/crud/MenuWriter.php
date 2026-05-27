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
                'status' => 'enable',
                'create_time' => $now,
                'update_time' => $now,
            ]);

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
                    'status' => 'enable',
                    'create_time' => $now,
                    'update_time' => $now,
                ]);
                $namesWritten[] = $buttonName;
            }

            if ($parentId > 0) {
                $this->autoAssignPermission($menuId, $parentId);
            }
            $children = Db::name('admin_rule')->where('pid', $menuId)->select()->toArray();
            foreach ($children as $child) {
                $this->autoAssignPermission((int) $child['id'], $menuId);
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
        if ($parent !== '') {
            $names = [$parent];
            if ($app !== 'admin' && !str_starts_with($parent, $app . '/')) {
                array_unshift($names, $app . '/' . $parent);
            }
            $id = Db::name('admin_rule')->whereIn('name', $names)->value('id');
            if (!$id) {
                throw new CrudException("Parent menu [$parent] was not found in admin_rule.");
            }
            return (int) $id;
        }

        $segments = explode('/', $path);
        array_pop($segments);
        $pid = 0;
        $prefix = $app === 'admin' ? '' : $app . '/';
        $current = [];
        foreach ($segments as $segment) {
            $current[] = $segment;
            $name = $prefix . implode('/', $current);
            $row = Db::name('admin_rule')->where('name', $name)->find();
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
                'status' => 'enable',
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);
        }

        return $pid;
    }

    protected function upsertRule(array $data): int
    {
        $row = Db::name('admin_rule')->where('name', $data['name'])->find();
        if ($row) {
            $update = $data;
            unset($update['id'], $update['create_time']);
            $update['update_time'] = date('Y-m-d H:i:s');
            Db::name('admin_rule')->where('id', $row['id'])->update($update);
            return (int) $row['id'];
        }

        return (int) Db::name('admin_rule')->insertGetId($data);
    }

    protected function autoAssignPermission(int $id, int $pid): void
    {
        $groups = Db::name('admin_group')->where('rules', '<>', '*')->select()->toArray();
        foreach ($groups as $group) {
            $rules = array_filter(explode(',', (string) $group['rules']));
            if (in_array((string) $pid, $rules, true) && !in_array((string) $id, $rules, true)) {
                $rules[] = (string) $id;
                Db::name('admin_group')->where('id', $group['id'])->update(['rules' => implode(',', $rules)]);
            }
        }
    }
}
