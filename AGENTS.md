# XFrame AI Project Guide

这份文档给后续 AI/Agent 快速理解本仓库使用。先读本文，再改代码。

## 项目定位

XFrame 是一个基于 webman 的后台管理框架项目，后端使用 PHP 8.1+、webman、Think ORM、Phinx，前端使用 Vue 3、Vite、TypeScript、Element Plus、Pinia。

仓库是前后端一体结构：

- 后端入口：`start.php`
- 后端应用：`app/`
- 核心服务和 CRUD 生成器：`core/`
- 配置：`config/`
- 数据库迁移与种子：`database/`
- 前端源码：`web/`
- 前端构建产物发布目录：`public/`
- 独立错误日志目录：`errlog/`

## 快速命令

后端依赖：

```bash
composer install
```

启动后端：

```bash
php start.php start
```

守护进程启动：

```bash
php start.php start -d
```

重启守护进程，尤其是修改 `.env`、`config/` 或 PHP 代码后：

```bash
php start.php restart -d
```

数据库迁移：

```bash
php vendor/bin/phinx migrate -c phinx.php
```

数据库种子：

```bash
php vendor/bin/phinx seed:run -c phinx.php -s XFrameSeeder
```

前端依赖与开发：

```bash
cd web
pnpm install
pnpm dev
```

前端校验与构建：

```bash
cd web
pnpm typecheck
pnpm lint
pnpm build
```

前端当前有基于 Node assert 的轻量测试，可用：

```bash
cd web
pnpm exec tsx tests/route-lang-loader.test.ts
pnpm exec tsx tests/admin-url.test.ts
```

## 环境变量

根目录 `.env` 由 `vlucas/phpdotenv` 加载，主要变量：

- `APP_PORT`：webman 监听端口，默认 8000。
- `APP_WORKERS`：worker 数，默认 `cpu_count() * 4`。
- `APP_DEBUG`：运行态 debug。注意 `.env` 不会自动接管运行态，必须由 `config/app.php` 显式读取；守护进程下修改后必须 restart。
- `DB_CONNECTION`：`mysql` 或 `pgsql`。
- `DB_HOST`、`DB_PORT`、`DB_DATABASE`、`DB_USERNAME`、`DB_PASSWORD`。
- `XFRAME_DEFAULT_PASSWORD`：Seeder 创建默认管理员密码，默认 `123456`。

前端环境在 `web/.env.development` 和 `web/.env.production`：

- `VITE_AXIOS_BASE_URL=getCurrentDomain` 表示用当前域名。
- 本地开发默认请求 `http://localhost:8000`。
- `VITE_BASE_PATH`、`VITE_OUT_DIR` 影响 Vite base 和构建输出。

`docker-compose.yml` 当前把宿主 `8787` 映射到容器 `8787`，但后端默认监听 `APP_PORT=8000`。使用 Docker 前先确认端口配置是否一致。

## 后端架构

webman 会按默认控制器规则分发请求。根路由逻辑在 `config/route.php`：

- 后台页面入口默认 `/admin`，也可由数据库配置项 `admin_entrance` 改成其他单段路径。
- 后台接口请求必须带 `server` 请求头或参数，前端 axios 默认加 `server: true`。
- 当前后台入口不是 `/admin` 时，后端会把自定义入口映射回 `app\admin` 控制器。
- 非后台入口请求继续交给 webman 默认分发。

控制器基类：

- `app\BaseController`：统一 `success()`、`error()` 和 JSON 响应结构。
- `app\common\controller\Admin`：后台控制器基类，挂载 `AdminAuth` 与 `AdminLog` 中间件，提供查询构建、数据权限、通用排序。
- `app\common\traits\Admin`：通用 CRUD 方法 `index/add/edit/del/sortable/select`。

后台鉴权：

- `app\common\middleware\AdminAuth` 只处理继承 `app\common\controller\Admin` 的控制器。
- 控制器可用 `$noNeedLogin` 和 `$noNeedPermission` 放行方法。
- 权限节点来自 `admin_rule.name`，控制器路径会通过 `admin_controller_rule_path()` 转成 如 `system/errorLog/item`。
- 超级管理员组的 `rules='*'`。
- 未登录返回 code `303`，token 过期返回 code `409`，无权限返回 code `401`。

Token：

- `core\service\Token` 支持 mysql/redis driver，默认配置在 `config/xframe.php`。
- 后台 token 类型是 `admin`，刷新 token 类型是 `admin-refresh`。
- 前端通过 `xtoken` 请求头携带后台 token。

上传：

- 上传配置在 `config/upload.php`。
- 上传服务是 `core\service\Upload`，默认本地驱动。
- 文件入库表是 `attachment`，同一 `sha1 + topic + storage` 会复用记录并增加引用。

错误日志：

- 业务错误日志后台严格围绕项目根目录 `errlog/`，不是 webman 默认 `runtime/logs/`。
- `app\common\exception\Handler` 会调用 `log_err()`。
- `log_err()` 写入 `errlog/YYYY-MM-DD.log`，超过 2MB 会轮转。
- 后台错误日志接口在 `app/admin/controller/system/ErrorLog.php`，读取/下载/删除前必须校验文件名并限制在 `errlog/` 内。

## 前端架构

前端源码在 `web/src`。

关键入口：

- `web/src/main.ts`：Vue 应用入口。
- `web/src/router/index.ts`：Vue Router，含语言包按需加载。
- `web/src/router/static/adminBase.ts`：后台基础路径 `adminBaseRoutePath`，会被系统配置里的后台入口同步改写。
- `web/src/utils/axios.ts`：统一请求封装、token 注入、刷新 token、错误处理、后台入口 URL 规范化。
- `web/src/utils/router.ts`：动态菜单路由、权限节点、菜单树处理。
- `web/src/stores/adminInfo.ts`：后台管理员登录态。
- `web/src/stores/siteConfig.ts`、`web/src/stores/config.ts`：站点与 UI 配置。

前端 API 约定：

- 后台接口一般写在 `web/src/api/admin/**`。
- 后台接口使用 webman 默认控制器路径，如 `/admin/system/Admin/index`，不再兼容点号控制器 URL。
- 后台入口改为非 `/admin` 时，axios 会把接口前缀替换成当前 `adminBaseRoutePath`。
- 通用表格接口可用 `xTableApi`，默认动作是 `index/add/edit/del/sortable`。

动态菜单：

- 后端 `admin_rule` 返回菜单和按钮权限。
- 前端 `handleAdminRoute()` 会把菜单中的 `component` 映射到 `/src/views/admin/**/*.vue`。
- `extend='add_menu_only'` 表示只加菜单，不注册真实路由。
- `extend='add_rules_only'` 表示只加权限节点，不显示菜单。

语言包：

- 语言入口在 `web/src/lang/`。
- 路由切换时 `loadRouteLangMessages()` 会按后台路径和 route name 加载页面语言包，再加载公共语言包。
- 新页面通常需要同步增加中英文语言文件。

## 数据库与初始化

迁移配置在 `phinx.php`，会读取 `.env` 中的 DB 配置。当前迁移目录：

- `database/migrations/20260523000000_create_xframe_table.php`

种子类：

- `database/seeds/XFrameSeeder.php`

当前 1.0.0 干净库基线（2026-05-27）：

- 迁移状态：`20260523000000 CreateXframeTable` 为 up。
- 表：`admin`、`admin_group`、`admin_group_access`、`admin_log`、`admin_rule`、`attachment`、`attachment_category`、`captcha`、`config`、`dataexport`、`phinxlog`、`token`、`user`。
- 关键计数：`admin=1`、`admin_group=1`、`admin_group_access=1`、`admin_rule=47`、`config=7`、`phinxlog=1`。
- 空表：`attachment`、`attachment_category`、`admin_log`、`captcha`、`dataexport`、`token`、`user`。

重要约定：

- 大多数基础表使用 `create_time` / `update_time`。
- `admin`、`admin_group`、`admin_rule`、`user` 的 `status` 语义统一为整数：`1=启用`、`0=禁用`。
- 当前 seed 使用 `Y-m-d H:i:s` 写入 datetime 字段。
- 迁移/seed 依赖 `app/functions.php` 中的公共 helper。若 CLI 报 helper 未定义，先确认 bootstrap 是否加载。

## 新增后台功能的交付规则

新增一个菜单型后台功能时，不要只加控制器和 Vue 页面。必须同时考虑：

- 后端控制器：通常继承 `app\common\controller\Admin`。
- 模型与验证器：放在对应 `app/**/model`、`app/**/validate`。
- 前端页面：放在 `web/src/views/admin/**`。
- 前端 API：放在 `web/src/api/admin/**`。
- 语言包：`web/src/lang/admin/zh-cn/**` 与 `web/src/lang/admin/en/**`。
- 权限节点：`admin_rule` 中菜单和 button 权限必须完整。
- 新装数据库：更新 `XFrameSeeder`。
- 已有数据库：新增幂等 Phinx 迁移，不能只依赖最新 seed。
- 前端按钮显隐：优先由后端返回的权限节点和 `navTabs` 权限映射驱动。

系统配置功能的特殊约定：

- “系统配置”拆成可见菜单入口 `system/config/menu` 和隐藏路由权限节点 `system/config`。
- `Config` 控制器会返回 `permissions.add` / `permissions.del` 给前端决定按钮显隐。
- 后台入口 `admin_entrance` 更新时会同步改写 `web/src/router/static/adminBase.ts`。

错误日志功能的特殊约定：

- 权限节点是 `system/errorLog` 及其 `index/item/download/del/delall`。
- seed 和幂等迁移都必须包含这些节点。
- 目标目录是 `errlog/`，不是 `runtime/logs/`。

## CRUD 生成器

命令类是 `app/command/XframeCrud.php`，核心实现位于 `core/crud/`。

常用示例：

```bash
php webman xframe:crud --table=your_table --title=功能名 --path=module/name --parent=system
```

有用选项：

- `--dry-run`：预览文件和菜单。
- `--force`：覆盖已生成文件。
- `--delete`：按 manifest 删除生成内容。
- `--no-menu`：只生成文件，不写菜单。
- `--spec=path.json`：传字段覆盖配置。

生成器会写后端、前端和菜单 manifest。改生成器前先看 `core/crud/DescriptorBuilder.php`、`Generator.php`、`MenuWriter.php`、`ManifestWriter.php`。

## 代码风格与注意事项

PHP：

- 遵循现有 webman + Think ORM 风格，不引入 ThinkPHP facade 兼容层，除非已有代码已使用。
- 后台接口统一返回 `success()/error()` 包装结构。
- 输入过滤优先用现有 helper：`filter()`、`clean_xss()`。
- 权限路径使用 `admin_controller_rule_path()` 的输出风格。
- 文件操作必须限制目录边界，尤其是日志、附件、下载、删除类功能。

TypeScript/Vue：

- 路径别名 `/@` 指向 `web/src`。
- UI 基于 Element Plus 和项目已有 `xTable`、`formInput`、`attachmentMaterial` 等组件。
- 新后台页面尽量沿用 `web/src/views/admin/system/*` 现有表格、弹窗、权限按钮模式。
- axios 请求默认期待后端 code `1` 成功；code 非 1 会被拦截为 reject。
- 不要随意改 `public/assets`，它是构建产物目录。

数据库：

- 影响内置数据、权限节点、配置分组、菜单路径的变更，必须区分“新装 seed”和“存量库迁移”。
- 幂等迁移应按稳定字段如 `name` 查找父级和已存在记录，避免依赖固定 ID；如果兼容当前 seed 需要固定 ID，也要处理已存在场景。
- 不要假设旧库会因修改历史迁移或 seed 自动更新。

调试：

- `APP_DEBUG=false` 的目标是隐藏响应细节，但仍保留内部详细日志。
- 真实接口验收时用后台入口路径，并带 `server: true`。
- 守护进程模式下修改 `.env` 后必须 restart。

## 不要误删或误改

- `vendor/`、`web/node_modules/`：依赖目录。
- `runtime/`：webman 运行时文件。
- `errlog/`：业务错误日志目录，只保留 `.gitignore` 入库即可。
- `public/`：前端构建发布目录，构建会更新。
- 用户已有未提交改动时，除非任务明确要求，不要回滚、不覆盖。
