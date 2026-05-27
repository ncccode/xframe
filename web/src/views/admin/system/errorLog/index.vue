<template>
    <div class="default-main x-table-box">
        <el-alert class="x-table-alert" v-if="xTable.table.remark" :title="xTable.table.remark" type="info" show-icon />

        <TableHeader
            :buttons="['refresh', 'delete', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('system.errorLog.Search filename')"
        >
            <el-popconfirm
                v-if="auth('delall')"
                @confirm="delallButtonClick"
                :confirm-button-text="t('Delete')"
                :cancel-button-text="t('Cancel')"
                confirm-button-type="danger"
                :title="t('system.errorLog.Clear confirm')"
            >
                <template #reference>
                    <div class="btns-ml-12 error-log-clear-button">
                        <el-tooltip :content="t('system.errorLog.Clear all')" placement="top">
                            <el-button v-blur class="table-header-operate" type="danger">
                                <Icon name="fa fa-trash" />
                                <span class="table-header-operate-text">{{ t('system.errorLog.Clear all') }}</span>
                            </el-button>
                        </el-tooltip>
                    </div>
                </template>
            </el-popconfirm>
        </TableHeader>

        <Table />

        <Info />
    </div>
</template>

<script setup lang="ts">
import { concat } from 'lodash-es'
import { provide } from 'vue'
import { useI18n } from 'vue-i18n'
import { xTableApi } from '/@/api/common'
import { delall, downloadUrl, item } from '/@/api/admin/system/errorLog'
import { defaultOptButtons } from '/@/components/table'
import Table from '/@/components/table/index.vue'
import TableHeader from '/@/components/table/header/index.vue'
import { auth } from '/@/utils/common'
import xTableClass from '/@/utils/xTable'
import Info from './info.vue'

defineOptions({
    name: 'system/errorLog',
})

const { t } = useI18n()

let optButtons: OptButton[] = [
    {
        render: 'tipButton',
        name: 'info',
        title: 'Info',
        text: '',
        type: 'primary',
        icon: 'fa fa-search-plus',
        class: 'table-row-edit',
        disabledTip: false,
        display: () => auth('item'),
        click: (row: TableRow) => {
            infoButtonClick(row)
        },
    },
    {
        render: 'tipButton',
        name: 'download',
        title: 'Download',
        text: '',
        type: 'success',
        icon: 'fa fa-download',
        class: 'table-row-download',
        disabledTip: false,
        display: () => auth('download'),
        click: (row: TableRow) => {
            downloadButtonClick(row)
        },
    },
]

optButtons = concat(optButtons, defaultOptButtons(['delete']))

const xTable = new xTableClass(new xTableApi('/admin/system/ErrorLog/'), {
    pk: 'name',
    column: [
        { type: 'selection', align: 'center', operator: false },
        {
            label: t('system.errorLog.name'),
            prop: 'name',
            align: 'left',
            operator: false,
            showOverflowTooltip: true,
            minWidth: 220,
        },
        { label: t('system.errorLog.size'), prop: 'size', align: 'center', operator: false, width: 120 },
        { label: t('system.errorLog.time'), prop: 'time', align: 'center', operator: false, width: 180 },
        {
            label: t('Operate'),
            align: 'center',
            width: '150',
            render: 'buttons',
            buttons: optButtons,
            operator: false,
        },
    ],
    dblClickNotEditColumn: [undefined],
})

xTable.before.onTableDblclick = ({ row }) => {
    infoButtonClick(row)
    return false
}

xTable.mount()
xTable.getData()

provide('xTable', xTable)

const infoButtonClick = (row: TableRow) => {
    if (!row) return
    xTable.form.operate = 'Info'
    xTable.form.items = {
        content: '',
    }
    xTable.form.extend = {
        filename: row.name,
    }
    xTable.form.loading = true
    item(row.name)
        .then((res) => {
            xTable.form.items = {
                content: res.data.content,
            }
        })
        .finally(() => {
            xTable.form.loading = false
        })
}

const downloadButtonClick = (row: TableRow) => {
    if (!row) return
    window.open(downloadUrl(row.name), '_blank')
}

const delallButtonClick = () => {
    delall().then(() => {
        xTable.onTableHeaderAction('refresh', { event: 'delall' })
    })
}
</script>

<style scoped lang="scss">
.error-log-clear-button {
    margin-left: 12px;
}
</style>
