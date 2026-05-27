import createAxios from '/@/utils/axios'
import { checkFileMimetype } from '/@/utils/common'
import { getUrl } from '/@/utils/axios'
import { useAdminInfo } from '/@/stores/adminInfo'
import { ElNotification, type UploadRawFile } from 'element-plus'
import { useSiteConfig } from '/@/stores/siteConfig'
import { state as uploadExpandState, fileUpload as uploadExpand } from '/@/components/mixins/xUpload'
import type { AxiosRequestConfig } from 'axios'
import { uuid } from '/@/utils/random'
import { i18n } from '../lang'
import { adminBaseRoutePath } from '/@/router/static/adminBase'
import { SYSTEM_ZINDEX } from '/@/stores/constant/common'

/*
 * 公共请求函数和Url定义
 */

// Admin模块
export const adminUploadUrl = '/admin/ajax/upload'
export const adminBuildSuffixSvgUrl = adminBaseRoutePath + '/ajax/buildSuffixSvg'
export const adminAreaUrl = '/admin/ajax/area'

// 公共
export const clickCaptchaUrl = '/api/common/clickCaptcha'
export const checkClickCaptchaUrl = '/api/common/checkClickCaptcha'
export const refreshTokenUrl = '/api/common/refreshToken'

/**
 * 上传文件
 */
export function fileUpload(fd: FormData, params: anyObj = {}, forceLocal = false, config: AxiosRequestConfig = {}): ApiPromise {
    let errorMsg = ''
    const file = fd.get('file') as UploadRawFile
    const siteConfig = useSiteConfig()

    if (!file.name || typeof file.size == 'undefined') {
        errorMsg = i18n.global.t('utils.The data of the uploaded file is incomplete!')
    } else if (!checkFileMimetype(file.name, file.type)) {
        errorMsg = i18n.global.t('utils.The type of uploaded file is not allowed!')
    } else if (file.size > siteConfig.upload.maxSize) {
        errorMsg = i18n.global.t('utils.The size of the uploaded file exceeds the allowed range!')
    }
    if (errorMsg) {
        return new Promise((resolve, reject) => {
            ElNotification({
                type: 'error',
                message: errorMsg,
                zIndex: SYSTEM_ZINDEX,
            })
            reject(errorMsg)
        })
    }

    if (!forceLocal && uploadExpandState() == 'enable') {
        return uploadExpand(fd, params, config)
    }

    return createAxios({
        url: adminUploadUrl,
        method: 'POST',
        data: fd,
        params: params,
        timeout: 0,
        ...config,
    })
}

/**
 * 生成文件后缀icon的svg图片
 * @param suffix 后缀名
 * @param background 背景色,如:rgb(255,255,255)
 */
export function buildSuffixSvgUrl(suffix: string, background = '') {
    const adminInfo = useAdminInfo()
    return (
        getUrl() +
        adminBuildSuffixSvgUrl +
        '?xtoken=' +
        adminInfo.getToken() +
        '&suffix=' +
        suffix +
        (background ? '&background=' + background : '') +
        '&server=1'
    )
}

/**
 * 获取地区数据
 */
export function getArea(values: number[]) {
    const params: { province?: number; city?: number; uuid?: string } = {}
    if (values[0]) {
        params.province = values[0]
    }
    if (values[1]) {
        params.city = values[1]
    }
    params.uuid = uuid()
    return createAxios({
        url: adminAreaUrl,
        method: 'GET',
        params: params,
    })
}

/**
 * 远程下拉框数据获取
 */
export function getSelectData(remoteUrl: string, q: string, params: anyObj = {}) {
    return createAxios({
        url: remoteUrl,
        method: 'get',
        params: {
            select: true,
            quickSearch: q,
            ...params,
        },
    })
}

export function getCaptchaData(id: string, apiBaseURL: string) {
    return createAxios({
        url: apiBaseURL + clickCaptchaUrl,
        method: 'get',
        params: {
            id,
        },
    })
}

export function checkClickCaptcha(id: string, info: string, unset: boolean, apiBaseURL: string) {
    return createAxios(
        {
            url: apiBaseURL + checkClickCaptchaUrl,
            method: 'post',
            data: {
                id,
                info,
                unset,
            },
        },
        {
            showCodeMessage: false,
        }
    )
}

export function refreshToken() {
    const adminInfo = useAdminInfo()
    return createAxios({
        url: refreshTokenUrl,
        method: 'POST',
        data: {
            refreshToken: adminInfo.getToken('refresh'),
        },
    })
}

/**
 * 快速生成一个控制器的 增、删、改、查、排序 接口的请求方法
 * 本 class 实例通常直接传递给 xTable 使用，开发者可重写本类的方法，亦可直接向 xTable 传递自定义的 API 请求类
 * 表格相关网络请求无需局限于本类，开发者可于 /src/api/ 目录创建自定义的接口请求函数，并于需要的地方导入使用即可
 */
export class xTableApi {
    private controllerUrl
    public actionUrl

    constructor(controllerUrl: string) {
        this.controllerUrl = controllerUrl
        this.actionUrl = new Map([
            ['index', controllerUrl + 'index'],
            ['add', controllerUrl + 'add'],
            ['edit', controllerUrl + 'edit'],
            ['del', controllerUrl + 'del'],
            ['copy', controllerUrl + 'copy'],
            ['export', controllerUrl + 'export'],
            ['sortable', controllerUrl + 'sortable'],
        ])
    }

    /**
     * 表格查看接口的请求方法
     * @param filter 数据过滤条件
     */
    index(filter: XTable['filter'] = {}) {
        return createAxios<TableDefaultData>({
            url: this.actionUrl.get('index'),
            method: 'get',
            params: filter,
        })
    }

    /**
     * 获取被编辑行数据
     * @param params 被编辑行主键等
     */
    edit(params: anyObj) {
        return createAxios({
            url: this.actionUrl.get('edit'),
            method: 'get',
            params,
        })
    }

    /**
     * 表格删除接口的请求方法
     * @param ids 被删除数据的主键数组
     */
    del(ids: string[]) {
        return createAxios(
            {
                url: this.actionUrl.get('del'),
                method: 'DELETE',
                params: {
                    ids,
                },
            },
            {
                showSuccessMessage: true,
            }
        )
    }

    /**
     * 表格复制接口的请求方法
     * @param ids 被复制数据的主键数组
     */
    copy(ids: string[]) {
        return createAxios(
            {
                url: this.actionUrl.get('copy'),
                method: 'post',
                data: {
                    ids,
                },
            },
            {
                showSuccessMessage: true,
            }
        )
    }

    /**
     * 表格导出接口的请求方法
     * @param data 导出任务初始化或子任务参数
     */
    export(data: anyObj = {}) {
        return createAxios(
            {
                url: this.actionUrl.get('export'),
                method: 'post',
                data,
            },
            {
                showSuccessMessage: true,
            }
        )
    }

    /**
     * 向指定接口 POST 数据，本方法虽然较为通用，但请不要局限于此，开发者可于 /src/api/ 目录创建自定义的接口请求函数，并于需要的地方导入使用即可
     * @param action 请求的接口，比如 add、edit
     * @param data 要 POST 的数据
     */
    postData(action: string, data: anyObj) {
        return createAxios(
            {
                url: this.actionUrl.has(action) ? this.actionUrl.get(action) : this.controllerUrl + action,
                method: 'post',
                data,
            },
            {
                showSuccessMessage: true,
            }
        )
    }

    /**
     * 表格行排序接口的请求方法
     */
    sortable(data: anyObj) {
        return createAxios({
            url: this.actionUrl.get('sortable'),
            method: 'post',
            data,
        })
    }
}
