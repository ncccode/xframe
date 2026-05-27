<?php

namespace core\utils;

use RuntimeException;

/**
 * xframe 通用异常类
 * catch 到异常后可以直接 $this->error(__($e->getMessage()), $e->getData(), $e->getCode());
 */
class Exception extends RuntimeException
{
    protected array $data;

    public function __construct(string $message, int $code = 0, array $data = [])
    {
        $this->data = $data;
        parent::__construct($message, $code);
    }

    public function getData(): array
    {
        return $this->data;
    }
}
