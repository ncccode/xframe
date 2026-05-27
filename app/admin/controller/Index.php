<?php
declare (strict_types=1);

namespace app\admin\controller;

use Throwable;
use support\Response;
use core\utils\ClickCaptcha;
use Webman\Config;
use think\facade\Validate;
use core\service\Token;
use app\admin\model\AdminLog;
use app\common\controller\Admin;

class Index extends Admin
{
    protected array $noNeedLogin      = ['logout', 'login'];
    protected array $noNeedPermission = ['index'];

    /**
     * 后台初始化请求
     * @return void
     * @throws Throwable
     */
    public function index(): Response
    {
        $adminInfo          = $this->auth->getInfo();
        $adminInfo['super'] = $this->auth->isSuperAdmin();
        unset($adminInfo['token'], $adminInfo['refresh_token']);

        $menus = $this->auth->getMenus();
        if (!$menus) {
            return $this->error(__('No background menu, please contact super administrator!'));
        }
        return $this->success('', [
            'adminInfo'  => $adminInfo,
            'menus'      => $menus,
            'siteConfig' => [
                'siteName'     => \get_sys_config('site_name'),
                'version'      => \get_sys_config('version'),
                'apiUrl'       => Config::get('xframe.api_url'),
                'upload'       => \keys_to_camel_case(\get_upload_config(), ['max_size', 'save_name', 'allowed_suffixes', 'allowed_mime_types']),
                'cdnUrl'       => \full_url(),
                'cdnUrlParams' => Config::get('xframe.cdn_url_params'),
            ]
        ]);
    }

    /**
     * 管理员登录
     * @return void
     * @throws Throwable
     */
    public function login(): Response
    {
        // 检查登录态
        if ($this->auth->isLogin()) {
            return $this->success(__('You have already logged in. There is no need to log in again~'), [
                'type' => $this->auth::LOGGED_IN
            ], $this->auth::LOGIN_RESPONSE_CODE);
        }

        $captchaSwitch = Config::get('xframe.admin_login_captcha');

        // 检查提交
        if ($this->request->isPost()) {
            $username = $this->request->post('username');
            $password = $this->request->post('password');
            $keep     = $this->request->post('keep');

            $rule = [
                'username|' . __('Username') => 'require|length:3,30',
                'password|' . __('Password') => 'require|regex:^(?!.*[&<>"\'\n\r]).{6,32}$',
            ];
            $data = [
                'username' => $username,
                'password' => $password,
            ];
            if ($captchaSwitch) {
                $rule['captchaId|' . __('CaptchaId')] = 'require';
                $rule['captchaInfo|' . __('Captcha')] = 'require';

                $data['captchaId']   = $this->request->post('captchaId');
                $data['captchaInfo'] = $this->request->post('captchaInfo');
            }
            $validate = Validate::rule($rule);
            if (!$validate->check($data)) {
                return $this->error($validate->getError());
            }

            if ($captchaSwitch) {
                $captchaObj = new ClickCaptcha();
                if (!$captchaObj->check($data['captchaId'], $data['captchaInfo'])) {
                    return $this->error(__('Captcha error'));
                }
            }

            AdminLog::instance()->setTitle(__('Login'));

            $res = $this->auth->login($username, $password, (bool)$keep);
            if ($res === true) {
                return $this->success(__('Login succeeded!'), [
                    'userInfo' => $this->auth->getInfo()
                ]);
            } else {
                $msg = $this->auth->getError();
                $msg = $msg ?: __('Incorrect user name or password!');
                return $this->error($msg);
            }
        }

        return $this->success('', [
            'captcha' => $captchaSwitch
        ]);
    }

    /**
     * 管理员注销
     * @return void
     */
    public function logout(): Response
    {
        if ($this->request->isPost()) {
            $refreshToken = $this->request->post('refreshToken', '');
            if ($refreshToken) (new Token())->delete((string)$refreshToken);
            $this->auth->logout();
            return $this->success();
        }

        return $this->error(__('Parameter error'));
    }
}
