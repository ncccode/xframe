import createAxios from '/@/utils/axios'

export type AttachmentMediaType = 'image' | 'video' | 'file'
export type AttachmentCategoryId = number | ''

export interface AttachmentCategory {
    id: number
    name: string
    media_type: AttachmentMediaType
    sort: number
}

export interface AttachmentItem {
    id: number
    category_id: number
    media_type: AttachmentMediaType
    topic: string
    url: string
    full_url: string
    suffix: string
    name: string
    size: number
    mimetype: string
    quote: number
    storage: string
    last_upload_time: number
    create_time?: number
}

export interface AttachmentListParams {
    page?: number
    limit?: number
    category_id?: number
    media_type?: AttachmentMediaType
    name?: string
    quickSearch?: string
}

export function getAttachmentCategories(mediaType: AttachmentMediaType) {
    return createAxios<TableDefaultData<AttachmentCategory[]>>({
        url: '/admin/system/AttachmentCategory/index',
        method: 'get',
        params: {
            page: 1,
            limit: 999,
            media_type: mediaType,
            order: 'sort,desc',
        },
    })
}

export function addAttachmentCategory(data: Pick<AttachmentCategory, 'name' | 'media_type'>) {
    return createAxios(
        {
            url: '/admin/system/AttachmentCategory/add',
            method: 'post',
            data,
        },
        {
            showSuccessMessage: true,
        }
    )
}

export function editAttachmentCategory(id: number, data: Partial<Pick<AttachmentCategory, 'name' | 'sort'>>) {
    return createAxios(
        {
            url: '/admin/system/AttachmentCategory/edit',
            method: 'post',
            data: {
                id,
                ...data,
            },
        },
        {
            showSuccessMessage: true,
        }
    )
}

export function deleteAttachmentCategories(ids: number[]) {
    return createAxios(
        {
            url: '/admin/system/AttachmentCategory/del',
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

export function getAttachments(params: AttachmentListParams) {
    return createAxios<TableDefaultData<AttachmentItem[]>>({
        url: '/admin/system/Attachment/index',
        method: 'get',
        params,
    })
}

export function editAttachmentName(id: number, name: string) {
    return createAxios(
        {
            url: '/admin/system/Attachment/edit',
            method: 'post',
            data: {
                id,
                name,
            },
        },
        {
            showSuccessMessage: true,
        }
    )
}

export function moveAttachments(ids: number[], categoryId: number) {
    return createAxios(
        {
            url: '/admin/system/Attachment/move',
            method: 'post',
            data: {
                ids,
                category_id: categoryId,
            },
        },
        {
            showSuccessMessage: true,
        }
    )
}

export function deleteAttachments(ids: number[]) {
    return createAxios(
        {
            url: '/admin/system/Attachment/del',
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
