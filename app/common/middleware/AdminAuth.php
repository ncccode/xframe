<?php

namespace app\common\middleware;

use ReflectionClass;
use Throwable;
use Webman\Event\Event;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use app\common\library\Auth;
use core\service\token\TokenExpirationException;

class AdminAuth implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $controller = (string) $request->controller;
        if (!$controller || !is_a($controller, \app\common\controller\Admin::class, true)) {
            return $handler($request);
        }

        $properties       = (new ReflectionClass($controller))->getDefaultProperties();
        $noNeedLogin      = $properties['noNeedLogin'] ?? [];
        $noNeedPermission = $properties['noNeedPermission'] ?? [];
        $action           = strtolower((string) $request->action);

        $actionIn = static function (array|string $actions) use ($action): bool {
            $actions = is_array($actions) ? $actions : explode(',', $actions);
            $actions = array_map(static fn($item) => strtolower(trim((string) $item)), $actions);
            return in_array('*', $actions, true) || in_array($action, $actions, true);
        };

        $needLogin = !$actionIn($noNeedLogin);
        $auth      = Auth::instance();

        try {
            $token = (string) (
                $request->header('xtoken')
                ?: $request->header('x-token')
                ?: \get_auth_token()
            );
            if ($token) {
                $auth->init($token);
            }
        } catch (TokenExpirationException) {
            if ($needLogin) {
                return json([
                    'code' => 409,
                    'msg'  => __('Token expiration'),
                    'time' => time(),
                    'data' => [],
                ]);
            }
        }

        if ($needLogin) {
            if (!$auth->isLogin()) {
                return json([
                    'code' => $auth::LOGIN_RESPONSE_CODE,
                    'msg'  => __('Please login first'),
                    'time' => time(),
                    'data' => [
                        'type' => $auth::NEED_LOGIN,
                    ],
                ]);
            }

            if (!$actionIn($noNeedPermission)) {
                $controllerPath = preg_replace('/^app\\\\admin\\\\controller\\\\/i', '', $controller);
                $controllerPath = \admin_controller_rule_path((string) $controllerPath);
                $actionPath = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', (string) $request->action));

                if (!$auth->check(trim($controllerPath . '/' . $actionPath, '/'))) {
                    return json([
                        'code' => 401,
                        'msg'  => __('You have no permission'),
                        'time' => time(),
                        'data' => [],
                    ]);
                }
            }
        }

        Event::emit('adminInit', $auth);

        return $handler($request);
    }
}
