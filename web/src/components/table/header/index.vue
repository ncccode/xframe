<template>
    <!-- 数据导出 -->
    <DataExport ref="exportRef" v-if="props.buttons.includes('export')" />

    <!-- 公共搜索 -->
    <el-collapse-transition>
        <ComSearch v-if="props.buttons.includes('comSearch') && xTable.table.showComSearch">
            <template v-for="(slot, idx) in $slots" :key="idx" #[idx]>
                <slot :name="idx"></slot>
            </template>
        </ComSearch>
    </el-collapse-transition>

    <!-- 操作按钮组 -->
    <div v-bind="$attrs" class="table-header x-scroll-style">
        <slot name="refreshPrepend"></slot>
        <el-tooltip v-if="props.buttons.includes('refresh')" :content="t('Refresh')" placement="top">
            <el-button v-blur @click="onAction('refresh', { loading: true })" color="#40485b" class="table-header-operate btns-ml-12" type="info">
                <Icon name="fa fa-refresh" />
            </el-button>
        </el-tooltip>
        <slot name="refreshAppend"></slot>
        <el-tooltip v-if="props.buttons.includes('add') && xTable.auth('add')" :content="t('Add')" placement="top">
            <el-button v-blur @click="onAction('add')" class="table-header-operate btns-ml-12" type="primary">
                <Icon name="fa fa-plus" />
                <span class="table-header-operate-text">{{ t('Add') }}</span>
            </el-button>
        </el-tooltip>
        <el-tooltip v-if="props.buttons.includes('edit') && xTable.auth('edit')" :content="t('Edit selected row')" placement="top">
            <el-button v-blur @click="onAction('edit')" :disabled="!enableBatchOpt" class="table-header-operate btns-ml-12" type="primary">
                <Icon name="fa fa-pencil" />
                <span class="table-header-operate-text">{{ t('Edit') }}</span>
            </el-button>
        </el-tooltip>
        <el-popconfirm
            v-if="props.buttons.includes('delete') && xTable.auth('del')"
            @confirm="onAction('delete')"
            :confirm-button-text="t('Delete')"
            :cancel-button-text="t('Cancel')"
            confirmButtonType="danger"
            :title="t('Are you sure to delete the selected record?')"
            :disabled="!enableBatchOpt"
        >
            <template #reference>
                <div class="btns-ml-12">
                    <el-tooltip :content="t('Delete selected row')" placement="top">
                        <el-button v-blur :disabled="!enableBatchOpt" class="table-header-operate" type="danger">
                            <Icon name="fa fa-trash" />
                            <span class="table-header-operate-text">{{ t('Delete') }}</span>
                        </el-button>
                    </el-tooltip>
                </div>
            </template>
        </el-popconfirm>
        <el-tooltip v-if="props.buttons.includes('copy') && xTable.auth('copy')" :content="t('Copy selected row')" placement="top">
            <el-button v-blur @click="onAction('copy')" :disabled="!enableBatchOpt" class="table-header-operate btns-ml-12" color="#626aef">
                <Icon name="el-icon-CopyDocument" />
                <span class="table-header-operate-text">{{ t('Copy') }}</span>
            </el-button>
        </el-tooltip>
        <el-tooltip v-if="props.buttons.includes('export') && xTable.auth('export')" :content="t('Export data')" placement="top">
            <el-button v-blur @click="onAction('export')" class="table-header-operate btns-ml-12" type="success">
                <Icon name="el-icon-Download" />
                <span class="table-header-operate-text">{{ t('Export') }}</span>
            </el-button>
        </el-tooltip>
        <el-tooltip
            v-if="props.buttons.includes('unfold')"
            :content="(xTable.table.expandAll ? t('Shrink') : t('Open')) + t('All submenus')"
            placement="top"
        >
            <el-button
                v-blur
                @click="xTable.onTableHeaderAction('unfold', { unfold: !xTable.table.expandAll })"
                class="table-header-operate btns-ml-12"
                :type="xTable.table.expandAll ? 'danger' : 'warning'"
            >
                <span class="table-header-operate-text">{{ xTable.table.expandAll ? t('Shrink all') : t('Expand all') }}</span>
            </el-button>
        </el-tooltip>

        <!-- slot -->
        <slot></slot>

        <!-- 右侧搜索框和工具按钮 -->
        <div class="table-search">
            <slot name="quickSearchPrepend"></slot>
            <el-input
                v-if="props.buttons.includes('quickSearch')"
                v-model="xTable.table.filter!.quickSearch"
                class="xs-hidden quick-search"
                @input="onSearchInput"
                :placeholder="quickSearchPlaceholder ? quickSearchPlaceholder : t('Search')"
                clearable
            />
            <div class="table-search-button-group" v-if="props.buttons.includes('columnDisplay') || props.buttons.includes('comSearch')">
                <el-dropdown v-if="props.buttons.includes('columnDisplay')" :max-height="380" :hide-on-click="false">
                    <el-button
                        class="table-search-button-item"
                        :class="props.buttons.includes('comSearch') ? 'right-border' : ''"
                        color="#dcdfe6"
                        plain
                        v-blur
                    >
                        <Icon size="14" name="el-icon-Grid" />
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item v-for="(item, idx) in columnDisplay" :key="idx">
                                <el-checkbox
                                    v-if="item.prop"
                                    @change="onChangeShowColumn($event, item.prop!)"
                                    :checked="!item.show"
                                    :model-value="item.show"
                                    size="small"
                                    :label="item.label"
                                />
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
                <el-tooltip
                    v-if="props.buttons.includes('comSearch')"
                    :disabled="xTable.table.showComSearch"
                    :content="t('Expand generic search')"
                    placement="top"
                >
                    <el-button
                        class="table-search-button-item"
                        @click="xTable.table.showComSearch = !xTable.table.showComSearch"
                        color="#dcdfe6"
                        plain
                        v-blur
                    >
                        <Icon size="14" name="el-icon-Search" />
                    </el-button>
                </el-tooltip>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { debounce } from 'lodash-es'
import { computed, inject, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ComSearch from '/@/components/table/comSearch/index.vue'
import DataExport from '/@/components/table/dataExport/index.vue'
import type xTableClass from '/@/utils/xTable'

const { t } = useI18n()
const xTable = inject('xTable') as xTableClass
const exportRef = ref<{ setTask: (data: anyObj) => void }>()

interface Props {
    buttons: HeaderOptButton[]
    quickSearchPlaceholder?: string
}
const props = withDefaults(defineProps<Props>(), {
    buttons: () => {
        return ['refresh', 'add', 'edit', 'delete']
    },
    quickSearchPlaceholder: '',
})

const columnDisplay = computed(() => {
    let columnDisplayArr = []
    for (let item of xTable.table.column) {
        item.type === 'selection' || item.render === 'buttons' || item.enableColumnDisplayControl === false ? '' : columnDisplayArr.push(item)
    }
    return columnDisplayArr
})

const enableBatchOpt = computed(() => (xTable.table.selection!.length > 0 ? true : false))

const onAction = (event: XTableHeaderActionEventName, data: anyObj = {}) => {
    xTable.onTableHeaderAction(event, data)
}

const onSearchInput = debounce(() => {
    xTable.onTableHeaderAction('quick-search', { keyword: xTable.table.filter!.quickSearch })
}, 500)

const onChangeShowColumn = (value: string | number | boolean, field: string) => {
    xTable.onTableHeaderAction('change-show-column', { field: field, value: value })
}

onMounted(() => {
    xTable.table.exportRef = exportRef.value
})
</script>

<style scoped lang="scss">
.table-header {
    position: relative;
    overflow-x: auto;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    width: 100%;
    max-width: 100%;
    background-color: var(--x-bg-color-overlay);
    border: 1px solid var(--x-border-color);
    border-bottom: none;
    padding: 13px 15px;
    font-size: 14px;
    .table-header-operate-text {
        margin-left: 6px;
    }
}
.btns-ml-12 + .btns-ml-12 {
    margin-left: 12px;
}
.table-search {
    display: flex;
    margin-left: auto;
    .quick-search {
        width: auto;
    }
}
.table-search-button-group {
    display: flex;
    margin-left: 12px;
    border: 1px solid var(--el-border-color);
    border-radius: var(--el-border-radius-base);
    overflow: hidden;
    button:focus,
    button:active {
        background-color: var(--x-bg-color-overlay);
    }
    button:hover {
        background-color: var(--el-color-info-light-7);
    }
    .table-search-button-item {
        height: 30px;
        border: none;
        border-radius: 0;
    }
    .el-button + .el-button {
        margin: 0;
    }
    .right-border {
        border-right: 1px solid var(--el-border-color);
    }
}

html.dark {
    .table-search-button-group {
        button:focus,
        button:active {
            background-color: var(--el-color-info-dark-2);
        }
        button:hover {
            background-color: var(--el-color-info-light-7);
        }
        button {
            background-color: var(--x-bg-color-overlay);
            el-icon {
                color: white !important;
            }
        }
    }
}
</style>
