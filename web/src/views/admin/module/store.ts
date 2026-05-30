import { reactive } from 'vue'
import { uuid } from '/@/utils/random'
import type { moduleState } from './types'

export const state = reactive<moduleState>({
    loading: {
        table: true,
        common: false,
        install: false,
        goodsInfo: false,
    },
    dialog: {
        common: false,
        goodsInfo: false,
    },
    table: {
        remark: '',
        modules: [],
        modulesEbak: [],
        category: [],
        onlyLocal: false,
        indexLoaded: false,
        params: {
            quickSearch: '',
            activeTab: 'all',
        },
    },
    goodsInfo: {},
    common: {
        uid: '',
        moduleState: 0,
        quickClose: false,
        type: 'loading',
        dialogTitle: '',
        fileConflict: [],
        dependConflict: [],
        loadingTitle: 'init',
        loadingComponentKey: uuid(),
        waitInstallDepend: [],
        dependInstallState: 'none',
        disableConflictFile: [],
        disableDependConflict: [],
        disableParams: {},
        update: false,
        requiresWebRebuild: false,
        versions: [],
    },
    sysVersion: '',
    nuxtVersion: '',
    installedModule: [],
    installedModuleUids: [],
    installedModuleVersions: [],
})
