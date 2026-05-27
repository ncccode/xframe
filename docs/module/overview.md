# 模块概述与安装流程

## 模块能做什么

- 在允许的目录下**新增或覆盖**文件（安装前会检测冲突并支持备份，见安装器逻辑）。
- 通过 `config.json` 声明 **Composer**、**npm**（及可选 `web-nuxt`）依赖，由安装流程合并进根目录 `composer.json`、`web/package.json` 并执行安装命令。
- 在模块根目录提供 `**install.sql`**，安装时自动导入数据库（见 [安装 SQL](./sql.md)）。
- 提供 **PHP 核心类**，响应安装、卸载、启用、禁用、更新等事件（见 [生命周期与事件](./events.md)）。
- 可选 `**webBootstrap.stub`**，向 `web` 工程 `main.ts`、`App.vue` 等位置插入代码（见 [前端引导](./web-bootstrap.md)）。

覆盖系统已有文件会影响后续升级与维护；除非必要，优先通过新增文件或配置扩展。

## 与后台「模块管理」的关系

管理员在后台使用模块功能时，会调用 `app/admin/controller/Module.php` 中的接口，内部使用 `core\module\Manage` 完成：

- 拉取或上传模块包、解压至 `modules/{uid}/`
- 安装预检（系统版本、依赖与互斥等，与中心服务交互时使用 `config/xframe.php` 中的 `**api_url**` 等配置）
- 导入 SQL、执行事件、合并依赖、处理文件冲突
- 启用/禁用/卸载及 Web 引导代码的写入与清理

系统版本号读取自 `config/xframe.php` 中的 `**version**` 字段。

## 本地调试模块包

将符合规范的 zip 解压到 `modules/{uid}/`，保证存在 `**info.ini**`（见 [目录与清单文件](./structure.md)）。若仅本地开发，可直接在此目录放置源码，再通过后台上传安装或配合安装流程测试。