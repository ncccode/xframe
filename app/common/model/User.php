<?php

namespace app\common\model;

use think\Model;

/**
 * 会员公共模型
 */
class User extends Model
{
    protected $autoWriteTimestamp = true;

    public function getAvatarAttr($value): string
    {
        return full_url((string)$value, false, config('xframe.default_avatar'));
    }

    public function setAvatarAttr($value): string
    {
        return $value == full_url('', false, config('xframe.default_avatar')) ? '' : $value;
    }

    public function resetPassword(int|string $uid, string $newPassword): int|User
    {
        return $this->where(['id' => $uid])->update(['password' => hash_password($newPassword), 'salt' => '']);
    }
}
