export enum moduleInstallState {
    UNINSTALLED,
    INSTALLED,
    WAIT_INSTALL,
    CONFLICT_PENDING,
    DEPENDENT_WAIT_INSTALL,
    DIRECTORY_OCCUPIED,
    DISABLE,
}

export interface moduleInfo {
    uid: string
    title: string
    version: string
    state: number
    website: string
    installed?: boolean
    installSource?: string
    type?: string
    logo?: string
    images?: string[]
    tags?: anyObj[]
    stateTag: {
        type: string
        text: string
    }
}

export interface moduleState {
    loading: {
        table: boolean
        common: boolean
        install: boolean
        goodsInfo: boolean
    }
    dialog: {
        common: boolean
        goodsInfo: boolean
    }
    table: {
        remark: string
        modules: anyObj
        modulesEbak: anyObj
        category: anyObj
        onlyLocal: boolean
        indexLoaded: boolean
        params: anyObj
    }
    goodsInfo: anyObj
    common: {
        uid: string
        moduleState: number
        quickClose: boolean
        type: 'loading' | 'installConflict' | 'done' | 'disableConfirmConflict' | 'uploadInstall' | 'selectVersion'
        dialogTitle: string
        fileConflict: anyObj[]
        dependConflict: anyObj[]
        loadingTitle: 'init' | 'download' | 'install' | 'getInstallableVersion'
        loadingComponentKey: string
        waitInstallDepend: string[]
        dependInstallState: 'none' | 'executing' | 'success' | 'fail'
        disableConflictFile: { file: string }[]
        disableDependConflict: anyObj[]
        disableParams: anyObj
        update: boolean
        requiresWebRebuild: boolean
        versions: anyObj[]
    }
    sysVersion: string
    nuxtVersion: string
    installedModule: moduleInfo[]
    installedModuleUids: string[]
    installedModuleVersions: { uid: string; version: string }[]
}
