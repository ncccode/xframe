<template>
    <div class="attachment-material" v-loading="state.loading">
        <aside class="attachment-material__aside">
            <el-scrollbar class="attachment-material__category-scroll">
                <el-tree
                    ref="treeRef"
                    node-key="id"
                    :data="categoryTree"
                    :highlight-current="true"
                    :expand-on-click-node="false"
                    :current-node-key="state.categoryId"
                    @node-click="onCategorySelect"
                >
                    <template #default="{ data }">
                        <div class="attachment-material__category">
                            <Icon name="fa fa-folder-o" size="14" :color="iconColor" />
                            <span class="attachment-material__category-name">{{ data.name }}</span>
                            <el-dropdown v-if="typeof data.id === 'number' && data.id > 0" trigger="click" @click.stop>
                                <span class="attachment-material__category-more" @click.stop>...</span>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item @click="renameCategory(data)">{{ t('system.attachment.Rename group') }}</el-dropdown-item>
                                        <el-dropdown-item @click="removeCategory(data)" divided>
                                            {{ t('system.attachment.Delete group') }}
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </template>
                </el-tree>
            </el-scrollbar>
            <div class="attachment-material__category-footer">
                <el-button v-blur class="w100" @click="createCategory">
                    <Icon name="fa fa-plus" size="13" :color="iconColor" />
                    <span>{{ t('system.attachment.Add group') }}</span>
                </el-button>
            </div>
        </aside>

        <main class="attachment-material__main">
            <div class="attachment-material__toolbar">
                <div class="attachment-material__toolbar-left">
                    <el-upload :auto-upload="false" :show-file-list="false" :multiple="true" :accept="accept" @change="onUploadChange">
                        <el-button v-blur type="primary">
                            <Icon name="el-icon-Upload" size="14" color="#ffffff" />
                            <span>{{ uploadText }}</span>
                        </el-button>
                    </el-upload>

                    <template v-if="mode === 'page'">
                        <el-button v-blur :disabled="!state.selected.length" @click="removeSelected">
                            <Icon name="fa fa-trash-o" size="13" :color="state.selected.length ? iconColor : iconDisabledColor" />
                            <span>{{ t('Delete') }}</span>
                        </el-button>
                        <el-select
                            v-model="state.moveCategoryId"
                            class="attachment-material__move-select"
                            :disabled="!state.selected.length"
                            :placeholder="t('system.attachment.Move to')"
                        >
                            <el-option :label="t('system.attachment.Uncategorized')" :value="0" />
                            <el-option v-for="item in state.categories" :key="item.id" :label="item.name" :value="item.id" />
                        </el-select>
                        <el-button v-blur :disabled="!canMove" @click="moveSelected">
                            <Icon name="fa fa-share" size="13" :color="canMove ? iconColor : iconDisabledColor" />
                            <span>{{ t('system.attachment.Move') }}</span>
                        </el-button>
                    </template>
                </div>

                <div class="attachment-material__toolbar-right">
                    <el-input
                        v-model="state.keyword"
                        class="attachment-material__search"
                        clearable
                        :placeholder="t('system.attachment.Search file name')"
                        @keyup.enter="refresh"
                        @clear="refresh"
                    >
                        <template #append>
                            <el-button v-blur @click="refresh">
                                <Icon name="el-icon-Search" size="14" :color="iconColor" />
                            </el-button>
                        </template>
                    </el-input>
                    <el-tooltip :content="t('system.attachment.Grid view')" placement="top">
                        <el-button v-blur :type="state.viewMode === 'grid' ? 'primary' : 'default'" @click="state.viewMode = 'grid'">
                            <Icon name="el-icon-Menu" size="14" :color="state.viewMode === 'grid' ? iconActiveColor : iconColor" />
                        </el-button>
                    </el-tooltip>
                    <el-tooltip :content="t('system.attachment.Table view')" placement="top">
                        <el-button v-blur :type="state.viewMode === 'table' ? 'primary' : 'default'" @click="state.viewMode = 'table'">
                            <Icon name="fa fa-list" size="13" :color="state.viewMode === 'table' ? iconActiveColor : iconColor" />
                        </el-button>
                    </el-tooltip>
                    <el-button v-blur @click="refresh">
                        <Icon name="fa fa-refresh" size="13" :color="iconColor" />
                    </el-button>
                </div>
            </div>

            <div v-if="mode === 'page'" class="attachment-material__select-row">
                <el-checkbox
                    :model-value="pageChecked"
                    :disabled="!state.attachments.length"
                    :indeterminate="pageIndeterminate"
                    @change="togglePageSelection"
                >
                    {{ t('system.attachment.Select current page') }}
                </el-checkbox>
                <span v-if="state.selected.length" class="attachment-material__selected-count">
                    {{ t('system.attachment.Selected count', { count: state.selected.length }) }}
                </span>
            </div>

            <section class="attachment-material__content">
                <el-scrollbar v-show="state.viewMode === 'grid'" class="attachment-material__grid-scroll">
                    <ul v-if="state.attachments.length" class="attachment-material__grid">
                        <li
                            v-for="item in state.attachments"
                            :key="item.id"
                            class="attachment-material__card"
                            :style="{ width: fileSize }"
                            @click="toggleSelection(item)"
                        >
                            <div class="attachment-material__thumb" :style="{ width: fileSize, height: fileSize }">
                                <img :src="previewUrl(item)" :alt="item.name" />
                                <div v-if="isSelected(item.id)" class="attachment-material__checked">
                                    <Icon name="fa fa-check" color="#ffffff" />
                                </div>
                                <button v-if="mode === 'page'" class="attachment-material__remove-one" type="button" @click.stop="removeOne(item)">
                                    <Icon name="fa fa-close" color="#ffffff" size="12" />
                                </button>
                            </div>
                            <div class="attachment-material__name" :title="item.name">{{ item.name }}</div>
                            <div class="attachment-material__actions">
                                <el-button v-if="mode === 'page'" link type="primary" @click.stop="renameAttachment(item)">
                                    {{ t('system.attachment.Rename') }}
                                </el-button>
                                <el-button v-if="type !== 'file'" link type="primary" @click.stop="openPreview(item)">
                                    {{ t('system.attachment.Preview') }}
                                </el-button>
                                <el-button v-if="type === 'file'" link type="primary" @click.stop="openFile(item)">
                                    {{ t('system.attachment.Open') }}
                                </el-button>
                            </div>
                        </li>
                    </ul>
                    <el-empty v-else class="attachment-material__empty" :description="t('system.attachment.No files')" />
                </el-scrollbar>

                <el-table
                    v-show="state.viewMode === 'table'"
                    class="attachment-material__table"
                    :data="state.attachments"
                    height="100%"
                    @row-click="toggleSelection"
                >
                    <el-table-column width="52">
                        <template #default="{ row }">
                            <el-checkbox :model-value="isSelected(row.id)" @change="toggleSelection(row)" @click.stop />
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('utils.preview')" width="96">
                        <template #default="{ row }">
                            <img class="attachment-material__table-thumb" :src="previewUrl(row)" :alt="row.name" />
                        </template>
                    </el-table-column>
                    <el-table-column prop="name" :label="t('utils.Original name')" min-width="180" show-overflow-tooltip />
                    <el-table-column prop="size" :label="t('utils.size')" width="110">
                        <template #default="{ row }">{{ formatSize(row.size) }}</template>
                    </el-table-column>
                    <el-table-column prop="last_upload_time" :label="t('utils.Last upload time')" width="180">
                        <template #default="{ row }">{{ formatTime(row.last_upload_time) }}</template>
                    </el-table-column>
                    <el-table-column :label="t('Operate')" width="170" fixed="right">
                        <template #default="{ row }">
                            <el-button v-if="mode === 'page'" link type="primary" @click.stop="renameAttachment(row)">
                                {{ t('system.attachment.Rename') }}
                            </el-button>
                            <el-button link type="primary" @click.stop="type === 'file' ? openFile(row) : openPreview(row)">
                                {{ type === 'file' ? t('system.attachment.Open') : t('system.attachment.Preview') }}
                            </el-button>
                            <el-button v-if="mode === 'page'" link type="danger" @click.stop="removeOne(row)">
                                {{ t('Delete') }}
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </section>

            <footer class="attachment-material__footer">
                <div>
                    <span v-if="mode === 'picker'" class="attachment-material__selected-count">
                        {{ t('system.attachment.Selected count', { count: state.selected.length }) }}
                    </span>
                </div>
                <el-pagination
                    v-model:current-page="state.page"
                    v-model:page-size="state.limit"
                    background
                    layout="total, prev, pager, next, jumper"
                    :total="state.total"
                    @current-change="getList"
                />
            </footer>
        </main>

        <aside v-if="mode === 'picker'" class="attachment-material__selected">
            <header>
                <span>{{ t('system.attachment.Selected count', { count: state.selected.length }) }}</span>
                <el-button link type="primary" @click="clearSelect">{{ t('system.attachment.Clear') }}</el-button>
            </header>
            <el-scrollbar>
                <ul class="attachment-material__selected-list">
                    <li v-for="item in state.selected" :key="item.id">
                        <div class="attachment-material__thumb attachment-material__selected-thumb">
                            <img :src="previewUrl(item)" :alt="item.name" />
                            <button class="attachment-material__remove-one" type="button" @click.stop="cancelSelection(item.id)">
                                <Icon name="fa fa-close" color="#ffffff" size="12" />
                            </button>
                        </div>
                    </li>
                </ul>
            </el-scrollbar>
        </aside>

        <el-dialog v-model="state.previewVisible" :title="state.previewItem?.name" width="720px" append-to-body destroy-on-close>
            <div class="attachment-material__preview">
                <img v-if="type === 'image'" :src="state.previewItem?.full_url" :alt="state.previewItem?.name" />
                <video v-else-if="type === 'video'" :src="state.previewItem?.full_url" controls />
            </div>
        </el-dialog>

        <el-dialog
            v-model="state.progressVisible"
            :title="t('system.attachment.Upload progress')"
            width="520px"
            append-to-body
            :close-on-click-modal="false"
        >
            <div v-for="item in state.uploadingFiles" :key="item.uid" class="attachment-material__progress">
                <div>{{ item.name }}</div>
                <el-progress :percentage="item.percentage" />
            </div>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, useTemplateRef, watch } from 'vue'
import { ElMessage, ElMessageBox, type UploadFile, type UploadFiles } from 'element-plus'
import type { AxiosProgressEvent } from 'axios'
import { useI18n } from 'vue-i18n'
import { fileUpload, buildSuffixSvgUrl } from '/@/api/common'
import {
    addAttachmentCategory,
    deleteAttachmentCategories,
    deleteAttachments,
    editAttachmentCategory,
    editAttachmentName,
    getAttachmentCategories,
    getAttachments,
    moveAttachments,
    type AttachmentCategory,
    type AttachmentCategoryId,
    type AttachmentItem,
    type AttachmentMediaType,
} from '/@/api/admin/system/attachment'
import { uuid } from '/@/utils/random'

interface Props {
    type?: AttachmentMediaType
    mode?: 'page' | 'picker'
    limit?: number
    fileSize?: string
    pageSize?: number
}

interface CategoryNode {
    id: AttachmentCategoryId
    name: string
}

interface UploadingFile {
    uid: number
    name: string
    percentage: number
}

const props = withDefaults(defineProps<Props>(), {
    type: 'image',
    mode: 'picker',
    limit: 1,
    fileSize: '112px',
    pageSize: 20,
})

const emits = defineEmits<{
    (e: 'change', value: AttachmentItem[]): void
}>()

const { t } = useI18n()
const treeRef = useTemplateRef('treeRef')
const iconColor = 'var(--el-text-color-secondary)'
const iconDisabledColor = 'var(--el-text-color-placeholder)'
const iconActiveColor = '#ffffff'

const state = reactive({
    loading: false,
    categories: [] as AttachmentCategory[],
    categoryId: '' as AttachmentCategoryId,
    moveCategoryId: null as number | null,
    attachments: [] as AttachmentItem[],
    selected: [] as AttachmentItem[],
    keyword: '',
    page: 1,
    limit: props.pageSize,
    total: 0,
    viewMode: 'grid' as 'grid' | 'table',
    previewVisible: false,
    previewItem: null as AttachmentItem | null,
    progressVisible: false,
    uploadingFiles: [] as UploadingFile[],
})

const categoryTree = computed<CategoryNode[]>(() => {
    return [
        { id: '', name: t('system.attachment.All files') },
        { id: 0, name: t('system.attachment.Uncategorized') },
        ...state.categories.map((item) => ({ id: item.id, name: item.name })),
    ]
})

const accept = computed(() => {
    if (props.type === 'image') return 'image/*'
    if (props.type === 'video') return 'video/*'
    return ''
})

const uploadText = computed(() => {
    if (props.type === 'image') return t('system.attachment.Upload image')
    if (props.type === 'video') return t('system.attachment.Upload video')
    return t('system.attachment.Upload file')
})

const pageChecked = computed(() => {
    return state.attachments.length > 0 && state.attachments.every((item) => isSelected(item.id))
})

const pageIndeterminate = computed(() => {
    const checkedCount = state.attachments.filter((item) => isSelected(item.id)).length
    return checkedCount > 0 && checkedCount < state.attachments.length
})

const canMove = computed(() => {
    return state.selected.length > 0 && state.moveCategoryId !== null
})

const getCategoryList = () => {
    return getAttachmentCategories(props.type).then((res) => {
        state.categories = res.data.list
        nextTick(() => treeRef.value?.setCurrentKey(state.categoryId))
    })
}

const getList = () => {
    state.loading = true
    const params: anyObj = {
        page: state.page,
        limit: state.limit,
        media_type: props.type,
    }
    if (state.categoryId !== '') {
        params.category_id = state.categoryId
    }
    if (state.keyword) {
        params.name = state.keyword
    }
    return getAttachments(params)
        .then((res) => {
            state.attachments = res.data.list
            state.total = res.data.total
        })
        .finally(() => {
            state.loading = false
        })
}

const refresh = () => {
    state.page = 1
    getList()
}

const onCategorySelect = (item: CategoryNode) => {
    state.categoryId = item.id
    state.moveCategoryId = null
    refresh()
}

const createCategory = () => {
    ElMessageBox.prompt(t('system.attachment.Group name'), t('system.attachment.Add group'), {
        confirmButtonText: t('Confirm'),
        cancelButtonText: t('Cancel'),
        inputValidator: (value) => Boolean(value && value.trim()),
        inputErrorMessage: t('system.attachment.Group name required'),
    }).then(({ value }) => {
        addAttachmentCategory({ name: value.trim(), media_type: props.type }).then(getCategoryList)
    })
}

const renameCategory = (item: AttachmentCategory) => {
    ElMessageBox.prompt(t('system.attachment.Group name'), t('system.attachment.Rename group'), {
        confirmButtonText: t('Confirm'),
        cancelButtonText: t('Cancel'),
        inputValue: item.name,
        inputValidator: (value) => Boolean(value && value.trim()),
        inputErrorMessage: t('system.attachment.Group name required'),
    }).then(({ value }) => {
        editAttachmentCategory(item.id, { name: value.trim() }).then(getCategoryList)
    })
}

const removeCategory = (item: AttachmentCategory) => {
    ElMessageBox.confirm(t('system.attachment.Delete group confirm'), t('Warning'), {
        type: 'warning',
        confirmButtonText: t('Confirm'),
        cancelButtonText: t('Cancel'),
    }).then(() => {
        deleteAttachmentCategories([item.id]).then(() => {
            state.categoryId = ''
            getCategoryList()
            refresh()
        })
    })
}

const onUploadChange = (uploadFile: UploadFile, uploadFiles: UploadFiles) => {
    if (!uploadFile.raw) return
    const fd = new FormData()
    fd.append('file', uploadFile.raw)
    if (typeof state.categoryId === 'number') {
        fd.append('category_id', String(state.categoryId))
    }
    fd.append('media_type', props.type)

    const uploading: UploadingFile = {
        uid: Number(uploadFile.uid),
        name: uploadFile.name,
        percentage: 0,
    }
    state.uploadingFiles.push(uploading)
    state.progressVisible = true

    fileUpload(fd, { uuid: uuid() }, true, {
        onUploadProgress: (evt: AxiosProgressEvent) => {
            if (evt.total && evt.total > 0) {
                uploading.percentage = Math.round((evt.loaded / evt.total) * 100)
            }
        },
    })
        .then((res) => {
            if (res.code === 1) {
                uploading.percentage = 100
                refresh()
            }
        })
        .finally(() => {
            const allDone = state.uploadingFiles.every((item) => item.percentage >= 100)
            if (allDone) {
                setTimeout(() => {
                    state.progressVisible = false
                    state.uploadingFiles = []
                }, 300)
            }
            uploadFiles.splice(0, uploadFiles.length)
        })
}

const isSelected = (id: number) => {
    return state.selected.some((item) => item.id === id)
}

const toggleSelection = (item: AttachmentItem) => {
    const index = state.selected.findIndex((row) => row.id === item.id)
    if (index >= 0) {
        state.selected.splice(index, 1)
        if (!state.selected.length) {
            state.moveCategoryId = null
        }
        emits('change', state.selected)
        return
    }
    if (props.limit > 0 && state.selected.length >= props.limit) {
        if (props.limit === 1) {
            state.selected = [item]
            emits('change', state.selected)
            return
        }
        ElMessage.warning(t('system.attachment.Selection limit reached'))
        return
    }
    state.selected.push(item)
    emits('change', state.selected)
}

const cancelSelection = (id: number) => {
    state.selected = state.selected.filter((item) => item.id !== id)
    emits('change', state.selected)
}

const clearSelect = () => {
    state.selected = []
    state.moveCategoryId = null
    emits('change', state.selected)
}

const togglePageSelection = (checked: string | number | boolean) => {
    if (!checked) {
        state.attachments.forEach((item) => cancelSelection(item.id))
        return
    }
    state.attachments.forEach((item) => {
        if (!isSelected(item.id)) {
            toggleSelection(item)
        }
    })
}

const selectedIds = () => state.selected.map((item) => item.id)

const removeSelected = () => {
    if (!state.selected.length) return
    removeByIds(selectedIds())
}

const removeOne = (item: AttachmentItem) => {
    removeByIds([item.id])
}

const removeByIds = (ids: number[]) => {
    ElMessageBox.confirm(t('system.attachment.Delete files confirm'), t('Warning'), {
        type: 'warning',
        confirmButtonText: t('Confirm'),
        cancelButtonText: t('Cancel'),
    }).then(() => {
        deleteAttachments(ids).then(() => {
            state.selected = state.selected.filter((item) => !ids.includes(item.id))
            if (!state.selected.length) {
                state.moveCategoryId = null
            }
            emits('change', state.selected)
            getList()
        })
    })
}

const moveSelected = () => {
    if (!canMove.value || state.moveCategoryId === null) return
    moveAttachments(selectedIds(), state.moveCategoryId).then(() => {
        clearSelect()
        refresh()
    })
}

const renameAttachment = (item: AttachmentItem) => {
    ElMessageBox.prompt(t('system.attachment.File name'), t('system.attachment.Rename'), {
        confirmButtonText: t('Confirm'),
        cancelButtonText: t('Cancel'),
        inputValue: item.name,
        inputValidator: (value) => Boolean(value && value.trim()),
        inputErrorMessage: t('system.attachment.File name required'),
    }).then(({ value }) => {
        editAttachmentName(item.id, value.trim()).then(getList)
    })
}

const previewUrl = (item: AttachmentItem) => {
    if (props.type === 'image') return item.full_url
    return buildSuffixSvgUrl(item.suffix || suffixFromName(item.name))
}

const suffixFromName = (name: string) => {
    const suffix = name.includes('.') ? name.split('.').pop() : ''
    return suffix || 'file'
}

const openPreview = (item: AttachmentItem) => {
    state.previewItem = item
    state.previewVisible = true
}

const openFile = (item: AttachmentItem) => {
    window.open(item.full_url)
}

const formatSize = (size: number) => {
    if (!size) return '0 B'
    const index = Math.min(Math.floor(Math.log(size) / Math.log(1024)), 4)
    const value = size / Math.pow(1024, index)
    return `${value.toFixed(index < 2 ? 0 : 2)} ${['B', 'KB', 'MB', 'GB', 'TB'][index]}`
}

const formatTime = (time?: number) => {
    if (!time) return '-'
    return new Date(time * 1000).toLocaleString()
}

onMounted(() => {
    getCategoryList()
    getList()
})

watch(
    () => props.type,
    () => {
        state.categoryId = ''
        state.selected = []
        state.page = 1
        getCategoryList()
        getList()
        emits('change', state.selected)
    }
)

defineExpose({
    clearSelect,
})
</script>

<style scoped lang="scss">
.attachment-material {
    display: flex;
    min-height: 0;
    height: 100%;
    border: 1px solid var(--el-border-color-light);
    background: var(--x-bg-color-overlay);
}

.attachment-material__aside {
    display: flex;
    flex-direction: column;
    width: 210px;
    min-width: 180px;
    border-right: 1px solid var(--el-border-color-light);
    background: var(--x-bg-color-overlay);
}

.attachment-material__category-scroll {
    flex: 1;
    min-height: 0;
}

.attachment-material__category-scroll :deep(.el-tree) {
    padding: 6px 0;
}

.attachment-material__category-scroll :deep(.el-tree-node__content) {
    height: 34px;
}

.attachment-material__category {
    display: flex;
    align-items: center;
    width: 100%;
    min-width: 0;
    gap: 10px;
}

.attachment-material__category-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.attachment-material__category-more {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    color: var(--el-text-color-secondary);
}

.attachment-material__category-footer {
    padding: 12px;
    border-top: 1px solid var(--el-border-color-light);
}

.attachment-material__main {
    display: flex;
    flex: 1;
    min-width: 0;
    min-height: 0;
    flex-direction: column;
    padding: 14px 14px 10px;
    background: var(--x-bg-color-overlay);
}

.attachment-material__toolbar,
.attachment-material__toolbar-left,
.attachment-material__toolbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

.attachment-material__toolbar {
    justify-content: space-between;
    flex-wrap: wrap;
}

.attachment-material__search {
    width: 240px;
}

.attachment-material__move-select {
    width: 150px;
}

.attachment-material__select-row {
    display: flex;
    align-items: center;
    gap: 14px;
    min-height: 34px;
}

.attachment-material__selected-count {
    color: var(--el-text-color-secondary);
    font-size: var(--el-font-size-small);
}

.attachment-material__content {
    flex: 1;
    min-height: 0;
}

.attachment-material__grid-scroll {
    height: 100%;
}

.attachment-material__grid-scroll :deep(.el-scrollbar__view) {
    min-height: 100%;
}

.attachment-material__grid,
.attachment-material__selected-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.attachment-material__grid {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    padding: 12px 2px 4px;
}

.attachment-material__card {
    cursor: pointer;
}

.attachment-material__thumb {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid var(--el-border-color-light);
    border-radius: 6px;
    background: var(--el-fill-color-lighter);
}

.attachment-material__thumb img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.attachment-material__checked {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(64, 158, 255, 0.72);
}

.attachment-material__remove-one {
    position: absolute;
    top: 6px;
    right: 6px;
    display: none;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.48);
    cursor: pointer;
}

.attachment-material__thumb:hover .attachment-material__remove-one {
    display: flex;
}

.attachment-material__name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-top: 6px;
    font-size: var(--el-font-size-small);
    color: var(--el-text-color-primary);
}

.attachment-material__actions {
    display: flex;
    align-items: center;
    min-height: 28px;
    visibility: hidden;
}

.attachment-material__card:hover .attachment-material__actions {
    visibility: visible;
}

.attachment-material__table {
    height: 100%;
}

.attachment-material__empty {
    min-height: 100%;
    justify-content: center;
}

.attachment-material__table-thumb {
    display: block;
    width: 52px;
    height: 52px;
    object-fit: contain;
    border-radius: 4px;
    background: var(--el-fill-color-lighter);
}

.attachment-material__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-top: 10px;
}

.attachment-material__selected {
    display: flex;
    flex-direction: column;
    width: 132px;
    border-left: 1px solid var(--el-border-color-light);
}

.attachment-material__selected header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px;
    border-bottom: 1px solid var(--el-border-color-light);
}

.attachment-material__selected-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 10px;
}

.attachment-material__selected-thumb {
    width: 100px;
    height: 100px;
}

.attachment-material__preview {
    display: flex;
    justify-content: center;
    max-height: 70vh;
    overflow: auto;
}

.attachment-material__preview img,
.attachment-material__preview video {
    max-width: 100%;
    max-height: 70vh;
}

.attachment-material__progress + .attachment-material__progress {
    margin-top: 12px;
}

.w100 {
    width: 100%;
}
</style>
