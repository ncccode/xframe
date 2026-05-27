import { useXAccount } from '/@/stores/xAccount'
import { useSiteConfig } from '/@/stores/siteConfig'
import createAxios from '/@/utils/axios'

const storeUrl = '/api/store/'
const moduleControllerUrl = '/admin/module/'

export function index(params: anyObj = {}) {
    return createAxios({
        url: moduleControllerUrl + 'index',
        method: 'get',
        params: params,
    })
}

export function modules(params: anyObj = {}) {
    const siteConfig = useSiteConfig()
    return createAxios({
        url: siteConfig.apiUrl + storeUrl + 'modules',
        method: 'get',
        params: params,
    })
}

export function info(params: anyObj) {
    const xAccount = useXAccount()
    const siteConfig = useSiteConfig()
    return createAxios(
        {
            url: siteConfig.apiUrl + storeUrl + 'info',
            method: 'get',
            params: params,
        },
        {
            anotherToken: xAccount.getToken('auth'),
        }
    )
}

/**
 * 获取模块的可安装版本列表
 */
export function preDownload(data: anyObj) {
    const xAccount = useXAccount()
    const siteConfig = useSiteConfig()
    return createAxios(
        {
            url: siteConfig.apiUrl + storeUrl + 'preDownload',
            method: 'POST',
            data,
        },
        {
            anotherToken: xAccount.getToken('auth'),
        }
    )
}

export function getInstallState(uid: string) {
    return createAxios({
        url: moduleControllerUrl + 'state',
        method: 'get',
        params: {
            uid: uid,
        },
    })
}

export function postInstallModule(uid: string, versionId: number, version: string, update: boolean, extend: anyObj = {}) {
    const xAccount = useXAccount()
    return createAxios(
        {
            url: moduleControllerUrl + 'install',
            method: 'POST',
            data: {
                uid,
                update,
                version,
                versionId,
                token: xAccount.getToken('auth'),
                extend,
            },
            timeout: 3000 * 10,
        },
        {
            showCodeMessage: false,
        }
    )
}

export function postUninstall(uid: string) {
    return createAxios(
        {
            url: moduleControllerUrl + 'uninstall',
            method: 'post',
            params: {
                uid: uid,
            },
        },
        {
            showSuccessMessage: true,
        }
    )
}

export function changeState(params: anyObj) {
    return createAxios(
        {
            url: moduleControllerUrl + 'changeState',
            method: 'post',
            data: params,
        },
        {
            showCodeMessage: false,
        }
    )
}

export function dependentInstallComplete(uid: string) {
    return createAxios({
        url: moduleControllerUrl + 'dependentInstallComplete',
        method: 'post',
        params: {
            uid: uid,
        },
    })
}

export function upload(file: string) {
    const xAccount = useXAccount()
    return createAxios({
        url: moduleControllerUrl + 'upload',
        method: 'post',
        params: {
            file: file,
            token: xAccount.getToken('auth'),
        },
    })
}
