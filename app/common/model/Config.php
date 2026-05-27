<?php

namespace app\common\model;

use Throwable;
use think\Model;
use app\admin\model\Config as AdminConfigModel;

class Config extends Model
{
    /**
     * 添加系统配置分组
     * @throws Throwable
     */
    public static function addConfigGroup(string $key, string $value): bool
    {
        return self::addArrayItem('config_group', $key, $value);
    }

    /**
     * 删除系统配置分组
     * @throws Throwable
     */
    public static function removeConfigGroup(string $key): bool
    {
        if (AdminConfigModel::where('group', $key)->find()) {
            return false;
        }

        return self::removeArrayItem('config_group', $key);
    }

    /**
     * 添加系统快捷配置入口
     * @throws Throwable
     */
    public static function addQuickEntrance(string $key, string $value): bool
    {
        return self::addArrayItem('config_quick_entrance', $key, $value);
    }

    /**
     * 删除系统快捷配置入口
     * @throws Throwable
     */
    public static function removeQuickEntrance(string $key): bool
    {
        return self::removeArrayItem('config_quick_entrance', $key);
    }

    /**
     * 为 array 类型配置项添加元素
     * @throws Throwable
     */
    public static function addArrayItem(string $name, string $key, string $value): bool
    {
        $configRow = AdminConfigModel::where('name', $name)->find();
        if (!$configRow) {
            return false;
        }

        $configValue = self::normalizeArrayValue($configRow->value);
        foreach ($configValue as $item) {
            if (($item['key'] ?? null) === $key) {
                return false;
            }
        }

        $configRow->value = array_merge($configValue, [['key' => $key, 'value' => $value]]);
        $configRow->save();
        return true;
    }

    /**
     * 删除 array 类型配置项的一个元素
     * @throws Throwable
     */
    public static function removeArrayItem(string $name, string $key): bool
    {
        $configRow = AdminConfigModel::where('name', $name)->find();
        if (!$configRow) {
            return false;
        }

        $configValue = array_values(array_filter(
            self::normalizeArrayValue($configRow->value),
            static fn($item): bool => !is_array($item) || ($item['key'] ?? null) !== $key
        ));

        $configRow->value = $configValue;
        $configRow->save();
        return true;
    }

    private static function normalizeArrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
