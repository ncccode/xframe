<?php

namespace app\common\model;

use think\Model;

/**
 * 附件分组模型
 */
class AttachmentCategory extends Model
{
    protected $autoWriteTimestamp = true;

    protected static function onAfterDelete($model): void
    {
        Attachment::where('category_id', $model->id)->update(['category_id' => 0]);
    }
}
