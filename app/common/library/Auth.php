<?php

namespace app\common\library;

use Throwable;
use Webman\Config;
use support\think\Db;
use core\service\Token;
use core\utils\Random;
use core\utils\Tree;
use app\admin\model\Admin as AdminModel;
use app\admin\model\AdminGroup;
use app\admin\model\AdminRule;

/**
 * 后台权限类（管理员权限类）
 * @property int    $id              管理员ID
 * @property string $username        管理员用户名
 * @property string $nickname        管理员昵称
 * @property string $email           管理员邮箱
 * @property string $mobile          管理员手机号
 * @property string $password        密码密文
 * @property string $salt            密码盐
 * @property int    $status          管理员状态
 * @property int    $last_login_time 最后登录时间
 * @property string $last_login_ip   最后登录IP
 */
class Auth
{
    /**
     * 需要登录时/无需登录时的响应状态代码
     */
    public const LOGIN_RESPONSE_CODE = 303;

    /**
     * 需要登录标记 - 前台应清理 token、记录当前路由 path、跳转到登录页
     */
    public const NEED_LOGIN = 'need login';

    /**
     * 已经登录标记 - 前台应跳转到基础路由
     */
    public const LOGGED_IN = 'logged in';

    /**
     * token 入库 type
     */
    public const TOKEN_TYPE = 'admin';

    /**
     * 是否登录
     * @var bool
     */
    protected bool $loginEd = false;

    /**
     * 错误消息
     * @var string
     */
    protected string $error = '';

    /**
     * Model实例
     * @var ?AdminModel
     */
    protected ?AdminModel $model = null;

    /**
     * 令牌
     * @var string
     */
    protected string $token = '';

    /**
     * 刷新令牌
     * @var string
     */
    protected string $refreshToken = '';

    /**
     * 令牌默认有效期
     * 可在 config/xframe.php 内修改默认值
     * @var int
     */
    protected int $keepTime = 86400;

    /**
     * 刷新令牌有效期
     * @var int
     */
    protected int $refreshTokenKeepTime = 2592000;

    /**
     * 允许输出的字段
     * @var array
     */
    protected array $allowFields = ['id', 'username', 'nickname', 'email', 'mobile', 'avatar', 'motto', 'last_login_time', 'last_login_ip', 'group_arr', 'group_name_arr'];

    /**
     * 构造方法
     * @param array $config 预留配置项
     */
    public function __construct(array $config = [])
    {
        $this->setKeepTime((int) Config::get('xframe.admin_token_keep_time', 86400));
    }

    /**
     * 魔术方法-管理员信息字段
     * @param $name
     * @return mixed 字段信息
     */
    public function __get($name): mixed
    {
        return $this->model?->$name;
    }

    /**
     * 初始化
     * @param array $options 预留配置项
     * @return Auth
     */
    public static function instance(array $options = []): Auth
    {
        $request = request();
        if (!isset($request->adminAuth)) {
            $request->adminAuth = new static($options);
        }
        return $request->adminAuth;
    }

    /**
     * 根据Token初始化管理员登录态
     * @param $token
     * @return bool
     * @throws Throwable
     */
    public function init($token): bool
    {
        $tokenService = new Token();
        $tokenData = $tokenService->get($token);
        if ($tokenData) {
            /**
             * 过期检查，过期则抛出 TokenExpirationException
             */
            $tokenService->tokenExpirationCheck($tokenData);
            $adminId = intval($tokenData['user_id']);
            if ($tokenData['type'] === self::TOKEN_TYPE && $adminId > 0) {
                $this->model = AdminModel::where('id', $adminId)->find();
                if (!$this->model) {
                    $this->setError('Account not exist');
                    return false;
                }
                if ((int) $this->model->status !== 1) {
                    $this->setError('Account disabled');
                    return false;
                }
                $this->token = $token;
                $this->loginEd = true;
                return true;
            }
        }

        $this->setError('Token login failed');
        $this->reset(false);
        return false;
    }

    /**
     * 管理员登录
     * @param string $username 用户名
     * @param string $password 密码
     * @param bool   $keep     是否保持登录
     * @return bool
     * @throws Throwable
     */
    public function login(string $username, string $password, bool $keep): bool
    {
        // 判断账户类型
        $accountType = false;
        if (preg_match('/^1[3-9]\d{9}$/', $username)) {
            $accountType = 'mobile';
        } elseif (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $accountType = 'email';
        } elseif (preg_match('/^[a-zA-Z][a-zA-Z0-9_]{2,29}$/', $username)) {
            $accountType = 'username';
        }
        if (!$accountType) {
            $this->setError('Account not exist');
            return false;
        }

        $this->model = AdminModel::where($accountType, $username)->find();
        if (!$this->model) {
            $this->setError('Account not exist');
            return false;
        }
        if ((int) $this->model->status === 0) {
            $this->setError('Account disabled');
            return false;
        }

        // 登录失败重试检查
        $adminLoginRetry = Config::get('xframe.admin_login_retry');
        if ($adminLoginRetry && $this->model->last_login_time) {
            // 重置失败次数
            if ($this->model->login_failure > 0 && time() - (int) $this->model->last_login_time >= 86400) {
                $this->model->login_failure = 0;
                $this->model->save();

                // 重获模型实例，避免单实例多次更新
                $this->model = AdminModel::where($accountType, $username)->find();
            }
            if ($this->model->login_failure >= $adminLoginRetry) {
                $this->setError('Please try again after 1 day');
                return false;
            }
        }

        // 密码检查
        if (!\verify_password($password, $this->model->password, ['salt' => $this->model->salt ?? ''])) {
            $this->loginFailed();
            $this->setError('Password is incorrect');
            return false;
        }

        // 清理 token
        $tokenService = new Token();
        if (Config::get('xframe.admin_sso')) {
            $tokenService->clear(self::TOKEN_TYPE, $this->model->id);
            $tokenService->clear(self::TOKEN_TYPE . '-refresh', $this->model->id);
        }
        if ($keep) {
            $this->setRefreshToken($this->refreshTokenKeepTime);
        }
        return $this->loginSuccessful();
    }

    /**
     * 直接登录管理员账号
     * @param int $adminId 管理员ID
     * @return bool
     * @throws Throwable
     */
    public function direct(int $adminId): bool
    {
        $this->model = AdminModel::find($adminId);
        if (!$this->model) {
            return false;
        }
        return $this->loginSuccessful();
    }

    /**
     * 检查旧密码是否正确
     * @param $password
     * @return bool
     * @deprecated 请使用 verify_password 公共函数代替
     */
    public function checkPassword($password): bool
    {
        return $this->model ? \verify_password($password, $this->model->password, ['salt' => $this->model->salt ?? '']) : false;
    }

    /**
     * 登录成功
     * @return bool
     */
    public function loginSuccessful(): bool
    {
        if (!$this->model) {
            return false;
        }
        $this->model->startTrans();
        try {
            $this->model->login_failure = 0;
            $this->model->last_login_time = time();
            $this->model->last_login_ip = request()->getRealIp();
            $this->model->save();
            $this->loginEd = true;

            if (!$this->token) {
                $this->token = Random::uuid();
                (new Token())->set($this->token, self::TOKEN_TYPE, $this->model->id, $this->keepTime);
            }
            $this->model->commit();
        } catch (Throwable $e) {
            $this->model->rollback();
            $this->setError($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * 登录失败
     * @return bool
     */
    public function loginFailed(): bool
    {
        if (!$this->model) {
            return false;
        }
        $this->model->startTrans();
        try {
            $this->model->login_failure++;
            $this->model->last_login_time = time();
            $this->model->last_login_ip = request()->getRealIp();
            $this->model->save();
            $this->model->commit();
        } catch (Throwable $e) {
            $this->model->rollback();
            $this->setError($e->getMessage());
            return false;
        }
        return $this->reset();
    }

    /**
     * 退出登录
     * @return bool
     */
    public function logout(): bool
    {
        if (!$this->loginEd) {
            $this->setError('You are not logged in');
            return false;
        }
        return $this->reset();
    }

    /**
     * 是否登录
     * @return bool
     */
    public function isLogin(): bool
    {
        return $this->loginEd;
    }

    /**
     * 获取管理员模型
     * @return AdminModel
     */
    public function getUser(): AdminModel
    {
        return $this->model;
    }

    /**
     * 获取管理员Token
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * 设置刷新Token
     * @param int $keepTime
     * @return void
     */
    public function setRefreshToken(int $keepTime = 0): void
    {
        if (!$this->model) {
            return;
        }
        $this->refreshToken = Random::uuid();
        (new Token())->set($this->refreshToken, self::TOKEN_TYPE . '-refresh', $this->model->id, $keepTime);
    }

    /**
     * 获取管理员刷新Token
     * @return string
     */
    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    /**
     * 获取管理员信息 - 只输出允许输出的字段
     * @return array
     */
    public function getInfo(): array
    {
        if (!$this->model) {
            return [];
        }
        $info = $this->model->toArray();
        $info = array_intersect_key($info, array_flip($this->getAllowFields()));
        $info['token'] = $this->getToken();
        $info['refresh_token'] = $this->getRefreshToken();
        return $info;
    }

    /**
     * 获取管理员信息
     * @return array
     */
    public function getUserInfo(): array
    {
        return $this->getInfo();
    }

    /**
     * 获取允许输出字段
     * @return array
     */
    public function getAllowFields(): array
    {
        return $this->allowFields;
    }

    /**
     * 设置允许输出字段
     * @param $fields
     * @return void
     */
    public function setAllowFields($fields): void
    {
        $this->allowFields = (array) $fields;
    }

    /**
     * 设置Token有效期
     * @param int $keepTime
     * @return void
     */
    public function setKeepTime(int $keepTime = 0): void
    {
        $this->keepTime = $keepTime;
    }

    /**
     * 检查权限
     * @param string $name     权限规则
     * @param int    $uid      管理员ID
     * @param string $relation 关系
     * @param string $mode     检查模式
     * @return bool
     */
    public function check(string $name, int $uid = 0, string $relation = 'or', string $mode = 'url'): bool
    {
        $uid = $uid ?: (int) $this->id;
        $name = trim($name, '/');
        if ($this->isSuperAdmin($uid)) {
            return true;
        }
        $ruleNames = array_column($this->getRuleList($uid), 'name');
        return in_array($name, $ruleNames, true);
    }

    /**
     * 获取管理员拥有的权限规则列表
     * @param int $uid 管理员ID
     * @return array
     */
    public function getRuleList(int $uid = 0): array
    {
        $ids = $this->getRuleIds($uid);
        $query = AdminRule::where('status', 1);
        if (!in_array('*', $ids, true)) {
            if (!$ids) {
                return [];
            }
            $query->where('id', 'in', $ids);
        }
        return $query->order('sort', 'desc')->select()->toArray();
    }

    /**
     * 获取管理员拥有的权限规则ID
     * @param int $uid 管理员ID
     * @return array
     */
    public function getRuleIds(int $uid = 0): array
    {
        $uid = $uid ?: (int) $this->id;
        if ($uid <= 0) {
            return [];
        }
        $groupIds = Db::name('admin_group_access')->where('uid', $uid)->column('group_id');
        if (!$groupIds) {
            return [];
        }
        $groups = AdminGroup::where('status', 1)->where('id', 'in', $groupIds)->select()->toArray();
        $ids = [];
        foreach ($groups as $group) {
            if (($group['rules'] ?? '') === '*') {
                return ['*'];
            }
            $ids = array_merge($ids, array_filter(explode(',', (string) ($group['rules'] ?? ''))));
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * 获取管理员菜单
     * @param int $uid 管理员ID
     * @return array
     */
    public function getMenus(int $uid = 0): array
    {
        $rules = array_filter($this->getRuleList($uid), fn ($rule) => in_array($rule['type'], ['menu_dir', 'menu', 'button'], true));
        return Tree::instance()->assembleChild(array_values($rules));
    }

    /**
     * 是否是超级管理员
     * @param int $uid 管理员ID
     * @return bool
     */
    public function isSuperAdmin(int $uid = 0): bool
    {
        return in_array('*', $this->getRuleIds($uid ?: (int) $this->id), true);
    }

    /**
     * 是否是拥有所有权限的管理员
     * @return bool
     */
    public function isSuperUser(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * 获取指定分组下的管理员
     * @param array $groupIds 分组ID
     * @return array
     */
    public function getGroupAdmins(array $groupIds): array
    {
        if (!$groupIds) {
            return [];
        }
        return Db::name('admin_group_access')->where('group_id', 'in', $groupIds)->column('uid');
    }

    /**
     * 获取管理员所属分组的子级分组
     * @param int $uid 管理员ID
     * @return array
     */
    public function getAdminChildGroups(int $uid = 0): array
    {
        $uid = $uid ?: (int) $this->id;
        $groupIds = Db::name('admin_group_access')->where('uid', $uid)->column('group_id');
        if (!$groupIds) {
            return [];
        }
        $groups = AdminGroup::where('status', 1)->select()->toArray();
        $children = [];
        $parents = $groupIds;
        do {
            $found = [];
            foreach ($groups as $group) {
                if (in_array($group['pid'], $parents, true) && !in_array($group['id'], $children, true)) {
                    $children[] = $group['id'];
                    $found[] = $group['id'];
                }
            }
            $parents = $found;
        } while ($parents);
        return $children;
    }

    /**
     * 获取当前管理员可管理的权限分组
     * @param string $method allAuth=拥有目标分组全部权限；allAuthAndOthers=拥有目标分组全部权限且还有其他权限
     * @param array  $where  查询条件
     * @return array
     */
    public function getAllAuthGroups(string $method = 'allAuth', array $where = []): array
    {
        if ($this->isSuperAdmin()) {
            return AdminGroup::where($where)->column('id');
        }
        $ownedRuleIds = $this->getRuleIds();
        if (!$ownedRuleIds || in_array('*', $ownedRuleIds, true)) {
            return [];
        }

        $groups = AdminGroup::where($where)->where('status', 1)->select()->toArray();
        $ids = [];
        foreach ($groups as $group) {
            $rules = ($group['rules'] ?? '') === '*' ? ['*'] : array_filter(array_map('intval', explode(',', (string) ($group['rules'] ?? ''))));
            if (in_array('*', $rules, true)) {
                continue;
            }
            $hasAll = !array_diff($rules, $ownedRuleIds);
            $hasOthers = (bool) array_diff($ownedRuleIds, $rules);
            if ($hasAll && ($method === 'allAuth' || $hasOthers)) {
                $ids[] = $group['id'];
            }
        }
        return $ids;
    }

    /**
     * 设置错误消息
     * @param string $error
     * @return Auth
     */
    public function setError(string $error): Auth
    {
        $this->error = $error;
        return $this;
    }

    /**
     * 获取错误消息
     * @return string
     */
    public function getError(): string
    {
        return $this->error ? __($this->error) : '';
    }

    /**
     * 属性重置（注销、登录失败、重新初始化等将单例数据销毁）
     * @param bool $deleteToken 是否删除当前令牌
     * @return bool
     */
    protected function reset(bool $deleteToken = true): bool
    {
        if ($deleteToken && $this->token) {
            (new Token())->delete($this->token);
        }

        $this->token = '';
        $this->loginEd = false;
        $this->model = null;
        $this->refreshToken = '';
        $this->setError('');
        $this->setKeepTime((int) Config::get('xframe.admin_token_keep_time', 86400));
        return true;
    }
}
