<?php

namespace app\admin\controller\system;

use Throwable;
use support\Response;
use app\common\model\Attachment as AttachmentModel;
use app\common\controller\Admin as AdminController;

class Attachment extends AdminController
{
    /**
     * @var object
     * @phpstan-var AttachmentModel
     */
    protected object $model;

    protected string|array $preExcludeFields = [
        'url',
        'storage',
        'sha1',
        'topic',
        'quote',
        'admin_id',
        'user_id',
        'last_upload_time',
        'create_time',
        'update_time',
    ];

    protected string|array $quickSearchField = 'name';

    protected array $withJoinTable = ['admin', 'user'];

    protected string|array $defaultSortField = 'last_upload_time,desc';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new AttachmentModel();
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

        [$where, $alias, $limit, $order] = $this->queryBuilder();
        $mainAlias = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', basename(str_replace('\\', '/', get_class($this->model))))) . '.';

        $categoryId = $this->request->input('category_id', null);
        if ($categoryId !== null && $categoryId !== '') {
            $where[] = [$mainAlias . 'category_id', '=', (int)$categoryId];
        }

        $mediaType = (string)$this->request->input('media_type', '');
        if (in_array($mediaType, ['image', 'video', 'file'], true)) {
            $where[] = [$mainAlias . 'media_type', '=', $mediaType];
        }

        $name = (string)$this->request->input('name', '');
        if ($name !== '') {
            $where[] = [$mainAlias . 'name', 'LIKE', '%' . str_replace('%', '\%', $name) . '%'];
        }

        $res = $this->model
            ->field($this->indexField)
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
     * 移动附件分组
     */
    public function move(): Response
    {
        $ids = $this->request->input('ids', []);
        if (is_string($ids)) {
            $ids = array_filter(explode(',', $ids));
        }
        $categoryId = (int)$this->request->input('category_id', $this->request->input('cid', 0));

        if (!$ids) {
            return $this->error(__('No rows updated'));
        }

        $result = $this->model
            ->where($this->model->getPk(), 'in', $ids)
            ->update(['category_id' => max(0, $categoryId)]);

        if ($result !== false) {
            return $this->success(__('Update successful'));
        }
        return $this->error(__('No rows updated'));
    }

    /**
     * 删除附件记录和物理文件
     * @throws Throwable
     */
    public function del(): Response
    {
        $where             = [];
        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds) {
            $where[] = [$this->dataLimitField, 'in', $dataLimitAdminIds];
        }

        $ids = $this->request->input('ids', []);
        if (is_string($ids)) {
            $ids = array_filter(explode(',', $ids));
        }
        if (!$ids) {
            return $this->error(__('No rows were deleted'));
        }

        $where[] = [$this->model->getPk(), 'in', $ids];
        $data    = $this->model->where($where)->select();

        $count = 0;
        try {
            foreach ($data as $item) {
                $count += $item->delete();
            }
        } catch (Throwable $e) {
            return $this->error(__('%d records and files have been deleted', [$count]) . $e->getMessage());
        }

        if ($count) {
            return $this->success(__('%d records and files have been deleted', [$count]));
        }
        return $this->error(__('No rows were deleted'));
    }
}
