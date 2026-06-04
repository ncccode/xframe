<template>
    <div class="table-select w100" :style="componentStyle">
        <el-select
            ref="selectRef"
            class="w100"
            v-bind="$attrs"
            :model-value="state.value"
            :multiple="props.multiple"
            :loading="state.loading"
            :disabled="props.disabled"
            :clearable="props.clearable !== false"
            :collapse-tags="props.collapseTags ?? true"
            :collapse-tags-tooltip="props.collapseTagsTooltip ?? true"
            :filterable="false"
            :remote="false"
            :popper-class="popperClass"
            :teleported="props.teleported ?? false"
            @focus="onFocus"
            @change="onChangeSelect"
            @clear="onClear"
            @remove-tag="onRemoveTag"
            @visible-change="onVisibleChange"
        >
            <template #empty>
                <div class="table-select-panel" @mousedown.stop>
                    <div v-if="$slots.header || props.quickSearch !== false || searchItems.length" class="table-select-search">
                        <slot
                            v-if="$slots.header"
                            name="header"
                            :form="state.searchForm"
                            :keyword="state.keyword"
                            :loading="state.loading"
                            :submit="onSearchSubmit"
                            :reset="onSearchReset"
                            :set-keyword="setKeyword"
                        />
                        <el-form v-else :model="state.searchForm" class="table-select-search-form" @submit.prevent>
                            <el-form-item v-if="props.quickSearch !== false">
                                <el-input
                                    v-model="state.keyword"
                                    clearable
                                    :placeholder="props.keywordPlaceholder || $t('Search')"
                                    @keydown.enter.stop="onSearchSubmit"
                                />
                            </el-form-item>
                            <el-form-item v-for="item in searchItems" :key="getSearchField(item)" :label="item.label">
                                <el-select
                                    v-if="getSearchType(item) == 'select'"
                                    v-model="state.searchForm[getSearchField(item)]"
                                    class="w100"
                                    clearable
                                    :placeholder="getSearchPlaceholder(item)"
                                    :teleported="false"
                                    v-bind="getSearchInputAttr(item)"
                                    @keydown.enter.stop="onSearchSubmit"
                                >
                                    <el-option
                                        v-for="option in getSearchOptions(item)"
                                        :key="String(option.value)"
                                        :label="option.label"
                                        :value="option.value"
                                    />
                                </el-select>
                                <el-date-picker
                                    v-else-if="getSearchType(item) == 'date' || getSearchType(item) == 'datetime'"
                                    v-model="state.searchForm[getSearchField(item)]"
                                    class="w100"
                                    :type="getSearchDateType(item)"
                                    :value-format="getSearchType(item) == 'date' ? 'YYYY-MM-DD' : 'YYYY-MM-DD HH:mm:ss'"
                                    :placeholder="getSearchPlaceholder(item)"
                                    :teleported="false"
                                    v-bind="getSearchInputAttr(item)"
                                    @keydown.enter.stop="onSearchSubmit"
                                />
                                <el-time-picker
                                    v-else-if="getSearchType(item) == 'time'"
                                    v-model="state.searchForm[getSearchField(item)]"
                                    class="w100"
                                    value-format="HH:mm:ss"
                                    :placeholder="getSearchPlaceholder(item)"
                                    :teleported="false"
                                    v-bind="getSearchInputAttr(item)"
                                    @keydown.enter.stop="onSearchSubmit"
                                />
                                <el-input-number
                                    v-else-if="getSearchType(item) == 'number'"
                                    v-model="state.searchForm[getSearchField(item)]"
                                    class="w100"
                                    controls-position="right"
                                    v-bind="getSearchInputAttr(item)"
                                    @keydown.enter.stop="onSearchSubmit"
                                />
                                <el-input
                                    v-else
                                    v-model="state.searchForm[getSearchField(item)]"
                                    clearable
                                    :placeholder="getSearchPlaceholder(item)"
                                    v-bind="getSearchInputAttr(item)"
                                    @keydown.enter.stop="onSearchSubmit"
                                />
                            </el-form-item>
                            <el-form-item class="table-select-search-actions">
                                <el-button type="primary" :loading="state.loading" @click="onSearchSubmit">{{ $t('Search') }}</el-button>
                                <el-button v-if="props.showSearchReset" @click="onSearchReset">{{ $t('Reset') }}</el-button>
                            </el-form-item>
                        </el-form>
                    </div>
                    <el-table
                        ref="tableRef"
                        v-loading="state.loading"
                        :data="state.list"
                        :height="tableHeight"
                        :row-key="tableRowKey"
                        :highlight-current-row="!props.multiple"
                        v-bind="props.tableAttrs"
                        @row-click="onTableRowClick"
                        @select="onTableSelect"
                        @select-all="onTableSelectAll"
                    >
                        <el-table-column v-if="props.multiple" type="selection" width="46" />
                        <el-table-column v-else type="index" width="54">
                            <template #default="scope">
                                <span>{{ scope.$index + (state.currentPage - 1) * state.pageSize + 1 }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column v-for="(item, index) in tableColumns" :key="index" v-bind="item" />
                        <slot name="columns" />
                        <slot :rows="state.list" :loading="state.loading" />
                    </el-table>
                    <el-pagination
                        v-if="state.total > 0 && props.pagination !== false"
                        class="table-select-pagination"
                        v-bind="paginationAttrs"
                        @current-change="onCurrentPageChange"
                        @size-change="onPageSizeChange"
                    />
                </div>
            </template>
        </el-select>
    </div>
</template>

<script setup lang="ts">
import type { CSSProperties } from 'vue'
import { computed, getCurrentInstance, nextTick, onMounted, onUnmounted, reactive, toRaw, useAttrs, useTemplateRef, watch } from 'vue'
import type { ElSelect, PaginationProps, TableColumnCtx } from 'element-plus'
import { getSelectData } from '/@/api/common'
import { useConfig } from '/@/stores/config'
import { shortUuid } from '/@/utils/random'

type SelectValue = string | number | Array<string | number> | null
type TableSelectColumn = Partial<TableColumnCtx<anyObj>> & anyObj
type SearchOption = { label: string; value: string | number | boolean }
type SearchItem = {
    field?: string
    prop?: string
    name?: string
    label?: string
    type?: string
    render?: string
    msg?: string
    placeholder?: string
    value?: any
    defaultValue?: any
    content?: Record<string, string> | SearchOption[] | Array<string | number>
    options?: Record<string, string> | SearchOption[] | Array<string | number>
    inputAttr?: anyObj
    inputExtend?: anyObj
}
type RemoteData = {
    list?: anyObj[]
    options?: anyObj[]
    total?: number
}
type ElSelectProps = Omit<Partial<InstanceType<typeof ElSelect>['$props']>, 'modelValue'>

interface Props extends /* @vue-ignore */ ElSelectProps {
    pk?: string
    field?: string
    params?: anyObj
    remoteUrl?: string
    request?: (params: anyObj, keyword: string) => ApiPromise<RemoteData>
    parseData?: (response: ApiResponse<any>) => RemoteData
    modelValue: SelectValue
    multiple?: boolean
    columns?: TableSelectColumn[]
    column?: TableSelectColumn[]
    search?: SearchItem[]
    quickSearch?: boolean
    keywordPlaceholder?: string
    showSearchReset?: boolean
    labelFormatter?: (row: anyObj, index: string | number) => string
    rowKey?: string | ((row: anyObj) => string)
    tableAttrs?: anyObj
    pagination?: boolean | PaginationProps
    tableHeight?: number | string
    popperMinWidth?: number | string
}

const props = withDefaults(defineProps<Props>(), {
    pk: 'id',
    field: 'name',
    params: () => ({}),
    remoteUrl: '',
    modelValue: null,
    multiple: false,
    columns: () => [],
    column: () => [],
    search: () => [],
    quickSearch: true,
    showSearchReset: true,
    tableAttrs: () => ({}),
    pagination: true,
    tableHeight: 280,
    popperMinWidth: 560,
    disabled: false,
})

const attrs = useAttrs()
const config = useConfig()
const instance = getCurrentInstance()
const selectRef = useTemplateRef('selectRef')
const tableRef = useTemplateRef('tableRef')

const emit = defineEmits<{
    (e: 'update:modelValue', value: SelectValue): void
    (e: 'change', value: SelectValue): void
    (e: 'row', value: anyObj | anyObj[]): void
    (e: 'clear'): void
}>()

const state = reactive({
    primaryKey: getPrimaryKey(props.pk),
    list: [] as anyObj[],
    rowMap: {} as Record<string, anyObj>,
    loading: false,
    total: 0,
    currentPage: Number(props.params.page || 1),
    pageSize: Number(props.params.limit || props.params.pageSize || 10),
    keyword: '',
    value: null as SelectValue,
    initialized: false,
    searchForm: {} as anyObj,
    requestUuid: shortUuid('table_select'),
})

let io: IntersectionObserver | null = null
let requestIndex = 0

const valueOnClear = computed<SelectValue>(() => {
    const clearValue = (attrs.valueOnClear ?? (props as anyObj).valueOnClear) as SelectValue | (() => SelectValue) | undefined
    if (typeof clearValue == 'function') {
        return clearValue()
    }
    if (clearValue !== undefined) {
        return clearValue
    }
    return props.multiple ? [] : null
})

const emptyValues = computed(() => {
    return ((attrs.emptyValues ?? (props as anyObj).emptyValues) as any[] | undefined) || [null, undefined, '']
})

const componentStyle = computed<CSSProperties>(() => {
    const width = typeof props.popperMinWidth == 'number' ? `${props.popperMinWidth}px` : props.popperMinWidth
    return {
        '--table-select-popper-min-width': width,
    } as CSSProperties
})

const popperClass = computed(() => {
    return ['table-select-popper', (props as anyObj).popperClass, attrs.popperClass].filter(Boolean).join(' ')
})

const searchItems = computed(() => props.search.filter((item) => getSearchField(item)))

const tableColumns = computed<TableSelectColumn[]>(() => {
    const columns = props.columns.length ? props.columns : props.column
    if (columns.length) {
        return columns
    }
    return [
        {
            prop: props.field,
            label: props.field,
            minWidth: 140,
        },
    ]
})

const tableRowKey = computed(() => props.rowKey || state.primaryKey)

const tableHeight = computed(() => props.tableHeight ?? (attrs.height as string | number | undefined) ?? 280)

const paginationAttrs = computed<Partial<PaginationProps>>(() => {
    const defaultAttrs: Partial<PaginationProps> = {
        total: state.total,
        pageSize: state.pageSize,
        currentPage: state.currentPage,
        pagerCount: 5,
        layout: 'total, sizes, prev, pager, next',
        pageSizes: [10, 20, 50, 100],
        size: config.layout.shrink ? 'small' : 'default',
    }

    if (typeof props.pagination == 'boolean') {
        return defaultAttrs
    }
    return { ...defaultAttrs, ...props.pagination }
})

function getPrimaryKey(pk = 'id') {
    const pkArr = pk.split('.')
    return pkArr[pkArr.length - 1] || pk
}

function getRowValue(row: anyObj) {
    return row[state.primaryKey] === undefined || row[state.primaryKey] === null ? '' : String(row[state.primaryKey])
}

function getRowLabel(row: anyObj, index: string | number = 0) {
    if (typeof props.labelFormatter == 'function') {
        return props.labelFormatter(row, index)
    }
    return row[props.field] === undefined || row[props.field] === null ? getRowValue(row) : String(row[props.field])
}

function getValueArray(value: SelectValue = state.value) {
    if (Array.isArray(value)) {
        return value.map((item) => String(item))
    }
    return isEmptyValue(value) ? [] : [String(value)]
}

function isEmptyValue(value: any) {
    if (Array.isArray(value)) {
        return value.length === 0
    }
    return emptyValues.value.includes(value)
}

function normalizeValue(value: any): SelectValue {
    if (props.multiple) {
        if (Array.isArray(value)) {
            return value.filter((item) => !isEmptyValue(item)).map((item) => String(item))
        }
        return isEmptyValue(value) ? [] : [String(value)]
    }

    const nextValue = Array.isArray(value) ? value[0] : value
    if (isEmptyValue(nextValue)) {
        return valueOnClear.value
    }
    return typeof nextValue == 'number' ? String(nextValue) : nextValue
}

function setValue(value: any, emitUpdate = true, emitChange = false) {
    state.value = normalizeValue(value)
    if (emitUpdate) {
        emit('update:modelValue', state.value)
    }
    if (emitChange) {
        emit('change', state.value)
        emitSelectedRows()
    }
    syncSelectedLabel()
    return state.value
}

function emitSelectedRows() {
    if (typeof instance?.vnode.props?.onRow != 'function') {
        return
    }

    const values = getValueArray()
    if (props.multiple) {
        emit(
            'row',
            values
                .map((value) => state.rowMap[value])
                .filter((row) => row)
                .map((row) => toRaw(row))
        )
    } else {
        emit('row', values.length ? toRaw(state.rowMap[values[0]] || {}) : {})
    }
}

function cacheRows(rows: anyObj[]) {
    rows.forEach((row, index) => {
        const value = getRowValue(row)
        if (value) {
            state.rowMap[value] = row
            if (typeof props.labelFormatter == 'function') {
                state.rowMap[value] = {
                    ...row,
                    [props.field]: getRowLabel(row, index),
                }
            }
        }
    })
}

function syncSelectedLabel() {
    nextTick(() => {
        const select = selectRef.value as anyObj | null
        if (!select) {
            return
        }

        if (props.multiple) {
            const selectedItems = select.selected || select.states?.selected || []
            selectedItems.forEach((item: anyObj) => {
                const row = state.rowMap[String(item.value)]
                if (row) {
                    item.currentLabel = getRowLabel(row)
                    item.label = getRowLabel(row)
                }
            })
            return
        }

        const value = getValueArray()[0]
        const row = value ? state.rowMap[value] : null
        const label = row ? getRowLabel(row) : ''
        if ('selectedLabel' in select) {
            select.selectedLabel = label
        }
        if (select.states && 'selectedLabel' in select.states) {
            select.states.selectedLabel = label
        }
    })
}

function applyTableSelection() {
    nextTick(() => {
        const table = tableRef.value as anyObj | null
        if (!table) {
            return
        }

        const values = new Set(getValueArray())
        if (props.multiple) {
            table.clearSelection?.()
            state.list.forEach((row) => {
                table.toggleRowSelection?.(row, values.has(getRowValue(row)))
            })
        } else {
            const row = state.list.find((item) => values.has(getRowValue(item)))
            table.setCurrentRow?.(row || null)
        }
        table.setScrollTop?.(0)
    })
}

function getSearchField(item: SearchItem) {
    return item.field || item.prop || item.name || ''
}

function getSearchType(item: SearchItem) {
    const type = item.type || item.render || 'string'
    return type == 'selects' ? 'select' : type
}

function getSearchDateType(item: SearchItem): 'date' | 'datetime' {
    return getSearchType(item) == 'date' ? 'date' : 'datetime'
}

function getSearchPlaceholder(item: SearchItem) {
    return item.placeholder || item.msg || ''
}

function getSearchInputAttr(item: SearchItem) {
    return {
        ...(item.inputExtend || {}),
        ...(item.inputAttr || {}),
    }
}

function getSearchOptions(item: SearchItem): SearchOption[] {
    const options = item.options || item.content || {}
    if (Array.isArray(options)) {
        return options.map((option) => {
            if (typeof option == 'object') {
                return {
                    label: String((option as SearchOption).label ?? (option as SearchOption).value),
                    value: (option as SearchOption).value ?? (option as SearchOption).label,
                }
            }
            return {
                label: String(option),
                value: option,
            }
        })
    }

    return Object.keys(options).map((key) => ({
        label: String(options[key]),
        value: key,
    }))
}

function initSearchForm() {
    searchItems.value.forEach((item) => {
        const field = getSearchField(item)
        if (field && state.searchForm[field] === undefined) {
            state.searchForm[field] = item.defaultValue ?? item.value ?? ''
        }
    })
}

function resetSearchForm() {
    Object.keys(state.searchForm).forEach((key) => {
        delete state.searchForm[key]
    })
    initSearchForm()
}

function buildRequestParams(initValue: SelectValue = '') {
    const initializing = !isEmptyValue(initValue)
    return {
        ...props.params,
        ...(initializing ? {} : state.searchForm),
        page: state.currentPage,
        limit: state.pageSize,
        initKey: props.pk,
        initValue,
        uuid: state.requestUuid,
    }
}

async function getData(initValue: SelectValue = '') {
    if (!props.remoteUrl && typeof props.request != 'function') {
        state.list = []
        state.total = 0
        state.initialized = true
        return
    }

    const currentRequestIndex = ++requestIndex
    const initializing = !isEmptyValue(initValue)
    const keyword = initializing ? '' : state.keyword
    const params = buildRequestParams(initValue)

    state.loading = true
    try {
        const response = props.request
            ? await props.request({ select: true, quickSearch: keyword, ...params }, keyword)
            : await getSelectData(props.remoteUrl, keyword, params)
        if (currentRequestIndex !== requestIndex) {
            return
        }

        const data = props.parseData ? props.parseData(response) : Array.isArray(response.data) ? { options: response.data } : response.data
        const rows = (data.options || data.list || []) as anyObj[]

        state.list = rows
        state.total = Number(data.total || 0)
        cacheRows(rows)
        applyTableSelection()
        syncSelectedLabel()

        state.initialized = !initializing
    } finally {
        if (currentRequestIndex === requestIndex) {
            state.loading = false
        }
    }
}

function onFocus() {
    if (!state.initialized) {
        getData()
    }
}

function onVisibleChange(visible: boolean) {
    if (visible && !state.initialized) {
        getData()
    }
    if (!visible) {
        syncSelectedLabel()
    }
}

function onChangeSelect(value: SelectValue) {
    setValue(value, true, true)
    applyTableSelection()
}

function onClear() {
    setValue(valueOnClear.value, true, true)
    applyTableSelection()
    emit('clear')
}

function onRemoveTag() {
    nextTick(() => {
        applyTableSelection()
        syncSelectedLabel()
    })
}

function onTableRowClick(row: anyObj) {
    const value = getRowValue(row)
    if (!value) {
        return
    }

    cacheRows([row])
    if (props.multiple) {
        const values = new Set(getValueArray())
        if (values.has(value)) {
            values.delete(value)
        } else {
            values.add(value)
        }
        setValue(Array.from(values), true, true)
        applyTableSelection()
    } else {
        setValue(value, true, true)
        applyTableSelection()
        selectRef.value?.blur()
    }
}

function onTableSelect(selection: anyObj[], row: anyObj) {
    const value = getRowValue(row)
    const values = new Set(getValueArray())
    if (selection.some((item) => getRowValue(item) == value)) {
        values.add(value)
        cacheRows([row])
    } else {
        values.delete(value)
    }
    setValue(Array.from(values), true, true)
}

function onTableSelectAll(selection: anyObj[]) {
    const selectedValues = new Set(selection.map((row) => getRowValue(row)))
    const values = new Set(getValueArray())
    state.list.forEach((row) => {
        const value = getRowValue(row)
        if (selectedValues.has(value)) {
            values.add(value)
            cacheRows([row])
        } else {
            values.delete(value)
        }
    })
    setValue(Array.from(values), true, true)
}

function onSearchSubmit() {
    state.currentPage = 1
    state.initialized = false
    getData()
}

function onSearchReset() {
    state.keyword = ''
    resetSearchForm()
    onSearchSubmit()
}

function setKeyword(keyword: string) {
    state.keyword = keyword
}

function onCurrentPageChange(page: number) {
    state.currentPage = page
    getData()
}

function onPageSizeChange(pageSize: number) {
    state.pageSize = pageSize
    state.currentPage = 1
    getData()
}

onMounted(() => {
    initSearchForm()
    setValue(props.modelValue, false)

    if (!isEmptyValue(state.value)) {
        getData(state.value)
    }

    setTimeout(() => {
        if (window?.IntersectionObserver) {
            io = new IntersectionObserver((entries) => {
                for (const key in entries) {
                    if (!entries[key].isIntersecting) {
                        selectRef.value?.blur()
                    }
                }
            })
            if (selectRef.value?.$el instanceof Element) {
                io.observe(selectRef.value.$el)
            }
        }
    }, 500)
})

onUnmounted(() => {
    io?.disconnect()
})

watch(
    () => props.modelValue,
    (newValue) => {
        if (getWatchValue(state.value) != getWatchValue(newValue)) {
            setValue(newValue, false)
            if (!isEmptyValue(state.value)) {
                getData(state.value)
            } else {
                applyTableSelection()
            }
        }
    }
)

watch(
    () => props.search,
    () => {
        initSearchForm()
    },
    { deep: true }
)

function getWatchValue(value: SelectValue) {
    return Array.isArray(value) ? `array:${value.join(',')}` : `${typeof value}:${String(value)}`
}

function getRef() {
    return selectRef.value
}

function focus() {
    selectRef.value?.focus()
}

function blur() {
    selectRef.value?.blur()
}

defineExpose({
    blur,
    focus,
    getRef,
})
</script>

<style scoped lang="scss">
.table-select {
    position: relative;
}

:deep(.table-select-popper) {
    min-width: min(var(--table-select-popper-min-width), calc(100vw - 32px));
}

.table-select-panel {
    padding: 12px;
    box-sizing: border-box;
}

.table-select-search {
    margin-bottom: 10px;
}

.table-select-search-form {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: flex-start;

    :deep(.el-form-item) {
        margin: 0;
    }

    :deep(.el-form-item__label) {
        padding-right: 6px;
    }
}

.table-select-search-actions {
    white-space: nowrap;
}

.table-select-pagination {
    margin-top: 10px;
    justify-content: flex-end;
}
</style>
