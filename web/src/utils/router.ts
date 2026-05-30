import { ElNotification } from 'element-plus'
import { compact, reverse } from 'lodash-es'
import type { RouteLocationRaw, RouteRecordRaw } from 'vue-router'
import { isNavigationFailure, NavigationFailureType } from 'vue-router'
import { i18n } from '/@/lang/index'
import router from '/@/router/index'
import adminBaseRoute, { adminBaseRoutePath } from '/@/router/static/adminBase'
import { useAdminInfo } from '/@/stores/adminInfo'
import { useConfig } from '/@/stores/config'
import { useNavTabs } from '/@/stores/navTabs'
import { closeShade } from '/@/utils/pageShade'

export interface BackendAppConfig {
    name: string
    baseRoutePath: string
    baseRouteName: string
    loginRouteName: string
    loginRoutePath: string
    tokenHeader: string
    refreshTokenType: string
    viewsComponent: Record<string, any>
    getAuthToken: () => string
    getRefreshToken: () => string
    setAuthToken: (token: string) => void
    removeToken: () => void
}

interface StaticBackendRouteModule {
    default?: RouteRecordRaw | RouteRecordRaw[]
    backendApp?: BackendAppConfig
    [key: string]: unknown
}

interface StaticBackendRouteApp {
    name: string
    baseRoutePath: string
}

const staticBackendRouteFiles: Record<string, StaticBackendRouteModule> = import.meta.glob('/src/router/static/*.ts', { eager: true })
const baseRoutePathExportSuffix = 'BaseRoutePath'
const adminViewsComponent = import.meta.glob('/src/views/admin/**/*.vue')

export const adminBackendApp: BackendAppConfig = {
    name: 'admin',
    baseRoutePath: adminBaseRoutePath,
    baseRouteName: adminBaseRoute.name as string,
    loginRouteName: 'adminLogin',
    loginRoutePath: adminBaseRoutePath + '/login',
    tokenHeader: 'xtoken',
    refreshTokenType: 'admin-refresh',
    viewsComponent: adminViewsComponent,
    getAuthToken: () => useAdminInfo().getToken(),
    getRefreshToken: () => useAdminInfo().getToken('refresh'),
    setAuthToken: (token: string) => useAdminInfo().setToken(token, 'auth'),
    removeToken: () => useAdminInfo().removeToken(),
}

/**
 * 导航失败有错误消息的路由push
 * @param to — 导航位置，同 router.push
 */
export const routePush = async (to: RouteLocationRaw) => {
    try {
        const failure = await router.push(to)
        if (isNavigationFailure(failure, NavigationFailureType.aborted)) {
            ElNotification({
                message: i18n.global.t('utils.Navigation failed, navigation guard intercepted!'),
                type: 'error',
            })
        } else if (isNavigationFailure(failure, NavigationFailureType.duplicated)) {
            ElNotification({
                message: i18n.global.t('utils.Navigation failed, it is at the navigation target position!'),
                type: 'warning',
            })
        }
    } catch (error) {
        ElNotification({
            message: i18n.global.t('utils.Navigation failed, invalid route!'),
            type: 'error',
        })
        console.error(error)
    }
}

/**
 * 获取第一个菜单
 */
export const getFirstRoute = (routes: RouteRecordRaw[]): false | RouteRecordRaw => {
    const routerPaths: string[] = []
    const routers = router.getRoutes()
    routers.forEach((item) => {
        if (item.path) routerPaths.push(item.path)
    })
    let find: boolean | RouteRecordRaw = false
    for (const key in routes) {
        if (routes[key].meta?.type == 'menu' && routerPaths.indexOf(getRoutePathWithoutQuery(routes[key].path)) !== -1) {
            return routes[key]
        } else if (routes[key].children && routes[key].children?.length) {
            find = getFirstRoute(routes[key].children!)
            if (find) return find
        }
    }
    return find
}

const getRoutePathWithoutQuery = (path: string) => {
    return path.split(/[?#]/)[0]
}

const normalizeBaseRoutePath = (path: string) => {
    const trimPath = path.replace(/^\/+|\/+$/g, '')
    return trimPath ? '/' + trimPath : ''
}

const getBackendAppNameByBaseRoutePathExport = (exportName: string) => {
    const name = exportName.slice(0, -baseRoutePathExportSuffix.length)
    return name.charAt(0).toLowerCase() + name.slice(1)
}

/**
 * 扫描静态后台路由模块中的 xxxBaseRoutePath 入口路径
 */
export const getStaticBackendRouteApps = (): StaticBackendRouteApp[] => {
    const apps: StaticBackendRouteApp[] = []
    for (const key in staticBackendRouteFiles) {
        const routeModule = staticBackendRouteFiles[key]
        for (const exportName in routeModule) {
            if (!exportName.endsWith(baseRoutePathExportSuffix) || typeof routeModule[exportName] !== 'string') continue

            const name = getBackendAppNameByBaseRoutePathExport(exportName)
            const baseRoutePath = routeModule[exportName]
            if (!name || !baseRoutePath) continue

            apps.push({
                name,
                baseRoutePath: normalizeBaseRoutePath(baseRoutePath),
            })
        }
    }

    return apps
}

export const getStaticBackendBaseRoutePath = (routeName: string, fallback = '') => {
    return getStaticBackendRouteApps().find((item) => item.name === routeName)?.baseRoutePath ?? normalizeBaseRoutePath(fallback)
}

const getBackendBaseRoutePath = (backendApp: { name: string; baseRouteName: string; baseRoutePath: string }) => {
    const routeApps = getStaticBackendRouteApps()
    return (
        routeApps.find((item) => item.name === backendApp.name || item.name === backendApp.baseRouteName)?.baseRoutePath ??
        normalizeBaseRoutePath(backendApp.baseRoutePath)
    )
}

const normalizeBackendLoginRoutePath = (loginRoutePath: string, oldBaseRoutePath: string, newBaseRoutePath: string) => {
    const oldBasePath = normalizeBaseRoutePath(oldBaseRoutePath)
    if (!oldBasePath || oldBasePath === newBaseRoutePath || !loginRoutePath.startsWith(oldBasePath + '/')) return loginRoutePath

    return newBaseRoutePath + loginRoutePath.slice(oldBasePath.length)
}

const normalizeBackendApp = (backendApp: BackendAppConfig): BackendAppConfig => {
    const baseRoutePath = getBackendBaseRoutePath(backendApp)
    return {
        ...backendApp,
        baseRoutePath,
        loginRoutePath: normalizeBackendLoginRoutePath(backendApp.loginRoutePath, backendApp.baseRoutePath, baseRoutePath),
    }
}

export const backendApps: BackendAppConfig[] = [normalizeBackendApp(adminBackendApp)]

for (const key in staticBackendRouteFiles) {
    const app = staticBackendRouteFiles[key].backendApp
    if (!app || !app.name || !app.baseRoutePath || backendApps.some((item) => item.name === app.name)) continue

    backendApps.push(normalizeBackendApp(app))
}

export const getBackendAppByName = (name: string): BackendAppConfig | undefined => {
    return backendApps.find((app) => app.name === name)
}

export const getBackendAppByPath = (path = ''): BackendAppConfig | undefined => {
    const pathOnly = (path || '').split(/[?#]/)[0]
    return backendApps
        .filter((app) => pathOnly === app.baseRoutePath || pathOnly.startsWith(app.baseRoutePath + '/'))
        .sort((a, b) => b.baseRoutePath.length - a.baseRoutePath.length)[0]
}

export const getBackendAppByRequestUrl = (url = '', currentPath = ''): BackendAppConfig | undefined => {
    let path = url
    try {
        if (/^https?:\/\//i.test(url)) {
            path = new URL(url).pathname
        }
    } catch {
        path = url
    }

    const requestApp = getBackendAppByPath(path)
    if (requestApp) return requestApp

    return getBackendAppByPath(currentPath)
}

export const isBackendApp = (name: string, path = ''): boolean => {
    const app = getBackendAppByPath(path)
    return app?.name === name
}

export const getBackendPublicMessagePath = (lang: string, appName: string) => `./${appName}/${lang}.ts`

/**
 * 打开侧边菜单
 * @param menu 菜单数据
 */
export const onClickMenu = (menu: RouteRecordRaw) => {
    switch (menu.meta?.menu_type) {
        case 'iframe':
        case 'tab':
            routePush(menu.path)
            break
        case 'link':
            window.open(menu.path, '_blank')
            break

        default:
            ElNotification({
                message: i18n.global.t('utils.Navigation failed, the menu type is unrecognized!'),
                type: 'error',
            })
            break
    }

    const config = useConfig()
    if (config.layout.shrink) {
        closeShade(() => {
            config.setLayout('menuCollapse', true)
        })
    }
}

/**
 * 处理后台的路由
 */
export const handleAdminRoute = (routes: any) => {
    handleBackendRoute(adminBackendApp.name, routes)
}

/**
 * 处理后台应用路由
 */
export const handleBackendRoute = (appName: string, routes: any) => {
    const backendApp = getBackendAppByName(appName) ?? adminBackendApp
    const baseRoutePath = getBackendBaseRoutePath(backendApp)
    addRouteAll(backendApp.viewsComponent, routes, backendApp.baseRouteName)
    const menuBaseRoute = baseRoutePath + '/'

    // 更新stores中的路由菜单数据
    const navTabs = useNavTabs()
    navTabs.setTabsViewRoutes(handleMenuRule(routes, menuBaseRoute), baseRoutePath)
    navTabs.fillAuthNode(handleAuthNode(routes, menuBaseRoute))
}

/**
 * 处理用户后台的路由
 */
export const handleUserRoute = (routes: any) => {
    handleBackendRoute('user', routes)
}

/**
 * 获取菜单的paths
 */
export const getMenuPaths = (menus: RouteRecordRaw[]): string[] => {
    let menuPaths: string[] = []
    menus.forEach((item) => {
        menuPaths.push(item.path)
        if (item.children && item.children.length > 0) {
            menuPaths = menuPaths.concat(getMenuPaths(item.children))
        }
    })
    return menuPaths
}

/**
 * 获取菜单唯一标识
 * @param menu 菜单数据
 * @param prefix 前缀
 */
export const getMenuKey = (menu: RouteRecordRaw, prefix = '') => {
    if (prefix === '') {
        prefix = menu.path
    }
    return `${prefix}-${menu.name as string}-${menu.meta && menu.meta.id ? menu.meta.id : ''}`
}

/**
 * 后台菜单处理
 */
const handleMenuRule = (routes: any, pathPrefix = '/', type = ['menu', 'menu_dir']) => {
    const menuRule: RouteRecordRaw[] = []
    for (const key in routes) {
        if (routes[key].extend == 'add_rules_only') {
            continue
        }
        if (!type.includes(routes[key].type)) {
            continue
        }
        if (routes[key].type == 'menu_dir' && routes[key].children && !routes[key].children.length) {
            continue
        }
        if (
            ['route', 'menu', 'nav_user_menu', 'nav'].includes(routes[key].type) &&
            ((routes[key].menu_type == 'tab' && !routes[key].component) || (['link', 'iframe'].includes(routes[key].menu_type) && !routes[key].url))
        ) {
            continue
        }
        const currentPath = ['link', 'iframe'].includes(routes[key].menu_type) ? routes[key].url : pathPrefix + routes[key].path
        let children: RouteRecordRaw[] = []
        if (routes[key].children && routes[key].children.length > 0) {
            children = handleMenuRule(routes[key].children, pathPrefix, type)
        }
        menuRule.push({
            path: currentPath,
            name: routes[key].name,
            component: routes[key].component,
            meta: {
                id: routes[key].id,
                title: routes[key].title,
                icon: routes[key].icon,
                keepalive: routes[key].keepalive,
                menu_type: routes[key].menu_type,
                type: routes[key].type,
            },
            children: children,
        })
    }
    return menuRule
}

/**
 * 处理权限节点
 * @param routes 路由数据
 * @param prefix 节点前缀
 * @returns 组装好的权限节点
 */
const handleAuthNode = (routes: any, prefix = '/') => {
    const authNode: Map<string, string[]> = new Map([])
    assembleAuthNode(routes, authNode, prefix, prefix)
    return authNode
}
const assembleAuthNode = (routes: any, authNode: Map<string, string[]>, prefix = '/', parent = '/') => {
    const authNodeTemp = []
    for (const key in routes) {
        if (routes[key].type == 'button') authNodeTemp.push(prefix + routes[key].name)
        if (routes[key].children && routes[key].children.length > 0) {
            assembleAuthNode(routes[key].children, authNode, prefix, prefix + routes[key].name)
        }
    }
    if (authNodeTemp && authNodeTemp.length > 0) {
        authNode.set(parent, authNodeTemp)
    }
}

/**
 * 动态添加路由-带子路由
 * @param viewsComponent
 * @param routes
 * @param parentName
 * @param analyticRelation 根据 name 从已注册路由分析父级路由
 */
export const addRouteAll = (viewsComponent: Record<string, any>, routes: any, parentName: string, analyticRelation = false) => {
    for (const idx in routes) {
        if (routes[idx].extend == 'add_menu_only') {
            continue
        }
        if ((routes[idx].menu_type == 'tab' && viewsComponent[routes[idx].component]) || routes[idx].menu_type == 'iframe') {
            addRouteItem(viewsComponent, routes[idx], parentName, analyticRelation)
        }

        if (routes[idx].children && routes[idx].children.length > 0) {
            addRouteAll(viewsComponent, routes[idx].children, parentName, analyticRelation)
        }
    }
}

/**
 * 动态添加路由
 * @param viewsComponent
 * @param route
 * @param parentName
 * @param analyticRelation 根据 name 从已注册路由分析父级路由
 */
export const addRouteItem = (viewsComponent: Record<string, any>, route: any, parentName: string, analyticRelation: boolean) => {
    let path = '',
        component
    if (route.menu_type == 'iframe') {
        const backendApp = getBackendAppByPath(router.currentRoute.value.fullPath || router.currentRoute.value.path) ?? adminBackendApp
        const baseRoutePath = getBackendBaseRoutePath(backendApp)
        path = baseRoutePath + '/iframe/' + encodeURIComponent(route.url)
        component = () => import('/@/layouts/common/router-view/iframe.vue')
    } else {
        path = parentName ? getRoutePathWithoutQuery(route.path) : '/' + getRoutePathWithoutQuery(route.path)
        component = viewsComponent[route.component]
    }

    if (route.menu_type == 'tab' && analyticRelation) {
        const parentNames = getParentNames(route.name)
        if (parentNames.length) {
            for (const key in parentNames) {
                if (router.hasRoute(parentNames[key])) {
                    parentName = parentNames[key]
                    break
                }
            }
        }
    }

    const routeBaseInfo: RouteRecordRaw = {
        path: path,
        name: route.name,
        component: component,
        meta: {
            title: route.title,
            extend: route.extend,
            icon: route.icon,
            keepalive: route.keepalive,
            menu_type: route.menu_type,
            type: route.type,
            url: route.url,
            addtab: true,
        },
    }
    if (parentName) {
        router.addRoute(parentName, routeBaseInfo)
    } else {
        router.addRoute(routeBaseInfo)
    }
}

/**
 * 根据name字符串，获取父级name组合的数组
 * @param name
 */
const getParentNames = (name: string) => {
    const names = compact(name.split('/'))
    const tempNames = []
    const parentNames = []
    for (const key in names) {
        tempNames.push(names[key])
        if (parseInt(key) != names.length - 1) {
            parentNames.push(tempNames.join('/'))
        }
    }
    return reverse(parentNames)
}
