<?php

namespace app\admin\controller;

use Throwable;
use Webman\Config;
use support\Response;
use core\utils\Exception;
use app\admin\model\AdminLog;
use core\module\Server;
use core\module\Manage;
use app\common\controller\Admin;

class Module extends Admin
{
    protected array $noNeedPermission = ['state', 'dependentInstallComplete'];

    public function index(): Response
    {
        return $this->success('', [
            'installed'   => Server::installedList(root_path() . 'modules' . DIRECTORY_SEPARATOR),
            'sysVersion'  => Config::get('xframe.version'),
            'nuxtVersion' => Server::getNuxtVersion(),
        ]);
    }

    public function state(): Response
    {
        $uid = $this->request->get("uid/s", '');
        if (!$uid) {
            return $this->error(__('Parameter error'));
        }
        return $this->success('', [
            'state' => Manage::instance($uid)->getInstallState()
        ]);
    }

    public function install(): Response
    {
        AdminLog::instance()->setTitle(__('Install module'));
        $uid    = $this->request->param("uid/s", '');
        $token  = $this->request->param("token/s", '');
        $update = $this->request->param("update/b", false);
        if (!$uid) {
            return $this->error(__('Parameter error'));
        }
        if (!$token) {
            return $this->error(__('Please login first'), [], 303);
        }

        try {
            $res = Manage::instance($uid)->install($token, $update);
        } catch (Exception $e) {
            return $this->error(__($e->getMessage()), $e->getData(), $e->getCode());
        } catch (Throwable $e) {
            Server::installRecord($uid, (string)$this->request->param('version/s', ''), 'failed', $e->getMessage(), $token);
            return $this->error(__($e->getMessage()));
        }
        return $this->success('', [
            'data' => $res,
        ]);
    }

    public function dependentInstallComplete(): Response
    {
        $uid = $this->request->get("uid/s", '');
        if (!$uid) {
            return $this->error(__('Parameter error'));
        }
        try {
            Manage::instance($uid)->dependentInstallComplete('all');
        } catch (Exception $e) {
            return $this->error(__($e->getMessage()), $e->getData(), $e->getCode());
        } catch (Throwable $e) {
            return $this->error(__($e->getMessage()));
        }
        return $this->success();
    }

    public function changeState(): Response
    {
        AdminLog::instance()->setTitle(__('Change module state'));
        $uid   = $this->request->post("uid/s", '');
        $state = $this->request->post("state/b", false);
        if (!$uid) {
            return $this->error(__('Parameter error'));
        }
        try {
            $info = Manage::instance($uid)->changeState($state);
        } catch (Exception $e) {
            return $this->error(__($e->getMessage()), $e->getData(), $e->getCode());
        } catch (Throwable $e) {
            return $this->error(__($e->getMessage()));
        }
        return $this->success('', [
            'info' => $info,
        ]);
    }

    public function uninstall(): Response
    {
        AdminLog::instance()->setTitle(__('Unload module'));
        $uid = $this->request->get("uid/s", '');
        if (!$uid) {
            return $this->error(__('Parameter error'));
        }
        try {
            $info = Manage::instance($uid)->uninstall();
        } catch (Exception $e) {
            return $this->error(__($e->getMessage()), $e->getData(), $e->getCode());
        } catch (Throwable $e) {
            return $this->error(__($e->getMessage()));
        }
        return $this->success('', [
            'info' => $info,
        ]);
    }

    public function upload(): Response
    {
        AdminLog::instance()->setTitle(__('Upload install module'));
        $file = $this->request->get("file/s", '');
        if (!$file) {
            return $this->error(__('Parameter error'));
        }

        try {
            $info = Manage::instance()->upload($file);
        } catch (Exception $e) {
            return $this->error(__($e->getMessage()), $e->getData(), $e->getCode());
        } catch (Throwable $e) {
            return $this->error(__($e->getMessage()));
        }
        return $this->success('', [
            'info' => $info
        ]);
    }
}
