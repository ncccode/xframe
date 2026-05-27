<?php

namespace app\common\model;

use think\Model;

/**
 * 数据导出任务模型
 */
class Dataexport extends Model
{
    protected $name = 'dataexport';

    protected $type = [
        'columns' => 'array',
        'subtask' => 'array',
    ];
}
