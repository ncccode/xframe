<?php

namespace app\common\middleware;

use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use app\admin\model\AdminLog as AdminLogModel;

class AdminLog implements MiddlewareInterface
{
    /**
     * 写入管理日志
     * @throws Throwable
     */
    public function process(Request $request, callable $handler): Response
    {
        $response = $handler($request);
        if (in_array(strtoupper($request->method()), ['POST', 'DELETE'], true) && (bool) config('xframe.auto_write_admin_log', false)) {
            AdminLogModel::instance()->record();
        }
        return $response;
    }
}
