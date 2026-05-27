<?php

namespace app\admin\controller\system;

use Throwable;
use support\Response;
use app\common\controller\Admin;
use app\common\model\AttachmentCategory as AttachmentCategoryModel;

class AttachmentCategory extends Admin
{
    /**
     * @var object
     * @phpstan-var AttachmentCategoryModel
     */
    protected object $model;

    protected string|array $quickSearchField = 'name';

    protected string|array $defaultSortField = 'sort,desc';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new AttachmentCategoryModel();
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
        $mediaType = (string)$this->request->input('media_type', '');
        if (in_array($mediaType, ['image', 'video', 'file'], true)) {
            $where[] = ['attachment_category.media_type', '=', $mediaType];
        }

        $res = $this->model
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
