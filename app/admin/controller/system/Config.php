<?php

namespace app\admin\controller\system;

use Throwable;
use support\Response;
use core\utils\Filesystem;
use app\admin\model\Config as ConfigModel;
use app\common\controller\Admin as AdminController;

/**
 * 系统配置管理
 */
class Config extends AdminController
{
    /**
     * 默认展示的配置分组。
     */
    private const DEFAULT_VISIBLE_CONFIG_GROUPS = ['basics'];

    /**
     * 默认配置分组标题，用于保存时还原为原始翻译键。
     */
    private const DEFAULT_CONFIG_GROUP_TITLES = [
        'basics' => 'Basics',
    ];

    /**
     * @var object
     * @phpstan-var ConfigModel
     */
    protected object $model;

    /**
     * 配置变更时需要同步写入的本地文件路径。
     *
     * @var array<string, string>
     */
    protected array $filePath = [
        'webAdminBase' => 'web/src/router/static/adminBase.ts',
    ];

    /**
     * 初始化模型实例
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ConfigModel();
    }

    /**
     * 查看系统配置
     * @throws Throwable
     */
    public function index(): Response
    {
        $configGroup = (array) get_sys_config('config_group');
        $scope = $this->resolveConfigGroupScope($configGroup);
        if ($scope instanceof Response) {
            return $scope;
        }

        $visibleGroups = array_flip($scope['groups']);
        $configGroupList = $this->configGroupsForDisplay($this->filterConfigGroups($configGroup, $scope['groups']));
        $config      = ConfigModel::where('group', 'in', $scope['groups'])->order('sort desc')->select()->toArray();

        $list           = [];
        $newConfigGroup = [];
        foreach ($configGroup ?: [] as $item) {
            if (!is_array($item) || !isset($item['key']) || !isset($visibleGroups[(string) $item['key']])) {
                continue;
            }
            $list[$item['key']]['name']   = $item['key'];
            $list[$item['key']]['title']  = __($item['value']);
            $list[$item['key']]['list']   = [];
            $newConfigGroup[$item['key']] = $list[$item['key']]['title'];
        }
        foreach ($config as $item) {
            if (array_key_exists($item['group'], $newConfigGroup)) {
                if ($item['name'] === 'config_group') {
                    continue;
                }
                $item['title']                  = __($item['title']);
                $list[$item['group']]['list'][] = $item;
            }
        }

        return $this->success('', [
            'list'            => $list,
            'remark'          => get_route_remark(),
            'configGroup'     => $newConfigGroup,
            'configGroupList' => $configGroupList,
            'canManageGroups' => in_array('basics', $scope['groups'], true),
            'permissions'     => [
                'add' => $this->auth->check('system/config/add'),
                'del' => $this->auth->check('system/config/del'),
            ],
        ]);
    }

    /**
     * 编辑系统配置
     * @throws Throwable
     */
    public function edit(): Response
    {
        $configGroup = (array) get_sys_config('config_group');
        $scope = $this->resolveConfigGroupScope($configGroup);
        if ($scope instanceof Response) {
            return $scope;
        }

        $all = ConfigModel::where('group', 'in', $scope['groups'])->select();
        if ($this->request->isPost()) {
            $this->modelValidate = false;
            $data                = $this->request->post();
            if (!$data) {
                return $this->error(__('Parameter %s can not be empty', ['']));
            }

            foreach ($all as $item) {
                if ($item['type'] === 'editor' && isset($data[$item->name]) && is_string($data[$item->name])) {
                    $data[$item->name] = clean_xss($data[$item->name]);
                }
            }

            $data = $this->excludeFields($data);
            $allowedConfigNames = [];
            foreach ($all as $item) {
                $allowedConfigNames[$item->name] = true;
            }
            if (array_diff(array_keys($data), array_keys($allowedConfigNames))) {
                return $this->error(__('You have no permission'));
            }

            if (array_key_exists('config_group', $data)) {
                $data['config_group'] = $this->configGroupsForStorage($data['config_group'], $scope['groups']);
            }

            $configValue = [];
            $adminEntrance = null;
            $oldAdminEntrance = null;
            foreach ($all as $item) {
                if (!array_key_exists($item->name, $data)) {
                    continue;
                }

                $value = $data[$item->name];
                if ($item->name === 'admin_entrance') {
                    $normalizedEntrance = $this->normalizeAdminEntrance((string) $value);
                    if ($normalizedEntrance === null) {
                        return $this->error(__('Admin entrance rule'));
                    }
                    $data[$item->name] = $normalizedEntrance;
                    $value = $normalizedEntrance;
                    $adminEntrance = $normalizedEntrance;
                    $oldAdminEntrance = (string) get_sys_config('admin_entrance');
                }

                $configValue[] = [
                    'id'    => $item->id,
                    'type'  => $item->getData('type'),
                    'value' => $value,
                ];
            }

            $result = false;
            $this->model->startTrans();
            try {
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) {
                            $validate->scene('edit');
                        }
                        $validate->check($data);
                    }
                }
                $result = $this->model->saveAll($configValue);
                if ($result !== false && $adminEntrance !== null) {
                    $response = $this->updateAdminEntrance($adminEntrance, $oldAdminEntrance);
                    if ($response) {
                        $this->model->rollback();
                        return $response;
                    }
                }
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                return $this->error($e->getMessage());
            }

            if ($result !== false) {
                return $this->success(__('The current page configuration item was updated successfully'));
            }
            return $this->error(__('No rows updated'));
        }

        return $this->error(__('Parameter error'));
    }

    /**
     * 添加配置项
     * @throws Throwable
     */
    public function add(): Response
    {
        $configGroup = (array) get_sys_config('config_group');
        $scope = $this->resolveConfigGroupScope($configGroup);
        if ($scope instanceof Response) {
            return $scope;
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                return $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data   = $this->excludeFields($data);
            $data['name'] = isset($data['name']) ? trim((string) $data['name']) : '';
            if (!$data['name']) {
                return $this->error(__('Parameter %s can not be empty', [__('Variable name')]));
            }

            if ($this->model->where('name', $data['name'])->find()) {
                return $this->error(__('Variable name already exists'));
            }

            if (!isset($data['group']) || !in_array((string) $data['group'], $scope['groups'], true)) {
                return $this->error(__('You have no permission'));
            }

            $result = false;
            $this->model->startTrans();
            try {
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) {
                            $validate->scene('add');
                        }
                        $validate->check($data);
                    }
                }
                $result = $this->model->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                if (str_contains((string) $e->getMessage(), 'Duplicate entry')
                    || str_contains((string) $e->getMessage(), 'duplicate key value')) {
                    return $this->error(__('Variable name already exists'));
                }
                return $this->error($e->getMessage());
            }

            if ($result !== false) {
                return $this->success(__('Added successfully'));
            }
            return $this->error(__('No rows were added'));
        }

        return $this->error(__('Parameter error'));
    }

    /**
     * 删除配置项
     * @throws Throwable
     */
    public function del(): Response
    {
        $configGroup = (array) get_sys_config('config_group');
        $scope = $this->resolveConfigGroupScope($configGroup);
        if ($scope instanceof Response) {
            return $scope;
        }

        $ids = array_values(array_filter(array_map('intval', (array) $this->request->input('ids', []))));
        if (!$ids) {
            return $this->error(__('Parameter error'));
        }

        $rows = $this->model->where($this->model->getPk(), 'in', $ids)->select();
        if (!$rows || count($rows) !== count(array_unique($ids))) {
            return $this->error(__('Record not found'));
        }

        foreach ($rows as $row) {
            if (!in_array((string) $row->group, $scope['groups'], true) || !(int) $row->allow_del) {
                return $this->error(__('You have no permission'));
            }
        }

        $count = 0;
        $this->model->startTrans();
        try {
            foreach ($rows as $row) {
                $count += $row->delete();
            }
            $this->model->commit();
        } catch (Throwable $e) {
            $this->model->rollback();
            return $this->error($e->getMessage());
        }

        return $count ? $this->success(__('Deleted successfully')) : $this->error(__('No rows were deleted'));
    }

    /**
     * 同步后台入口路径到前端静态路由配置。
     *
     * @param string      $newEntrance 新后台入口路径
     * @param string|null $oldEntrance 旧后台入口路径，未传时从系统配置读取
     * @return Response|null 校验或写入失败时返回错误响应，成功或无需变更时返回 null
     */
    protected function updateAdminEntrance(string $newEntrance, ?string $oldEntrance = null): ?Response
    {
        $newEntrance = $this->normalizeAdminEntrance($newEntrance);
        if ($newEntrance === null) {
            return $this->error(__('Admin entrance rule'));
        }

        $oldEntrance = $this->normalizeAdminEntrance((string) ($oldEntrance ?? get_sys_config('admin_entrance'))) ?: '/admin';
        if ($oldEntrance === $newEntrance) {
            return null;
        }

        $adminBaseFilePath = Filesystem::fsFit(base_path($this->filePath['webAdminBase']));
        $adminBaseContent  = @file_get_contents($adminBaseFilePath);
        if ($adminBaseContent === false) {
            return $this->error(__('Configuration write failed: %s', [$this->filePath['webAdminBase']]));
        }

        $newAdminBaseContent = preg_replace(
            '/export\s+const\s+adminBaseRoutePath\s*=\s*([\'"])\/[A-Za-z0-9]+\1/',
            "export const adminBaseRoutePath = '$newEntrance'",
            $adminBaseContent,
            1,
            $replaceCount
        );
        if ($newAdminBaseContent === null || $replaceCount < 1) {
            return $this->error(__('Configuration write failed: %s', [$this->filePath['webAdminBase']]));
        }

        $result = @file_put_contents($adminBaseFilePath, $newAdminBaseContent);
        if ($result === false) {
            return $this->error(__('Configuration write failed: %s', [$this->filePath['webAdminBase']]));
        }

        return null;
    }

    /**
     * 规范并校验后台入口路径。
     *
     * @param string $entrance 后台入口路径
     * @return string|null 合法路径返回规范值，非法路径返回 null
     */
    private function normalizeAdminEntrance(string $entrance): ?string
    {
        $entrance = trim($entrance);
        if (!preg_match('/^\/[A-Za-z0-9]+$/', $entrance)) {
            return null;
        }

        $reserved = ['api', 'app', 'assets', 'static'];
        if (in_array(strtolower(ltrim($entrance, '/')), $reserved, true)) {
            return null;
        }

        return $entrance;
    }

    /**
     * 输出配置分组前翻译分组标题。
     *
     * @param mixed $configGroups 配置分组原始数据
     * @return mixed 翻译后的配置分组数据
     */
    private function configGroupsForDisplay(mixed $configGroups): mixed
    {
        if (!is_array($configGroups)) {
            return $configGroups;
        }

        foreach ($configGroups as &$item) {
            if (!is_array($item) || !isset($item['value'])) {
                continue;
            }
            $item['value'] = __((string) $item['value']);
        }
        unset($item);

        return $configGroups;
    }

    /**
     * 保存配置分组前还原默认标题，并保留当前权限不可见的隐藏分组。
     *
     * @param mixed           $configGroups     待保存的配置分组数据
     * @param array<int, string>|null $visibleGroupKeys 当前可见分组 key
     * @return mixed 适合写入配置表的配置分组数据
     */
    private function configGroupsForStorage(mixed $configGroups, ?array $visibleGroupKeys = null): mixed
    {
        if (!is_array($configGroups)) {
            return $configGroups;
        }

        foreach ($configGroups as &$item) {
            if (!is_array($item) || !isset($item['key'], $item['value'])) {
                continue;
            }
            $defaultTitle = self::DEFAULT_CONFIG_GROUP_TITLES[(string) $item['key']] ?? null;
            if ($defaultTitle && in_array($item['value'], [$defaultTitle, __($defaultTitle)], true)) {
                $item['value'] = $defaultTitle;
            }
        }
        unset($item);

        if ($visibleGroupKeys !== null) {
            $visibleGroupKeys = array_flip($visibleGroupKeys);
            $postedGroupKeys = [];
            foreach ($configGroups as $item) {
                if (is_array($item) && isset($item['key'])) {
                    $postedGroupKeys[(string) $item['key']] = true;
                }
            }

            $hiddenGroups = [];
            foreach ((array) get_sys_config('config_group') as $item) {
                if (!is_array($item) || !isset($item['key'])) {
                    continue;
                }
                $key = (string) $item['key'];
                if (!isset($visibleGroupKeys[$key]) && !isset($postedGroupKeys[$key])) {
                    $hiddenGroups[] = $item;
                }
            }

            return array_merge($hiddenGroups, $configGroups);
        }

        return $configGroups;
    }

    /**
     * 根据请求参数和菜单权限解析本次可操作的配置分组。
     *
     * @param array<int, mixed> $configGroup 系统配置中的全部配置分组
     * @return array{groups: array<int, string>, hasQueryScope: bool}|Response
     */
    private function resolveConfigGroupScope(array $configGroup): array|Response
    {
        $allGroups = [];
        foreach ($configGroup as $item) {
            if (is_array($item) && isset($item['key'])) {
                $allGroups[] = (string) $item['key'];
            }
        }

        $rawGroups = $this->request->get('groups', '');
        $rawGroup = $this->request->get('group', '');
        $hasGroupsScope = $this->hasConfigGroupParam($rawGroups);
        $hasGroupScope = $this->hasConfigGroupParam($rawGroup);
        $hasQueryScope = $hasGroupsScope || $hasGroupScope;
        $queryGroups = $this->normalizeConfigGroupParam($rawGroups);
        $queryGroup = $this->normalizeConfigGroupParam($rawGroup);
        $groups = $hasGroupsScope ? $queryGroups : ($hasGroupScope ? $queryGroup : self::DEFAULT_VISIBLE_CONFIG_GROUPS);
        $groups = array_values(array_unique($groups));

        if (!$groups || array_diff($groups, $allGroups)) {
            return $this->error(__('You have no permission'));
        }

        if ($hasQueryScope) {
            $authorizedGroups = $this->getAuthorizedConfigGroupsFromMenus();
            if (array_diff($groups, $authorizedGroups)) {
                return $this->error(__('You have no permission'));
            }
        }

        return [
            'groups' => $groups,
            'hasQueryScope' => $hasQueryScope,
        ];
    }

    /**
     * 规范化配置分组请求参数。
     *
     * @param mixed $value 支持逗号分隔字符串或数组
     * @return array<int, string> 去重后的配置分组 key
     */
    private function normalizeConfigGroupParam(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = explode(',', (string) $value);
        }

        $groups = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item !== '' && preg_match('/^[A-Za-z0-9_]+$/', $item)) {
                $groups[] = $item;
            }
        }

        return array_values(array_unique($groups));
    }

    /**
     * 判断请求中是否显式传入了配置分组参数。
     *
     * @param mixed $value 请求参数原始值
     */
    private function hasConfigGroupParam(mixed $value): bool
    {
        if (is_array($value)) {
            return (bool) array_filter($value, static fn ($item): bool => trim((string) $item) !== '');
        }

        return trim((string) $value) !== '';
    }

    /**
     * 从当前管理员可访问菜单中提取授权的配置分组。
     *
     * @return array<int, string> 授权配置分组 key
     */
    private function getAuthorizedConfigGroupsFromMenus(): array
    {
        $groups = [];
        foreach ($this->auth->getRuleList() as $rule) {
            if (($rule['type'] ?? '') !== 'menu') {
                continue;
            }

            $path = (string) ($rule['path'] ?? '');
            if (trim((string) parse_url($path, PHP_URL_PATH), '/') !== 'system/config') {
                continue;
            }

            $query = (string) parse_url($path, PHP_URL_QUERY);
            if ($query === '') {
                continue;
            }

            parse_str($query, $params);
            $ruleGroups = $this->normalizeConfigGroupParam($params['groups'] ?? '');
            if (!$ruleGroups) {
                $ruleGroups = $this->normalizeConfigGroupParam($params['group'] ?? '');
            }
            $groups = array_merge($groups, $ruleGroups);
        }

        return array_values(array_unique($groups));
    }

    /**
     * 过滤出当前可见的配置分组。
     *
     * @param array<int, mixed>   $configGroup      系统配置中的全部配置分组
     * @param array<int, string>  $visibleGroupKeys 当前可见分组 key
     * @return array<int, mixed> 可展示的配置分组
     */
    private function filterConfigGroups(array $configGroup, array $visibleGroupKeys): array
    {
        $visibleGroupKeys = array_flip($visibleGroupKeys);
        return array_values(array_filter($configGroup, static function ($item) use ($visibleGroupKeys): bool {
            return is_array($item) && isset($item['key']) && isset($visibleGroupKeys[(string) $item['key']]);
        }));
    }
}
