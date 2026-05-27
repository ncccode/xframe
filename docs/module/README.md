# 模块开发

模块用于在**不修改核心框架源码**的前提下，为站点增加或替换 `app`、`web`、`config` 等目录下的文件，并由后台「模块管理」完成下载、依赖安装、SQL 导入与生命周期脚本执行。

实现位于 `core/module/`（`Server`、`Manage` 等），后台接口为 `app/admin/controller/Module.php`。

## 文档索引


| 文档                                       | 说明                                                            |
| ---------------------------------------- | ------------------------------------------------------------- |
| [概述与安装流程](./overview.md)                 | 模块是什么、目录部署、安装/更新/卸载要点                                         |
| [目录与清单文件](./structure.md)                | 可覆盖目录、`info.ini`、`config.json`                                |
| [安装 SQL](./sql.md)                       | `install.sql` 与表前缀占位符                                         |
| [生命周期与事件](./events.md)                   | 核心类、`install` / `uninstall` / `enable` / `disable` / `update` |
| [前端引导（webBootstrap）](./web-bootstrap.md) | `webBootstrap.stub` 注入 `main.ts`、`App.vue` 等                  |


本地模块包解压后位于项目根目录 `modules/{uid}/`（`uid` 为模块唯一标识）。