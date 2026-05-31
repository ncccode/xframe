import { useAdminInfo } from '/@/stores/adminInfo'
import { useXAccount } from '/@/stores/xAccount'
import { useSiteConfig } from '/@/stores/siteConfig'
import createAxios from '/@/utils/axios'

export const url = '/admin/Index/'

export function index() {
    return createAxios({
        url: url + 'index',
        method: 'get',
    })
}

export function login(method: 'get' | 'post', params: object = {}) {
    return createAxios({
        url: url + 'login',
        data: params,
        method: method,
    })
}

export function logout() {
    const adminInfo = useAdminInfo()
    return createAxios({
        url: url + 'logout',
        method: 'POST',
        data: {
            refreshToken: adminInfo.getToken('refresh'),
        },
    })
}

export function clearCache(type = 'all') {
    return createAxios(
        {
            url: '/admin/Ajax/clearCache',
            method: 'POST',
            data: {
                type,
            },
        },
        {
            showSuccessMessage: true,
        }
    )
}

export function xAccountCheckIn(params: object = {}) {
    const siteConfig = useSiteConfig()
    return createAxios(
        {
            url: siteConfig.apiUrl + '/api/user/checkIn',
            data: params,
            method: 'post',
        },
        {
            showSuccessMessage: true,
        }
    )
}

export function xAccountGetUserInfo() {
    const xAccount = useXAccount()
    const siteConfig = useSiteConfig()
    return createAxios(
        {
            url: siteConfig.apiUrl + '/api/user/info',
            method: 'get',
        },
        {
            anotherToken: xAccount.getToken('auth'),
        }
    )
}

export function xAccountLogout() {
    const siteConfig = useSiteConfig()
    const xAccount = useXAccount()
    return createAxios({
        url: siteConfig.apiUrl + '/api/user/logout',
        method: 'POST',
        data: {
            refreshToken: xAccount.getToken('refresh'),
        },
    })
}
