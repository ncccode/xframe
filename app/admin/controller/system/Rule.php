<?php

namespace app\admin\controller\system;

use core\utils\Tree;
use Throwable;
use support\Response;
use app\admin\model\AdminRule;
use app\admin\model\AdminGroup;
use app\common\controller\Admin;

class Rule extends Admin
{
    protected string|array $preExcludeFields = ['create_time', 'update_time'];

    protected string|array $defaultSortField = ['sort' => 'desc'];

    protected string|array $quickSearchField = 'title';

    /**
     * @var object
     * @phpstan-var AdminRule
     */
    protected object $model;

    /**
     * @var Tree
     */
    protected Tree $tree;

    /**
     * 远程select初始化传值
     * @var array
     */
    protected array $initValue;

    /**
     * 搜索关键词
     * @var string
     */
    protected string $keyword;

    /**
     * 是否组装Tree
     * @var bool
     */
    protected bool $assembleTree;

    /**
     * 开启模型验证
     * @var bool
     */
    protected bool $modelValidate = true;

    /**
     * 开启模型场景验证
     * @var bool
     */
    protected bool $modelSceneValidate = true;

    public function initialize(): void
    {
        parent::initialize();

        $this->model        = new AdminRule();
        $this->tree         = Tree::instance();
        $isTree             = $this->request->input('isTree', true);
        $initValue          = $this->request->get('initValue', []);
        $this->initValue    = is_array($initValue) ? $initValue : explode(',', (string) $initValue);
        $this->initValue    = array_filter($this->initValue);
        $this->keyword      = $this->request->input('quickSearch', '');
        $this->assembleTree = $isTree && !$this->initValue; // 有初始化值时不组装树状（初始化出来的值更好看）
    }

    public function index(): Response
    {
        if ($this->request->input('select')) {
            return $this->select();
        }

        return $this->success('', [
            'list'   => $this->getMenus(),
            'remark' => \get_route_remark(),
        ]);
    }

    /**
     * 添加
     */
    public function add(): Response
    {
        if (!$this->auth->isSuperAdmin()) {
            return $this->error(__('You have no permission'));
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                return $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data = $this->excludeFields($data);
            if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                $data[$this->dataLimitField] = $this->auth->id;
            }

            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('add');
                        $validate->check($data);
                    }
                }
                $result = $this->model->save($data);

                // 检查有那些分组可以拥有新增菜单的权限
                if (!empty($data['pid'])) {
                    $this->autoAssignPermission($this->model->id, $data['pid']);
                }

                // 创建子级权限节点
                if ($data['type'] == 'menu' && !empty($data['buttons'])) {
                    $newButtons = [];
                    foreach ($data['buttons'] as $button) {
                        $buttonName = trim((string) $button, '/');
                        $newButtons[] = [
                            'pid'         => $this->model->id,
                            'type'        => 'button',
                            'title'       => __($buttonName),
                            'name'        => rtrim($data['name'], '/') . '/' . $buttonName,
                            'path'        => '',
                            'icon'        => '',
                            'menu_type'   => 'tab',
                            'url'         => '',
                            'component'   => '',
                            'keepalive'   => 0,
                            'extend'      => 'none',
                            'remark'      => '',
                            'sort'        => 0,
                            'status'      => 1,
                            'create_time' => date('Y-m-d H:i:s'),
                            'update_time' => date('Y-m-d H:i:s'),
                        ];
                    }
                    if (!empty($newButtons)) {
                        // 创建子级权限节点
                        AdminRule::insertAll($newButtons);

                        // 检查有那些分组可以拥有新增的子级权限
                        $children = AdminRule::where('pid', $this->model->id)->select();
                        foreach ($children as $child) {
                            $this->autoAssignPermission($child['id'], $this->model->id);
                        }
                    }
                }

                $this->model->commit();
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
        $id  = $this->request->input($this->model->getPk());
        $row = $this->model->find($id);
        if (!$row) {
            return $this->error(__('Record not found'));
        }

        if ($response = $this->checkRuleAuth((int)$row->id)) {
            return $response;
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

            $data   = $this->excludeFields($data);
            if ($response = $this->guardImmutableRuleFields($data, $row)) {
                return $response;
            }
            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('edit');
                        $validateData = array_merge($row->toArray(), $data);
                        $validateData[$this->model->getPk()] = $row[$this->model->getPk()];
                        $validate->check($validateData);
                    }
                }
                if (isset($data['pid']) && $data['pid'] > 0) {
                    // 满足意图并消除副作用
                    $parent = $this->model->where('id', $data['pid'])->find();
                    if ($parent['pid'] == $row['id']) {
                        $parent->pid = 0;
                        $parent->save();
                    }
                }
                $result = $row->save($data);
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
        $ids = $this->normalizeRuleIds($this->request->input('ids', []));
        if (!$ids) {
            return $this->error(__('No rows were deleted'));
        }

        foreach ($ids as $id) {
            if ($response = $this->checkRuleAuth($id)) {
                return $response;
            }
        }

        // 子级元素检查
        $subData = $this->model->where('pid', 'in', $ids)->column('pid', 'id');
        foreach ($subData as $key => $subDatum) {
            if (!in_array($key, $ids)) {
                return $this->error(__('Please delete the child element first, or use batch deletion'));
            }
        }

        return parent::del();
    }

    /**
     * 重写select方法
     * @throws Throwable
     */
    public function select(): Response
    {
        $data = $this->getMenus([['type', 'in', ['menu_dir', 'menu']], ['status', '=', 1]]);

        if ($this->assembleTree) {
            $data = $this->tree->assembleTree($this->tree->getTreeArray($data, 'title'));
        }
        return $this->success('', [
            'options' => $data
        ]);
    }

    /**
     * 获取菜单列表
     * @throws Throwable
     */
    protected function getMenus($where = []): array
    {
        $pk      = $this->model->getPk();
        $initKey = $this->request->get("initKey", $pk);

        $ids = $this->auth->getRuleIds();

        // 如果没有 * 则只获取用户拥有的规则
        if (!in_array('*', $ids)) {
            $where[] = ['id', 'in', $ids];
        }

        if ($this->keyword) {
            $keyword = explode(' ', $this->keyword);
            foreach ($keyword as $item) {
                $where[] = [$this->quickSearchField, 'like', '%' . $item . '%'];
            }
        }

        if ($this->initValue) {
            $where[] = [$initKey, 'in', $this->initValue];
        }

        // 读取用户组所有权限规则
        $rules = $this->model
            ->where($where)
            ->order($this->queryOrderBuilder())
            ->select()
            ->toArray();

        // 如果要求树状，此处先组装好 children
        return $this->assembleTree ? $this->tree->assembleChild($rules) : $rules;
    }

    /**
     * 非超管只能操作自己已经拥有的权限规则。
     */
    private function checkRuleAuth(int $ruleId): ?Response
    {
        if ($this->auth->isSuperAdmin()) {
            return null;
        }

        $ownedRuleIds = array_map('intval', $this->auth->getRuleIds());
        if (!in_array($ruleId, $ownedRuleIds, true)) {
            return $this->error(__('You have no permission'));
        }

        return null;
    }

    /**
     * 非超管不可修改权限节点身份字段，避免把已有节点改造成高权限节点。
     */
    private function guardImmutableRuleFields(array &$data, AdminRule $row): ?Response
    {
        if ($this->auth->isSuperAdmin()) {
            return null;
        }

        foreach (['name', 'type', 'pid'] as $field) {
            if (array_key_exists($field, $data) && (string)$data[$field] !== (string)$row[$field]) {
                return $this->error(__('You have no permission'));
            }
            unset($data[$field]);
        }

        return null;
    }

    /**
     * 规范化批量操作 ID。
     */
    private function normalizeRuleIds(mixed $ids): array
    {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    }

    /**
     * 检查所有非超管的分组是否应该拥有某个权限
     */
    private function autoAssignPermission(int $id, int $pid): void
    {
        $groups = AdminGroup::where('rules', '<>', '*')->select();
        foreach ($groups as $group) {
            $rules = explode(',', $group->rules);
            if (in_array($pid, $rules) && !in_array($id, $rules)) {
                $rules[]      = $id;
                $group->rules = implode(',', $rules);
                $group->save();
            }
        }
    }
}
