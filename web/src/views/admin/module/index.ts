import { ElNotification } from 'element-plus'
import { isArray } from 'lodash-es'
import { nextTick } from 'vue'
import { state } from './store'
import { moduleInstallState, type moduleState } from './types'
import { changeState, getInstallState, index, info, modules, postInstallModule, preDownload } from '/@/api/admin/module'
import { i18n } from '/@/lang/index'
import router from '/@/router/index'
import { SYSTEM_ZINDEX } from '/@/stores/constant/common'
import { taskStatus } from '/@/stores/constant/terminalTaskStatus'
import type { UserInfo } from '/@/stores/interface'
import { useTerminal } from '/@/stores/terminal'
import { useXAccount } from '/@/stores/xAccount'
import { uuid } from '/@/utils/random'
import { changeListenDirtyFileSwitch, closeHotUpdate } from '/@/utils/vite'
import localModuleLogo from '/@/assets/logo.png'

export const loadData = () => {
    state.loading.table = true
    if (!state.table.indexLoaded) {
        loadIndex().then(() => {
            getModules()
        })
    } else {
        getModules()
    }
}

export const onRefreshTableData = () => {
    state.table.indexLoaded = false
    for (const key in state.table.modulesEbak) {
        state.table.modulesEbak[key] = undefined
    }
    loadData()
}

const loadIndex = () => {
    return index().then((res) => {
        state.table.indexLoaded = true
        state.sysVersion = res.data.sysVersion
        state.nuxtVersion = res.data.nuxtVersion
        state.installedModule = res.data.installed

        const installedModuleUids: string[] = []
        const installedModuleVersions: { uid: string; version: string }[] = []
        if (res.data.installed) {
            state.installedModule.forEach((item) => {
                installedModuleUids.push(item.uid)
                installedModuleVersions.push({
                    uid: item.uid,
                    version: item.version,
                })
            })
            state.installedModuleUids = installedModuleUids
            state.installedModuleVersions = installedModuleVersions
        }
    })
}

const getModules = () => {
    if (typeof state.table.modulesEbak[state.table.params.activeTab] != 'undefined') {
        state.table.modules[state.table.params.activeTab] = modulesOnlyLocalHandle(state.table.modulesEbak[state.table.params.activeTab])
        state.loading.table = false
        return
    }
    const params: anyObj = {}
    for (const key in state.table.params) {
        if (state.table.params[key] != '') {
            params[key] = state.table.params[key]
        }
    }
    const moduleUids: string[] = []
    params['installed'] = state.installedModuleVersions
    params['sysVersion'] = state.sysVersion
    modules(params)
        .then((res) => {
            if (params.activeTab == 'all') {
                res.data.rows.forEach((item: anyObj) => {
                    moduleUids.push(item.uid)
                })

                state.installedModule.forEach((item) => {
                    if (moduleUids.indexOf(item.uid) === -1) {
                        if (state.table.params.quickSearch) {
                            if (item.title.includes(state.table.params.quickSearch)) res.data.rows.push(item)
                        } else {
                            res.data.rows.push(item)
                        }
                    }
                })
            }

            state.table.remark = res.data.remark
            state.table.modulesEbak[params.activeTab] = res.data.rows.map((item: anyObj) => {
                const idx = state.installedModuleUids.indexOf(item.uid)
                if (idx !== -1) {
                    item.state = state.installedModule[idx].state
                    item.title = state.installedModule[idx].title
                    item.version = state.installedModule[idx].version
                    item.website = state.installedModule[idx].website
                    item.stateTag = moduleStatus(item.state)

                    if (!isArray(item.tags)) item.tags = []
                    item.tags.push({
                        name: `${moduleStatus(item.state).text} v${state.installedModule[idx].version}`,
                        type: item.stateTag.type || 'primary',
                    })
                } else {
                    item.state = 0
                }

                if (item.new_version && item.tags) {
                    item.tags.push({
                        name: i18n.global.t('module.New version'),
                        type: 'danger',
                    })
                }

                return item
            })
            state.table.modules[params.activeTab] = modulesOnlyLocalHandle(state.table.modulesEbak[params.activeTab])
            state.table.category = res.data.category
        })
        .finally(() => {
            state.loading.table = false
        })
}

export const showInfo = (uid: string) => {
    state.dialog.goodsInfo = true
    state.loading.goodsInfo = true

    const localItem = state.installedModule.find((item) => {
        return item.uid == uid
    })

    info({
        uid: uid,
        localVersion: localItem?.version,
        sysVersion: state.sysVersion,
    })
        .then((res) => {
            if (localItem) {
                if (res.data.info.type == 'local') {
                    res.data.info = localItem
                    res.data.info.images = [localModuleLogo]
                    res.data.info.type = 'local' // 纯本地模块
                } else {
                    res.data.info.type = 'online'
                    res.data.info.state = localItem.state
                    res.data.info.version = localItem.version
                }
                res.data.info.enable = localItem.state === moduleInstallState.DISABLE ? false : true
            } else {
                res.data.info.state = 0
                res.data.info.type = 'online'
            }
            state.goodsInfo = res.data.info
        })
        .finally(() => {
            state.loading.goodsInfo = false
        })
}

export const showCommonLoading = (loadingTitle: moduleState['common']['loadingTitle']) => {
    state.common.type = 'loading'
    state.common.loadingTitle = loadingTitle
    state.common.loadingComponentKey = uuid()
    state.common.requiresWebRebuild = false
}

export const showModuleChangeDone = (uid: string, moduleState: moduleInstallState, requiresWebRebuild: boolean) => {
    state.dialog.goodsInfo = false
    state.common.uid = uid
    state.common.type = 'done'
    state.common.dialogTitle = moduleState === moduleInstallState.UNINSTALLED ? i18n.global.t('module.uninstall') : i18n.global.t('Disable')
    state.common.moduleState = moduleState
    state.common.dependInstallState = 'none'
    state.common.waitInstallDepend = []
    state.common.requiresWebRebuild = requiresWebRebuild
    nextTick(() => {
        state.dialog.common = true
    })
}

/**
 * 模块预安装
 */
export const onPreInstallModule = (uid: string, id: number, needGetInstallableVersion: boolean, update: boolean = false) => {
    const xAccount = useXAccount()
    if (!xAccount.getToken('auth')) {
        ElNotification({
            type: 'warning',
            message: i18n.global.t('module.Please login to the module market account first'),
            zIndex: SYSTEM_ZINDEX,
        })
        return
    }

    state.dialog.common = true
    showCommonLoading('init')
    state.common.dialogTitle = i18n.global.t('module.Install')

    const nextStep = (moduleState: number) => {
        if (needGetInstallableVersion) {
            showCommonLoading('getInstallableVersion')
            preDownload({
                uid,
                versionId: id,
                sysVersion: state.sysVersion,
                nuxtVersion: state.nuxtVersion,
                installed: state.installedModuleUids,
            })
                .then((res) => {
                    state.common.uid = uid
                    state.common.update = update
                    state.common.type = 'selectVersion'
                    state.common.dialogTitle = i18n.global.t('module.Select Version')
                    state.common.versions = res.data.versions

                    state.dialog.goodsInfo = false
                })
                .catch(() => {
                    state.dialog.common = false
                })
        } else {
            // 立即安装（上传安装、继续安装）
            showCommonLoading(moduleState === moduleInstallState.UNINSTALLED ? 'download' : 'install')
            execInstall(uid, id, '', update)

            state.dialog.goodsInfo = false
        }
    }

    if (update) {
        nextStep(moduleInstallState.DISABLE)
    } else {
        getInstallState(uid).then((res) => {
            if (
                res.data.state === moduleInstallState.INSTALLED ||
                res.data.state === moduleInstallState.DISABLE ||
                res.data.state === moduleInstallState.DIRECTORY_OCCUPIED
            ) {
                ElNotification({
                    type: 'error',
                    message:
                        res.data.state === moduleInstallState.INSTALLED || res.data.state === moduleInstallState.DISABLE
                            ? i18n.global.t('module.Installation cancelled because module already exists!')
                            : i18n.global.t('module.Installation cancelled because the directory required by the module is occupied!'),
                })
                state.dialog.common = false
                return
            }

            nextStep(res.data.state)
        })
    }
}

/**
 * 执行安装请求，还包含启用、安装时的冲突处理
 */
export const execInstall = (uid: string, id: number, version: string = '', update: boolean = false, extend: anyObj = {}) => {
    postInstallModule(uid, id, version, update, extend)
        .then((res) => {
            state.common.uid = res.data.data?.uid || uid
            state.common.requiresWebRebuild = !!res.data.data?.requires_web_rebuild
            state.common.dialogTitle = i18n.global.t('module.Installation complete')
            state.common.moduleState = moduleInstallState.INSTALLED
            state.common.type = 'done'
            onRefreshTableData()
        })
        .catch((res) => {
            if (res.code == -1) {
                state.common.uid = res.data.uid
                state.common.type = 'installConflict'
                state.common.dialogTitle = i18n.global.t('module.A conflict is found Please handle it manually')
                state.common.fileConflict = res.data.fileConflict
                state.common.dependConflict = res.data.dependConflict
            } else if (res.code == -2) {
                state.common.type = 'done'
                state.common.uid = res.data.uid
                state.common.requiresWebRebuild = !!res.data.requires_web_rebuild
                state.common.dialogTitle = i18n.global.t('module.Wait for dependent installation')
                state.common.moduleState = moduleInstallState.DEPENDENT_WAIT_INSTALL
                state.common.waitInstallDepend = [...res.data.wait_install]
                state.common.dependInstallState = 'executing'
                const terminal = useTerminal()
                if (res.data.wait_install.includes('npm_dependent_wait_install')) {
                    const taskWillStart = terminal.addTaskPM('web-install', true, 'module-install:' + res.data.uid, (res: number) => {
                        terminalTaskExecComplete(res, 'npm_dependent_wait_install')
                    })
                    if (!taskWillStart) terminalTaskExecComplete(taskStatus.Failed, 'npm_dependent_wait_install')
                }
                if (res.data.wait_install.includes('nuxt_npm_dependent_wait_install')) {
                    const taskWillStart = terminal.addTaskPM('nuxt-install', true, 'module-install:' + res.data.uid, (res: number) => {
                        terminalTaskExecComplete(res, 'nuxt_npm_dependent_wait_install')
                    })
                    if (!taskWillStart) terminalTaskExecComplete(taskStatus.Failed, 'nuxt_npm_dependent_wait_install')
                }
                if (res.data.wait_install.includes('composer_dependent_wait_install')) {
                    const taskWillStart = terminal.addTask('composer.update', true, 'module-install:' + res.data.uid, (res: number) => {
                        terminalTaskExecComplete(res, 'composer_dependent_wait_install')
                    })
                    if (!taskWillStart) terminalTaskExecComplete(taskStatus.Failed, 'composer_dependent_wait_install')
                }
            } else if (res.code == 0) {
                ElNotification({
                    type: 'error',
                    message: res.msg,
                    zIndex: SYSTEM_ZINDEX,
                })
                state.dialog.common = false
                onRefreshTableData()
            }
        })
        .finally(() => {
            state.loading.common = false
        })
}

const terminalTaskExecComplete = (res: number, type: string) => {
    if (res == taskStatus.Success) {
        state.common.waitInstallDepend = state.common.waitInstallDepend.filter((depend: string) => {
            return depend != type
        })
        if (state.common.waitInstallDepend.length == 0) {
            state.common.dependInstallState = 'success'

            if (router.currentRoute.value.name === 'moduleStore/moduleStore') {
                onRefreshTableData()
            }
        }
    } else {
        const terminal = useTerminal()
        terminal.toggle(true)
        state.common.dependInstallState = 'fail'

        if (router.currentRoute.value.name === 'moduleStore/moduleStore') {
            onRefreshTableData()
        }
    }

    if (router.currentRoute.value.name === 'moduleStore/moduleStore') {
        closeHotUpdate('modules')
    }
}

export const onDisable = (confirmConflict = false) => {
    state.loading.common = true

    if (confirmConflict) {
        const dependConflict: anyObj = {}
        for (const key in state.common.disableDependConflict) {
            if (state.common.disableDependConflict[key]['solution'] != 'delete') {
                continue
            }
            if (typeof dependConflict[state.common.disableDependConflict[key].env] == 'undefined') {
                dependConflict[state.common.disableDependConflict[key].env] = []
            }
            dependConflict[state.common.disableDependConflict[key].env].push(state.common.disableDependConflict[key].depend)
        }
        state.common.disableParams['confirmConflict'] = 1
        state.common.disableParams['dependConflictSolution'] = dependConflict
    }

    changeState(state.common.disableParams)
        .then((res) => {
            showModuleChangeDone(
                state.common.disableParams.uid || state.goodsInfo.uid || '',
                moduleInstallState.DISABLE,
                !!res.data.info?.requires_web_rebuild
            )
            onRefreshTableData()
        })
        .catch((res) => {
            if (res.code == -1) {
                state.dialog.common = true
                state.common.dialogTitle = i18n.global.t('module.Deal with conflict')
                state.common.type = 'disableConfirmConflict'
                state.common.disableDependConflict = res.data.dependConflict
                if (res.data.conflictFile && res.data.conflictFile.length) {
                    const conflictFile = []
                    for (const key in res.data.conflictFile) {
                        conflictFile.push({
                            file: res.data.conflictFile[key],
                        })
                    }
                    state.common.disableConflictFile = conflictFile
                }
            } else if (res.code == -2) {
                state.dialog.common = true
                const commandsData = {
                    type: 'disable',
                    commands: res.data.wait_install,
                    uid: res.data.uid,
                    requiresWebRebuild: !!res.data.requires_web_rebuild,
                }
                state.common.uid = state.goodsInfo.uid
                execCommand(commandsData)
            } else if (res.code == -3) {
                onPreInstallModule(state.goodsInfo.uid, state.goodsInfo.purchased, true, true)
            } else {
                ElNotification({
                    type: 'error',
                    message: res.msg,
                    zIndex: SYSTEM_ZINDEX,
                })
                if (state.common.disableParams && state.common.disableParams.uid) {
                    showInfo(state.common.disableParams.uid)
                } else {
                    onRefreshTableData()
                }
            }
        })
        .finally(() => {
            state.loading.common = false
        })
}

export const onEnable = (uid: string) => {
    state.loading.common = true
    changeState({
        uid: uid,
        state: 1,
    })
        .then(() => {
            state.dialog.common = true
            showCommonLoading('init')
            state.common.dialogTitle = i18n.global.t('Enable')

            execInstall(uid, 0)
            state.dialog.goodsInfo = false
        })
        .catch((res) => {
            ElNotification({
                type: 'error',
                message: res.msg,
                zIndex: SYSTEM_ZINDEX,
            })
            state.loading.common = false
        })
}

const modulesOnlyLocalHandle = (modules: anyObj) => {
    if (!state.table.onlyLocal) return modules
    return modules.filter((item: anyObj) => {
        return item.installed
    })
}

export const execCommand = (data: anyObj) => {
    if (data.type == 'disable') {
        state.dialog.common = true
        state.common.type = 'done'
        state.common.dialogTitle = i18n.global.t('module.Wait for dependent installation')
        state.common.moduleState = moduleInstallState.DISABLE
        state.common.uid = data.uid || state.common.uid
        state.common.requiresWebRebuild = !!data.requiresWebRebuild
        state.common.dependInstallState = 'executing'
        state.common.waitInstallDepend = []
        const terminal = useTerminal()
        data.commands.forEach((item: anyObj) => {
            state.common.waitInstallDepend.push(item.type)
            if (item.pm) {
                if (item.command == 'web-install') {
                    changeListenDirtyFileSwitch(false)
                }
                const taskWillStart = terminal.addTaskPM(item.command, true, '', (res: number) => {
                    terminalTaskExecComplete(res, item.type)
                    if (item.command == 'web-install') {
                        changeListenDirtyFileSwitch(true)
                    }
                })
                if (!taskWillStart) {
                    terminalTaskExecComplete(taskStatus.Failed, item.type)
                    if (item.command == 'web-install') {
                        changeListenDirtyFileSwitch(true)
                    }
                }
            } else {
                const taskWillStart = terminal.addTask(item.command, true, '', (res: number) => {
                    terminalTaskExecComplete(res, item.type)
                })
                if (!taskWillStart) terminalTaskExecComplete(taskStatus.Failed, item.type)
            }
        })
    }
}

export const specificUserName = (userInfo: Partial<UserInfo>) => {
    return userInfo.nickname + '（' + (userInfo.email || userInfo.mobile || 'ID:' + userInfo.id) + '）'
}

export const moduleStatus = (state: number) => {
    switch (state) {
        case moduleInstallState.INSTALLED:
            return {
                type: '',
                text: i18n.global.t('module.installed'),
            }
        case moduleInstallState.WAIT_INSTALL:
            return {
                type: 'success',
                text: i18n.global.t('module.Wait for installation'),
            }
        case moduleInstallState.CONFLICT_PENDING:
            return {
                type: 'danger',
                text: i18n.global.t('module.Conflict pending'),
            }
        case moduleInstallState.DEPENDENT_WAIT_INSTALL:
            return {
                type: 'warning',
                text: i18n.global.t('module.Dependency to be installed'),
            }
        case moduleInstallState.DISABLE:
            return {
                type: 'warning',
                text: i18n.global.t('Disable'),
            }
        default:
            return {
                type: 'info',
                text: i18n.global.t('Unknown'),
            }
    }
}
