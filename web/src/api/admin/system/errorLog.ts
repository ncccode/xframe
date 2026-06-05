import createAxios from '/@/utils/axios'

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

export function download(filename: string) {
    return createAxios(
        {
            url: url + 'download',
            method: 'post',
            data: {
                filename,
            },
            responseType: 'blob',
        },
        {
            reductDataFormat: false,
            showSuccessMessage: false,
        }
    )
}
