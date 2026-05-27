import createAxios, { getUrl } from '/@/utils/axios'
import { adminBaseRoutePath } from '/@/router/static/adminBase'
import { useAdminInfo } from '/@/stores/adminInfo'

export const url = '/admin/system/ErrorLog/'

export function item(filename: string) {
    return createAxios({
        url: url + 'item',
        method: 'post',
        data: {
            filename,
        },
    })
}

export function delall() {
    return createAxios(
        {
            url: url + 'delall',
            method: 'post',
        },
        {
            showSuccessMessage: true,
        }
    )
}

export function downloadUrl(filename: string) {
    const adminInfo = useAdminInfo()
    const params = new URLSearchParams({
        filename,
        xtoken: adminInfo.getToken(),
        server: '1',
    })

    return getUrl() + adminBaseRoutePath + '/system/ErrorLog/download?' + params.toString()
}
