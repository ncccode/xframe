<?php

namespace core\utils;

class Random
{
    /**
     * 获取全球唯一标识
     * @return string
     */
    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20)
        );
    }

    /**
     * 随机字符生成
     * @param string $type 类型 alpha/alnum/numeric/noZero/unique/md5/encrypt/sha1
     * @param int    $len  长度
     * @return string
     */
    public static function build(string $type = 'alnum', int $len = 8): string
    {
        switch ($type) {
            case 'alpha':
            case 'alnum':
            case 'numeric':
            case 'noZero':
                $pool = match ($type) {
                    'alpha' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
                    'alnum' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
                    'numeric' => '0123456789',
                    'noZero' => '123456789',
                    default => '',
                };
                $value = '';
                $poolLength = strlen($pool);
                for ($i = 0; $i < $len; $i++) {
                    $value .= $pool[random_int(0, $poolLength - 1)];
                }
                return $value;
            case 'unique':
            case 'md5':
                return bin2hex(random_bytes(16));
            case 'encrypt':
            case 'sha1':
                return hash('sha256', random_bytes(32));
        }
        return '';
    }

}
