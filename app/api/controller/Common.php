<?php

declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\common\library\Auth as AdminAuth;
use core\service\Token;
use core\utils\Captcha;
use core\utils\ClickCaptcha;
use core\utils\Random;
use support\think\Cache;
use support\Response;
use Webman\Config;

class Common extends BaseController
{
    /**
     * 验证码接口限流，防止被用于批量生成验证码或暴力尝试
     * @param string $action 接口标识
     * @return bool 是否已超限
     */
    private function captchaRateLimited(string $action): bool
    {
        $limit = (int)Config::get('xframe.captcha_rate_limit', 60);
        if ($limit <= 0) {
            return false;
        }

        $cacheKey = 'captcha_rate:' . $action . ':' . md5((string)$this->request->getRealIp());
        $count    = (int)Cache::get($cacheKey);
        if ($count >= $limit) {
            return true;
        }
        Cache::set($cacheKey, $count + 1, 60);
        return false;
    }

    /**
     * 图形验证码
     */
    public function captcha(): Response
    {
        // 旧版图形验证码默认禁用；如确需使用，请在 config/xframe.php 中开启 graphic_captcha
        if (!Config::get('xframe.graphic_captcha', false)) {
            return $this->error(__('Parameter error'), [], 404);
        }

        if ($this->captchaRateLimited('graphic')) {
            return $this->error(__('Too many requests, please try again later'), [], 429);
        }

        $captchaId = (string)$this->request->input('id', '');
        if (strlen($captchaId) > 64 || ($captchaId !== '' && !preg_match('/^[A-Za-z0-9_\-:.]+$/', $captchaId))) {
            return $this->error(__('Parameter error'));
        }

        $config = [
            'codeSet'  => '123456789',
            'fontSize' => 22,
            'useCurve' => false,
            'useNoise' => true,
            'length'   => 4,
            'bg'       => [255, 255, 255],
        ];

        return (new Captcha($config))->entry($captchaId);
    }

    /**
     * 点选验证码
     */
    public function clickCaptcha(): Response
    {
        if ($this->captchaRateLimited('click')) {
            return $this->error(__('Too many requests, please try again later'), [], 429);
        }

        $id = (string)$this->request->input('id', '');
        if ($id === '' || strlen($id) > 64 || !preg_match('/^[A-Za-z0-9_\-:.]+$/', $id)) {
            return $this->error(__('Parameter error'));
        }

        return $this->success('', (new ClickCaptcha())->creat($id));
    }

    /**
     * 点选验证码检查
     */
    public function checkClickCaptcha(): Response
    {
        if ($this->captchaRateLimited('click_check')) {
            return $this->error(__('Too many requests, please try again later'), [], 429);
        }

        $id = (string)$this->request->post('id', '');
        $info = (string)$this->request->post('info', '');
        $unset = filter_var($this->request->post('unset', false), FILTER_VALIDATE_BOOLEAN);

        if ($id === '' || $info === '' || strlen($id) > 64 || strlen($info) > 512) {
            return $this->error(__('Parameter error'));
        }

        if ((new ClickCaptcha())->check($id, $info, $unset)) {
            return $this->success();
        }

        return $this->error(__('Captcha error'));
    }

    /**
     * 刷新 token
     *
     * 不主动删除原 token，由 token 驱动清理过期数据，避免并发请求刷新期间丢失原 token 数据。
     */
    public function refreshToken(): Response
    {
        $refreshToken = (string)$this->request->post('refreshToken', '');
        $tokenService = new Token();
        $refreshTokenData = $refreshToken === '' ? [] : $tokenService->get($refreshToken);

        if (
            !$refreshTokenData
            || ((int)($refreshTokenData['expire_time'] ?? 0) > 0 && (int)$refreshTokenData['expire_time'] < time())
        ) {
            return $this->error(__('Login expired, please login again.'));
        }

        $newToken = Random::uuid();
        $type = (string)($refreshTokenData['type'] ?? '');
        $userId = (int)($refreshTokenData['user_id'] ?? 0);

        $authClass = $this->getAuthClassByRefreshTokenType($type);
        if ($userId <= 0 || !$authClass) {
            return $this->error(__('Login expired, please login again.'));
        }

        $tokenType = $authClass::TOKEN_TYPE;
        $keepTime = $this->getTokenKeepTime($authClass, $tokenType);

        $tokenService->set(
            $newToken,
            $tokenType,
            $userId,
            $keepTime
        );

        return $this->success('', [
            'type'  => $type,
            'token' => $newToken,
        ]);
    }

    /**
     * @return class-string<AdminAuth>|null
     */
    private function getAuthClassByRefreshTokenType(string $refreshTokenType): ?string
    {
        foreach ($this->backendAuthClasses() as $class) {
            if ($refreshTokenType === $class::TOKEN_TYPE . '-refresh') {
                return $class;
            }
        }

        return null;
    }

    /**
     * @param class-string<AdminAuth> $authClass
     */
    private function getTokenKeepTime(string $authClass, string $tokenType): int
    {
        $keys = [];
        foreach ($this->backendApps() as $backendApp) {
            $app = trim((string)($backendApp['app'] ?? $backendApp['name'] ?? ''), '\\/');
            if ($app === '' || $authClass !== 'app\\' . $app . '\\library\\Auth') {
                continue;
            }

            $keys[] = $app . '.' . $tokenType . '_token_keep_time';
            $keys[] = $app . '.token_keep_time';
            break;
        }

        $keys[] = 'xframe.' . $tokenType . '_token_keep_time';
        foreach (array_unique($keys) as $key) {
            $value = Config::get($key, null);
            if ($value !== null && $value !== '') {
                return (int)$value;
            }
        }

        return 86400;
    }

    /**
     * @return array<class-string<AdminAuth>>
     */
    private function backendAuthClasses(): array
    {
        $classes = [AdminAuth::class];
        foreach ($this->backendApps() as $backendApp) {
            $app = trim((string)($backendApp['app'] ?? $backendApp['name'] ?? ''), '\\/');
            if ($app === '') {
                continue;
            }
            $class = 'app\\' . $app . '\\library\\Auth';
            if (class_exists($class) && is_a($class, AdminAuth::class, true)) {
                $classes[] = $class;
            }
        }

        return array_values(array_unique($classes));
    }

    private function backendApps(): array
    {
        $apps = Config::get('xframe.backend_apps', []);
        if (!is_array($apps)) {
            return [];
        }

        $normalized = [];
        $reservedPaths = ['admin', 'api', 'app', 'assets', 'static'];
        foreach ($apps as $name => $appConfig) {
            $name = trim((string)$name);
            if ($name === '' || !is_array($appConfig)) {
                continue;
            }

            $path = '/' . trim((string)($appConfig['path'] ?? $name), '/');
            if (
                !preg_match('#^/[A-Za-z0-9][A-Za-z0-9_-]*$#', $path)
                || in_array(strtolower(trim($path, '/')), $reservedPaths, true)
            ) {
                continue;
            }

            $app = trim((string)($appConfig['app'] ?? $name), '\\/');
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $app)) {
                continue;
            }

            $normalized[$name] = [
                'name' => $name,
                'path' => $path,
                'app'  => $app,
            ];
        }

        return $normalized;
    }
}
