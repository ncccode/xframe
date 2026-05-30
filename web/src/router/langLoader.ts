interface RouteLangLocation {
    path: string
    fullPath: string
    name?: string | symbol | null
}

interface RouteLangBackendApp {
    name: string
    baseRoutePath: string
}

interface ResolveRouteLangLoadPathsOptions {
    to: RouteLangLocation
    defaultLang: string
    backendApps?: RouteLangBackendApp[]
    langAutoLoadMap: Record<string, string[]>
}

interface LoadRouteLangMessagesOptions extends ResolveRouteLangLoadPathsOptions {
    loadLangHandle: Record<string, any>
    mergeMessage: (message: anyObj, pathName?: string) => void
}

export function resolveRouteLangLoadPaths(options: ResolveRouteLangLoadPathsOptions) {
    const { to, defaultLang, langAutoLoadMap } = options
    const loadPath: string[] = []
    const backendApp = getRouteBackendApp(options)
    const appName = backendApp.name
    const prefix = getLangPrefix(defaultLang, appName)

    if (to.path in langAutoLoadMap) {
        loadPath.push(...langAutoLoadMap[to.path as keyof typeof langAutoLoadMap])
    }

    if (to.path === backendApp.baseRoutePath || to.path.startsWith(backendApp.baseRoutePath + '/')) {
        const backendPath = to.path.slice(to.path.indexOf(backendApp.baseRoutePath) + backendApp.baseRoutePath.length)
        if (backendPath) loadPath.push(prefix + backendPath + '.ts')
    }

    if (to.name) {
        loadPath.push(prefix + '/' + to.name.toString() + '.ts')
    }

    loadPath.push(getPublicMessagePath(defaultLang, appName))

    return Array.from(new Set(loadPath.map((path) => path.replaceAll('${lang}', defaultLang))))
}

export async function loadRouteLangMessages(options: LoadRouteLangMessagesOptions) {
    const { defaultLang, loadLangHandle, mergeMessage } = options
    const appName = getRouteBackendApp(options).name
    const prefix = getLangPrefix(defaultLang, appName)
    const publicMessagePath = getPublicMessagePath(defaultLang, appName)
    let loadPath = resolveRouteLangLoadPaths(options)

    if (!loadLangHandle.publicMessageLoaded) loadLangHandle.publicMessageLoaded = []
    if (loadLangHandle.publicMessageLoaded.includes(publicMessagePath)) {
        loadPath = loadPath.filter((path) => path !== publicMessagePath)
    } else {
        loadLangHandle.publicMessageLoaded.push(publicMessagePath)
    }

    await Promise.all(
        loadPath.map(async (path) => {
            const loader = loadLangHandle[path]
            if (typeof loader !== 'function') return

            try {
                const res = await loader()
                mergeMessage(res.default, getLangPathName(path, prefix))
            } catch (error) {
                console.error(error)
            }
        })
    )
}

function getLangPrefix(defaultLang: string, appName = 'admin') {
    return './' + appName + '/' + defaultLang
}

function getPublicMessagePath(defaultLang: string, appName = 'admin') {
    return getLangPrefix(defaultLang, appName) + '.ts'
}

function getRouteBackendApp(options: ResolveRouteLangLoadPathsOptions): RouteLangBackendApp {
    const pathOnly = (options.to.fullPath || options.to.path || '').split(/[?#]/)[0]
    const apps = options.backendApps ?? []
    const app = apps
        .filter((item) => pathOnly === item.baseRoutePath || pathOnly.startsWith(item.baseRoutePath + '/'))
        .sort((a, b) => b.baseRoutePath.length - a.baseRoutePath.length)[0]
    if (app) return app

    return apps.find((item) => item.name === 'admin') ?? { name: 'admin', baseRoutePath: '/admin' }
}

function getLangPathName(path: string, prefix: string) {
    const start = path.lastIndexOf(prefix) + prefix.length + 1
    const end = path.lastIndexOf('.')
    if (start > end) return ''
    return path.slice(start, end)
}
