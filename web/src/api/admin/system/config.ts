import createAxios from '/@/utils/axios'

export const url = '/admin/system/Config/'

export const actionUrl = new Map([
    ['index', url + 'index'],
    ['add', url + 'add'],
    ['edit', url + 'edit'],
    ['del', url + 'del'],
])

export function index(params: anyObj = {}) {
    return createAxios({
        url: actionUrl.get('index'),
        method: 'get',
        params,
    })
}

export function postData(action: string, data: anyObj, params: anyObj = {}) {
    return createAxios(
        {
            url: actionUrl.get(action),
            method: 'post',
            data: data,
            params,
        },
        {
            showSuccessMessage: true,
        }
    )
}

export function del(ids: string[], params: anyObj = {}) {
    return createAxios(
        {
            url: actionUrl.get('del'),
            method: 'DELETE',
            params: {
                ...params,
                ids: ids,
            },
        },
        {
            showSuccessMessage: true,
        }
    )
}
