# 模块安装 SQL

模块根目录可提供 `**install.sql**`。安装时由 `core\module\Server::importSql()` 读取并执行。

## 约定

- 使用占位符 `**__PREFIX__**` 表示当前数据库表前缀（执行前会替换为 `config` 中 MySQL 前缀）。
- 语句按分号分段执行；以 `--`、`/*` 开头的行会被跳过。
- 实现中对 `INSERT INTO` 会替换为 `**INSERT IGNORE INTO**`，重复数据不易直接报错中断（具体以数据库行为为准）。
- 模块**自建表**建议使用 `**模块 uid` 作为表名前缀**的一部分，避免与核心表冲突。

## 示例

```sql
UPDATE __PREFIX__config SET value='示例' WHERE name='site_name';

CREATE TABLE IF NOT EXISTS `__PREFIX__yourmodule_demo` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
