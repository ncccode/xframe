<?php

namespace app\admin\model;

use think\Model;

/**
 * AdminRule 模型
 * @property int $status 状态:1=启用,0=禁用
 */
class AdminRule extends Model
{
    protected $autoWriteTimestamp = true;

    public function setComponentAttr($value)
    {
        if ($value) $value = str_replace('\\', '/', $value);
        return $value;
    }

}
