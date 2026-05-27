<template>
    <div class="default-main x-table-box">
        <el-alert class="x-table-alert" v-if="xTable.table.remark" :title="xTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <TableHeader
            :buttons="['refresh', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('system.adminLog.title') })"
        />
        <!-- 表格 -->
        <!-- 要使用`el-table`组件原有的属性，直接加在Table标签上即可 -->
        <Table />

        <Info />
    </div>
</template>

<script setup lang="ts">
import { concat, cloneDeep } from 'lodash-es'
import { provide } from 'vue'
import xTableClass from '/@/utils/xTable'
import Table from '/@/components/table/index.vue'
import TableHeader from '/@/components/table/header/index.vue'
import { defaultOptButtons } from '/@/components/table'
import { xTableApi } from '/@/api/common'
import { useI18n } from 'vue-i18n'
import Info from './info.vue'
import { buildJsonToElTreeData } from '/@/utils/common'

defineOptions({
    name: 'system/adminLog',
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
        click: (row: TableRow) => {
            infoButtonClick(row)
        },
    },
]

optButtons = concat(optButtons, defaultOptButtons(['delete']))

const xTable = new xTableClass(new xTableApi('/admin/system/AdminLog/'), {
    column: [
        { type: 'selection', align: 'center', operator: false },
        { label: t('Id'), prop: 'id', align: 'center', operator: '=', operatorPlaceholder: t('Id'), width: 70 },
        {
            label: t('system.adminLog.admin_id'),
            prop: 'admin_id',
            align: 'center',
            operator: 'LIKE',
            operatorPlaceholder: t('Fuzzy query'),
            width: 70,
        },
        {
            label: t('system.adminLog.username'),
            prop: 'username',
            align: 'center',
            operator: 'LIKE',
            operatorPlaceholder: t('Fuzzy query'),
            width: 160,
        },
        { label: t('system.adminLog.title'), prop: 'title', align: 'center', operator: 'LIKE', operatorPlaceholder: t('Fuzzy query') },
        {
            show: false,
            label: t('system.adminLog.data'),
            prop: 'data',
            align: 'center',
            operator: 'LIKE',
            operatorPlaceholder: t('Fuzzy query'),
            showOverflowTooltip: true,
        },
        {
            label: t('system.adminLog.url'),
            prop: 'url',
            align: 'center',
            operator: 'LIKE',
            operatorPlaceholder: t('Fuzzy query'),
            showOverflowTooltip: true,
            render: 'url',
        },
        { label: t('system.adminLog.ip'), prop: 'ip', align: 'center', operator: 'LIKE', operatorPlaceholder: t('Fuzzy query'), render: 'tag' },
        {
            label: t('system.adminLog.useragent'),
            prop: 'useragent',
            align: 'center',
            operator: 'LIKE',
            operatorPlaceholder: t('Fuzzy query'),
            showOverflowTooltip: true,
        },
        {
            label: t('Create time'),
            prop: 'create_time',
            align: 'center',
            render: 'datetime',
            sortable: 'custom',
            operator: 'RANGE',
            width: 160,
        },
        {
            label: t('Operate'),
            align: 'center',
            width: '100',
            render: 'buttons',
            buttons: optButtons,
            operator: false,
        },
    ],
    dblClickNotEditColumn: [undefined],
})

// 利用双击单元格前钩子重写双击操作
xTable.before.onTableDblclick = ({ row }) => {
    infoButtonClick(row)
    return false
}

xTable.mount()
xTable.getData()

provide('xTable', xTable)

/** 点击查看详情按钮响应 */
const infoButtonClick = (row: TableRow) => {
    if (!row) return
    // 数据来自表格数据,未重新请求api,深克隆,不然可能会影响表格
    let rowClone = cloneDeep(row)
    rowClone.data = rowClone.data ? [{ label: '点击展开', children: buildJsonToElTreeData(JSON.parse(rowClone.data)) }] : []
    xTable.form.extend!['info'] = rowClone
    xTable.form.operate = 'Info'
}
</script>

<style scoped lang="scss"></style>
