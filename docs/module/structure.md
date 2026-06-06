# 目录与清单文件

## 可覆盖目录

模块包内可包含与主工程对齐的子目录，安装时会合并到项目根目录。`core\module\Server::getOverwriteDir()` 定义为：

`app`、`config`、`database`、`extend`、`modules`、`public`、`uniapp`、`vendor`、`web`、`web-nuxt`

实际打包时按模块需要包含其中部分即可；不包含的目录不会写入。

## `info.ini`（必填）

模块**根目录**必须提供 `info.ini`，供安装器解析模块元数据（唯一标识、标题、版本等）。字段需与线上规范一致；若需机器解析，请避免在文件内写无法被 `parse_ini_file` 识别的注释格式。

读取示例（仅说明用法）：

```php
use core\module\Server;

$dir = root_path() . 'modules/your_uid/';
$info = Server::getIni($dir);
```

## `config.json`（可选）

与 `**composer.json` / `web/package.json**` 相同语义的键可用于声明依赖，例如：

- `require`、`require-dev`：Composer
- `dependencies`、`devDependencies`：`web` 前端
- `nuxtDependencies`、`nuxtDevDependencies`：存在 `web-nuxt` 工程时使用
- `protectedFiles`：模块禁用时需保留的文件路径（避免误删导致系统无法启动）
- `composerConfig`：合并进根 `composer.json` 的 `config` 段

**JSON 不支持注释**，复制示例时请删除注释行。

安装器通过 `Server::getConfig()`、`Server::getDepend()` 读取；冲突检测见 `dependConflictCheck()`。

## 版本与依赖

模块对**主程序版本**的要求、与其它模块的依赖/互斥关系，通常由模块市场/中心服务端在 `**installPreCheck`** 阶段校验；本地开发时请关注 `Manage::install()` 中的预检参数与返回信息。
