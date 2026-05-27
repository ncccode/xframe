<?php

namespace app\admin\controller\system;

use Throwable;
use support\Response;
use app\admin\model\Admin;
use app\common\controller\Admin as AdminController;

class AdminInfo extends AdminController
{
    /**
     * @var object
     * @phpstan-var Admin
     */
    protected object $model;

    protected array|string $preExcludeFields = ['username', 'last_login_time', 'password', 'salt', 'status'];

    protected array $authAllowFields = ['id', 'username', 'nickname', 'avatar', 'email', 'mobile', 'motto', 'last_login_time'];

    protected array $noNeedPermission = ['index', 'edit'];

    public function initialize(): void
    {
        parent::initialize();
        $this->auth->setAllowFields($this->authAllowFields);
        $this->model = $this->auth->getUser();
    }

    public function index(): Response
    {
        return $this->success('', [
            'info' => $this->auth->getInfo(),
        ]);
    }

    /**
     * 编辑个人资料
     * @throws Throwable
     */
    public function edit(): Response
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->input($pk, $this->auth->id);
        $row = $this->model->find($id);
        if (!$row || (int) $row[$pk] !== (int) $this->auth->id) {
            return $this->error(__('Record not found'));
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                return $this->error(__('Parameter %s can not be empty', ['']));
            }

            if (!empty($data['avatar'])) {
                $row->avatar = $data['avatar'];
                if ($row->save()) {
                    return $this->success(__('Avatar modified successfully!'));
                }
            }

            if ($this->modelValidate) {
                try {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    $validate = new $validate();
                    $validate->scene('info')->check($data);
                } catch (Throwable $e) {
                    return $this->error($e->getMessage());
                }
            }

            if (!empty($data['password'])) {
                $this->model->resetPassword($this->auth->id, $data['password']);
            }

            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();
            try {
                $result = $row->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                return $this->error($e->getMessage());
            }

            if ($result !== false) {
                return $this->success(__('Update successful'));
            }
            return $this->error(__('No rows updated'));
        }

        return $this->success('', [
            'row' => $row,
        ]);
    }
}
