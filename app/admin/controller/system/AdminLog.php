<?php

namespace app\admin\controller\system;

use Throwable;
use support\Response;
use app\common\controller\Admin;
use app\admin\model\AdminLog as AdminLogModel;

class AdminLog extends Admin
{
    /**
     * @var object
     * @phpstan-var AdminLogModel
     */
    protected object $model;

    protected string|array $preExcludeFields = ['create_time', 'admin_id', 'username'];

    protected string|array $quickSearchField = ['title'];

    protected array $noNeedPermission = ['index'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new AdminLogModel();
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
        if (!$this->auth->isSuperAdmin()) {
            $where[] = ['admin_id', '=', $this->auth->id];
        }
        $res = $this->model
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
}
