<?php

namespace app\common\exception;

use Throwable;
use support\exception\Handler as BaseHandler;

class Handler extends BaseHandler
{
    public function report(Throwable $exception)
    {
        if (!$this->shouldntReport($exception) && function_exists('log_err')) {
            \log_err($exception);
        }

        parent::report($exception);
    }
}
