import type { RouteRecordRaw } from 'vue-router'

/**
 * 后台基础路由路径
 * 您可以在后台->系统配置中修改此值；后端会同步前端路由常量，并由 webman 路由把自定义入口映射至 admin 控制器
 */
export const adminBaseRoutePath = '/admin'

/*
 * 后台基础静态路由
 */
const adminBaseRoute: RouteRecordRaw = {
    path: adminBaseRoutePath,
    name: 'admin',
    component: () => import('/@/layouts/admin/index.vue'),
    // 直接重定向到 loading 路由
    redirect: adminBaseRoutePath + '/loading',
    meta: {
        title: `pagesTitle.admin`,
    },
    children: [
        {
            path: 'loading/:to?',
            name: 'adminMainLoading',
            component: () => import('/@/layouts/common/components/loading.vue'),
            meta: {
                title: `pagesTitle.loading`,
            },
        },
    ],
}

export default adminBaseRoute
