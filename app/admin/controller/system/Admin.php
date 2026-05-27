<?php

namespace app\admin\controller\system;

use Throwable;
use support\Response;
use support\think\Db;
use app\admin\model\Admin as AdminModel;
use app\common\controller\Admin as AdminController;

class Admin extends AdminController
{
    /**
     * 模型
     * @var object
     * @phpstan-var AdminModel
     */
    protected object $model;

    protected array|string $preExcludeFields = ['create_time', 'update_time', 'password', 'salt', 'login_failure', 'last_login_time', 'last_login_ip'];

    protected array|string $quickSearchField = ['username', 'nickname'];

    /**
     * 开启数据限制
     */
    protected string|int|bool $dataLimit = 'allAuthAndOthers';

    protected string $dataLimitField = 'id';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new AdminModel();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): Response
    {
        if ($this->request->input('select')) {
            return $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->withoutField('login_failure,password,salt')
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        return $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => \get_route_remark(),
        ]);
    }

    /**
     * 添加
     * @throws Throwable
     */
    public function add(): Response
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                return $this->error(__('Parameter %s can not be empty', ['']));
            }

            if ($this->modelValidate) {
                try {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    $validate = new $validate();
                    $validate->scene('add')->check($data);
                } catch (Throwable $e) {
                    return $this->error($e->getMessage());
                }
            }

            $passwd = $data['password']; // 密码将被排除不直接入库
            $data   = $this->excludeFields($data);
            $result = false;
            if ($data['group_arr'] && $response = $this->checkGroupAuth($data['group_arr'])) {
                return $response;
            }
            $this->model->startTrans();
            try {
                $result = $this->model->save($data);
                if ($data['group_arr']) {
                    $groupAccess = [];
                    foreach ($data['group_arr'] as $datum) {
                        $groupAccess[] = [
                            'uid'      => $this->model->id,
                            'group_id' => $datum,
                        ];
                    }
                    Db::name('admin_group_access')->insertAll($groupAccess);
                }
                $this->model->commit();

                if (!empty($passwd)) {
                    $this->model->resetPassword($this->model->id, $passwd);
                }
            } catch (Throwable $e) {
                $this->model->rollback();
                return $this->error($e->getMessage());
            }
            if ($result !== false) {
                return $this->success(__('Added successfully'));
            } else {
                return $this->error(__('No rows were added'));
            }
        }

        return $this->error(__('Parameter error'));
    }

    /**
     * 编辑
     * @throws Throwable
     */
    public function edit(): Response
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->input($pk);
        $row = $this->model->find($id);
        if (!$row) {
            return $this->error(__('Record not found'));
        }

        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds && !in_array($row[$this->dataLimitField], $dataLimitAdminIds)) {
            return $this->error(__('You have no permission'));
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                return $this->error(__('Parameter %s can not be empty', ['']));
            }

            if ($this->modelValidate) {
                try {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    $validate = new $validate();
                    $validate->scene('edit')->check($data);
                } catch (Throwable $e) {
                    return $this->error($e->getMessage());
                }
            }

            if ($this->auth->id == $data['id'] && (int) $data['status'] === 0) {
                return $this->error(__('Please use another administrator account to disable the current account!'));
            }

            if (!empty($data['password'])) {
                $this->model->resetPassword($row->id, $data['password']);
            }

            $groupAccess = [];
            if ($data['group_arr']) {
                $checkGroups = [];
                foreach ($data['group_arr'] as $datum) {
                    if (!in_array($datum, $row->group_arr)) {
                        $checkGroups[] = $datum;
                    }
                    $groupAccess[] = [
                        'uid'      => $id,
                        'group_id' => $datum,
                    ];
                }
                if ($response = $this->checkGroupAuth($checkGroups)) {
                    return $response;
                }
            }

            Db::name('admin_group_access')
                ->where('uid', $id)
                ->delete();

            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();
            try {
                $result = $row->save($data);
                if ($groupAccess) Db::name('admin_group_access')->insertAll($groupAccess);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                return $this->error($e->getMessage());
            }
            if ($result !== false) {
                return $this->success(__('Update successful'));
            } else {
                return $this->error(__('No rows updated'));
            }
        }

        unset($row['salt'], $row['login_failure']);
        $row['password'] = '';
        return $this->success('', [
            'row' => $row
        ]);
    }

    /**
     * 删除
     * @throws Throwable
     */
    public function del(): Response
    {
        $where             = [];
        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds) {
            $where[] = [$this->dataLimitField, 'in', $dataLimitAdminIds];
        }

        $ids     = $this->request->input('ids', []);
        $where[] = [$this->model->getPk(), 'in', $ids];
        $data    = $this->model->where($where)->select();

        $count = 0;
        $this->model->startTrans();
        try {
            foreach ($data as $v) {
                if ($v->id != $this->auth->id) {
                    $count += $v->delete();
                    Db::name('admin_group_access')
                        ->where('uid', $v['id'])
                        ->delete();
                }
            }
            $this->model->commit();
        } catch (Throwable $e) {
            $this->model->rollback();
            return $this->error($e->getMessage());
        }
        if ($count) {
            return $this->success(__('Deleted successfully'));
        } else {
            return $this->error(__('No rows were deleted'));
        }
    }

    /**
     * 检查分组权限
     * @throws Throwable
     */
    private function checkGroupAuth(array $groups): ?Response
    {
        if ($this->auth->isSuperAdmin()) {
            return null;
        }
        $authGroups = $this->auth->getAllAuthGroups('allAuthAndOthers');
        foreach ($groups as $group) {
            if (!in_array($group, $authGroups)) {
                return $this->error(__('You have no permission to add an administrator to this group!'));
            }
        }
        return null;
    }
}
