import { createRouter, createWebHistory } from 'vue-router'
import NProgress from 'nprogress'
import 'nprogress/nprogress.css'
import staticRoutes from '/@/router/static'
import { backendApps } from '/@/utils/router'
import { loading } from '/@/utils/loading'
import langAutoLoadMap from '/@/lang/autoload'
import { mergeMessage } from '/@/lang/index'
import { useConfig } from '/@/stores/config'
import { loadRouteLangMessages } from './langLoader'

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: staticRoutes,
})

router.beforeEach(async (to) => {
    NProgress.configure({ showSpinner: false })
    NProgress.start()
    if (!window.existLoading) {
        loading.show()
        window.existLoading = true
    }

    // 按需动态加载页面的语言包-start
    const config = useConfig()
    await loadRouteLangMessages({
        to,
        defaultLang: config.lang.defaultLang,
        backendApps,
        langAutoLoadMap,
        loadLangHandle: window.loadLangHandle,
        mergeMessage,
    })
    // 动态加载语言包-end
})

// 路由加载后
router.afterEach(() => {
    if (window.existLoading) {
        loading.hide()
    }
    NProgress.done()
})

export default router
