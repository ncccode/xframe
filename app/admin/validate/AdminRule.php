<?php

namespace app\admin\validate;

use app\admin\model\AdminRule as AdminRuleModel;
use think\Validate;

class AdminRule extends Validate
{
    protected $failException = true;

    protected $rule = [
        'type'  => 'require',
        'title' => 'require',
        'name'  => 'require|regex:/^[A-Za-z0-9_]+(?:\/[A-Za-z0-9_]+)*$/|uniqueRuleName',
    ];

    /**
     * 验证提示信息
     * @var array
     */
    protected $message = [];

    /**
     * 字段描述
     */
    protected $field = [
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add'  => ['type', 'title', 'name'],
        'edit' => ['type', 'title', 'name'],
    ];

    public function __construct()
    {
        $this->field = [
            'type'  => __('type'),
            'title' => __('title'),
            'name'  => __('name'),
        ];
        $this->message = array_merge($this->message, [
            'name.regex' => __('Please input correct rule name'),
        ]);
        parent::__construct();
    }

    protected function uniqueRuleName($value, $rule, array $data = [], string $field = '', string $title = ''): bool|string
    {
        $pk = (new AdminRuleModel())->getPk();
        $query = AdminRuleModel::where('name', (string)$value);
        if (isset($data[$pk]) && (int)$data[$pk] > 0) {
            $query->where($pk, '<>', (int)$data[$pk]);
        }

        return $query->find() ? __('Rule name already exists') : true;
    }
}
