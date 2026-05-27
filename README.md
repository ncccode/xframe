# XFrame

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
