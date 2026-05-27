# 前端引导（webBootstrap）

模块根目录可提供 `**webBootstrap.stub**`，用于在 `**web**` 工程（及可选 `**web-nuxt**`）的固定位置插入自定义代码；**安装时写入，禁用时按标记清理**（实现见 `core\module\Server::analysisWebBootstrap`、`installWebBootstrap` 等）。

## 代码块标记

文件使用 `**#...#`** 成对标记多个区块，换行符建议使用 **LF（`\n`）**。

常用区块包括：

- `#main.ts import code start#` … `#main.ts import code end#`
- `#main.ts start code start#` … `#main.ts start code end#`
- `#App.vue import code start#` … `#App.vue import code end#`
- `#App.vue onMounted code start#` … `#App.vue onMounted code end#`
- `#web-nuxt/app.vue import code start#` …（若项目存在 Nuxt 工程）
- `#web-nuxt/app.vue start code start#` …

仅需定义实际用到的区块即可。

## 注意

- 插入代码会改变本地 `web/src/main.ts`、`App.vue` 等文件，合并冲突时需人工处理。
- 与 Vite、ESLint 规则保持一致，避免在未使用的 import 上产生告警。