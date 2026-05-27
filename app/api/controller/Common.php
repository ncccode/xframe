<?php

declare(strict_types=1);

namespace app\api\controller;

use app\BaseController;
use app\common\library\Auth as AdminAuth;
use core\service\Token;
use core\utils\Captcha;
use core\utils\ClickCaptcha;
use core\utils\Random;
use support\Response;
use Webman\Config;

class Common extends BaseController
{
    /**
     * 图形验证码
     */
    public function captcha(): Response
    {
        $captchaId = (string)$this->request->input('id', '');
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
        $id = (string)$this->request->input('id', '');
        if ($id === '') {
            return $this->error(__('Parameter error'));
        }

        return $this->success('', (new ClickCaptcha())->creat($id));
    }

    /**
     * 点选验证码检查
     */
    public function checkClickCaptcha(): Response
    {
        $id = (string)$this->request->post('id', '');
        $info = (string)$this->request->post('info', '');
        $unset = filter_var($this->request->post('unset', false), FILTER_VALIDATE_BOOLEAN);

        if ($id === '' || $info === '') {
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

        if ($type !== AdminAuth::TOKEN_TYPE . '-refresh' || $userId <= 0) {
            return $this->error(__('Login expired, please login again.'));
        }

        $tokenService->set(
            $newToken,
            AdminAuth::TOKEN_TYPE,
            $userId,
            (int)Config::get('xframe.admin_token_keep_time', 86400)
        );

        return $this->success('', [
            'type'  => $type,
            'token' => $newToken,
        ]);
    }
}
