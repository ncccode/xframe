<?php

namespace core\service\token;

use RuntimeException;

/**
 * Token过期异常
 */
class TokenExpirationException extends RuntimeException
{
    protected array $data;

    public function __construct(string $message = '', int $code = 409, array $data = [])
    {
        $this->data = $data;
        parent::__construct($message, $code);
    }
}
