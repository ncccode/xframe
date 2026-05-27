# 模块生命周期与事件

## 核心类位置与命名

对每个模块标识 `uid`，安装器会解析为**驼峰类名**，在命名空间 **`modules\{uid小写}\`** 下查找**与 uid 解析名同名的类**。

例如 `uid` 为 `test` 时，类文件通常为 `modules/test/Test.php`（命名空间 `modules\test`），类名 **`Test`**。

`core\module\Server::getClass()` 会解析为 `\modules\...\YourClass`，`execEvent()` 在类存在且含有对应方法时调用：

| 方法 | 典型时机 |
| --- | --- |
| `install` | 首次安装完成、冲突处理完成之后 |
| `uninstall` | 卸载模块前 |
| `enable` | 启用 |
| `disable` | 禁用 |
| `update` | 升级包安装流程中 |

方法签名为 `public function install()` 等；`execEvent` 传入的第二个参数为事件名，第三个为附加数组，可按需使用。

## 监听 ThinkPHP 与应用事件

可在核心类的 **`AppInit`** 方法（若存在）中注册 `think\facade\Event` 监听，或在 `AppInit` 内编写初始化逻辑；ThinkPHP 内置事件名参见框架文档（如 `HttpRun`、`HttpEnd` 等）。

业务自定义事件需在业务代码中 `trigger`，再在模块内 `Event::listen`。

## 菜单与数据

在 `install` / `uninstall` 中常配合 `app\common\library\Menu` 增删后台菜单；直接操作模型或 `Db::name()` 时请注意表前缀与事务安全。
