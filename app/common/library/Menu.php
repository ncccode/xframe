<?php

namespace app\common\library;

use app\admin\model\AdminRule;
use InvalidArgumentException;
use support\think\Db;
use think\Model;
use Throwable;

/**
 * 菜单规则管理类
 */
class Menu
{
    /**
     * @param array      $menu
     * @param int|string $parent   父级规则 name 或 id
     * @param string     $mode     添加模式: cover=覆盖, rename=重命名, ignore=忽略
     * @param string     $position 位置: admin=后台, user=前台
     * @throws Throwable
     */
    public static function create(array $menu, int|string $parent = 0, string $mode = 'cover', string $position = 'admin'): void
    {
        $model = self::model($position);
        $pid = self::parentId($model, $parent);

        foreach ($menu as $item) {
            if (!self::requiredAttrCheck($item)) {
                continue;
            }

            $children = $item['children'] ?? [];
            unset($item['children']);

            $item = self::normalizeItem($item, $pid);
            $sameOldMenu = $model->where('name', $item['name'])->find();

            if ($sameOldMenu) {
                if ($mode === 'cover') {
                    $sameOldMenu->save($item);
                } elseif ($mode === 'rename') {
                    $count = $model->whereLike('name', $item['name'] . '-CONFLICT-%')->count() + 1;
                    $item['name'] .= '-CONFLICT-' . $count;
                    $item['path'] = ($item['path'] ?? '') . '-CONFLICT-' . $count;
                    $item['title'] .= '-CONFLICT-' . $count;
                    $sameOldMenu = $model->create($item);
                } elseif ($mode === 'ignore') {
                    $sameOldMenu = $model->where('name', $item['name'])->where('pid', $item['pid'])->find();
                    if (!$sameOldMenu) {
                        $sameOldMenu = $model->create($item);
                    }
                }
            } else {
                $sameOldMenu = $model->create($item);
            }

            if ($children) {
                self::create($children, (int)$sameOldMenu['id'], $mode, $position);
            }
        }
    }

    /**
     * @param string|int $id        规则 name 或 id
     * @param bool       $recursion 是否递归删除子级菜单、删除上级空菜单
     * @param string     $position  位置: admin=后台, user=前台
     * @throws Throwable
     */
    public static function delete(string|int $id, bool $recursion = false, string $position = 'admin'): bool
    {
        if (!$id) {
            return true;
        }

        $model = self::model($position);
        $menuRule = $model->where(is_numeric($id) ? 'id' : 'name', $id)->find();
        if (!$menuRule) {
            return true;
        }

        $children = $model->where('pid', (int)$menuRule['id'])->column('id');
        if ($children && !$recursion) {
            return true;
        }

        $ids = self::collectIds($model, (int)$menuRule['id'], $recursion);
        $model->where('id', 'in', $ids)->delete();
        self::removeGroupRules($ids, $position);

        if ((int)$menuRule['pid'] > 0) {
            self::delete((int)$menuRule['pid'], false, $position);
        }

        return true;
    }

    /**
     * @param string|int $id       规则 name 或 id
     * @param string     $position 位置: admin=后台, user=前台
     * @throws Throwable
     */
    public static function enable(string|int $id, string $position = 'admin', bool $recursion = true): bool
    {
        return self::setStatus($id, 1, $position, $recursion);
    }

    /**
     * @param string|int $id       规则 name 或 id
     * @param string     $position 位置: admin=后台, user=前台
     * @throws Throwable
     */
    public static function disable(string|int $id, string $position = 'admin', bool $recursion = true): bool
    {
        return self::setStatus($id, 0, $position, $recursion);
    }

    public static function requiredAttrCheck(array $menu): bool
    {
        foreach (['type', 'title', 'name'] as $attr) {
            if (empty($menu[$attr])) {
                return false;
            }
        }

        return true;
    }

    private static function model(string $position): Model
    {
        if ($position === 'admin') {
            return new AdminRule();
        }

        $class = '\\app\\admin\\model\\UserRule';
        if (class_exists($class)) {
            return new $class();
        }

        throw new InvalidArgumentException('Unsupported menu position: ' . $position);
    }

    private static function parentId(Model $model, int|string $parent): int
    {
        if (!$parent) {
            return 0;
        }

        return (int)$model->where(is_numeric($parent) ? 'id' : 'name', $parent)->value('id');
    }

    private static function normalizeItem(array $item, int $pid): array
    {
        $item['pid'] = isset($item['pid']) ? (int)$item['pid'] : $pid;
        $item['path'] = (string)($item['path'] ?? '');
        $item['icon'] = (string)($item['icon'] ?? '');
        $item['menu_type'] = (string)($item['menu_type'] ?? 'tab');
        $item['url'] = (string)($item['url'] ?? '');
        $item['component'] = (string)($item['component'] ?? '');
        $item['keepalive'] = (int)($item['keepalive'] ?? ($item['type'] === 'menu' ? 1 : 0));
        $item['extend'] = (string)($item['extend'] ?? 'none');
        $item['remark'] = (string)($item['remark'] ?? '');
        $item['sort'] = (int)($item['sort'] ?? ($item['weigh'] ?? 0));
        $item['status'] = (int)($item['status'] ?? 1);
        unset($item['weigh']);

        return $item;
    }

    private static function collectIds(Model $model, int $id, bool $recursion): array
    {
        $ids = [$id];
        if (!$recursion) {
            return $ids;
        }

        $pending = [$id];
        while ($pending) {
            $children = $model->where('pid', 'in', $pending)->column('id');
            $children = array_values(array_map('intval', $children));
            $children = array_values(array_diff($children, $ids));
            if (!$children) {
                break;
            }
            $ids = array_merge($ids, $children);
            $pending = $children;
        }

        return $ids;
    }

    private static function setStatus(string|int $id, int $status, string $position, bool $recursion): bool
    {
        $model = self::model($position);
        $menuRule = $model->where(is_numeric($id) ? 'id' : 'name', $id)->find();
        if (!$menuRule) {
            return false;
        }

        $ids = self::collectIds($model, (int)$menuRule['id'], $recursion);
        $model->where('id', 'in', $ids)->update(['status' => $status]);

        return true;
    }

    private static function removeGroupRules(array $ids, string $position): void
    {
        if ($position !== 'admin' || !$ids) {
            return;
        }

        $ids = array_map('strval', array_map('intval', $ids));
        $groups = Db::name('admin_group')->select()->toArray();
        foreach ($groups as $group) {
            $rules = (string)($group['rules'] ?? '');
            if ($rules === '' || $rules === '*') {
                continue;
            }

            $nextRules = array_values(array_diff(array_filter(explode(',', $rules)), $ids));
            if (implode(',', $nextRules) !== $rules) {
                Db::name('admin_group')->where('id', (int)$group['id'])->update([
                    'rules' => implode(',', $nextRules),
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
