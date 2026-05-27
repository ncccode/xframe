import createAxios from '/@/utils/axios'

export function getAdminRules() {
    return createAxios({
        url: '/admin/system/Rule/index',
        method: 'get',
    })
}
