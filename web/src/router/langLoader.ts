interface RouteLangLocation {
    path: string
    fullPath: string
    name?: string | symbol | null
}

interface ResolveRouteLangLoadPathsOptions {
    to: RouteLangLocation
    defaultLang: string
    adminBaseRoutePath: string
    langAutoLoadMap: Record<string, string[]>
    isAdminApp: (path: string) => boolean
}

interface LoadRouteLangMessagesOptions extends ResolveRouteLangLoadPathsOptions {
    loadLangHandle: Record<string, any>
    mergeMessage: (message: anyObj, pathName?: string) => void
}

export function resolveRouteLangLoadPaths(options: ResolveRouteLangLoadPathsOptions) {
    const { to, defaultLang, adminBaseRoutePath, langAutoLoadMap, isAdminApp } = options
    const loadPath: string[] = []
    const prefix = getLangPrefix(defaultLang)

    if (to.path in langAutoLoadMap) {
        loadPath.push(...langAutoLoadMap[to.path as keyof typeof langAutoLoadMap])
    }

    if (isAdminApp(to.fullPath)) {
        const adminPath = to.path.slice(to.path.indexOf(adminBaseRoutePath) + adminBaseRoutePath.length)
        if (adminPath) loadPath.push(prefix + adminPath + '.ts')
    }

    if (to.name) {
        loadPath.push(prefix + '/' + to.name.toString() + '.ts')
    }

    loadPath.push(getPublicMessagePath(defaultLang))

    return Array.from(new Set(loadPath.map((path) => path.replaceAll('${lang}', defaultLang))))
}

export async function loadRouteLangMessages(options: LoadRouteLangMessagesOptions) {
    const { defaultLang, loadLangHandle, mergeMessage } = options
    const prefix = getLangPrefix(defaultLang)
    const publicMessagePath = getPublicMessagePath(defaultLang)
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

function getLangPrefix(defaultLang: string) {
    return './admin/' + defaultLang
}

function getPublicMessagePath(defaultLang: string) {
    return getLangPrefix(defaultLang) + '.ts'
}

function getLangPathName(path: string, prefix: string) {
    const start = path.lastIndexOf(prefix) + prefix.length + 1
    const end = path.lastIndexOf('.')
    if (start > end) return ''
    return path.slice(start, end)
}
