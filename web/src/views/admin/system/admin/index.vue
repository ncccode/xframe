<template>
    <div class="default-main x-table-box">
        <el-alert class="x-table-alert" v-if="xTable.table.remark" :title="xTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'comSearch', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('system.admin.username') + '/' + t('system.admin.nickname') })"
        />

        <!-- 表格 -->
        <!-- 要使用`el-table`组件原有的属性，直接加在Table标签上即可 -->
        <Table />

        <!-- 表单 -->
        <Form />
    </div>
</template>

<script setup lang="ts">
import { provide } from 'vue'
import xTableClass from '/@/utils/xTable'
import Form from './form.vue'
import Table from '/@/components/table/index.vue'
import TableHeader from '/@/components/table/header/index.vue'
import { defaultOptButtons } from '/@/components/table'
import { xTableApi } from '/@/api/common'
import { useAdminInfo } from '/@/stores/adminInfo'
import { useI18n } from 'vue-i18n'

defineOptions({
    name: 'system/admin',
})

const { t } = useI18n()
const adminInfo = useAdminInfo()

const optButtons = defaultOptButtons(['edit', 'delete'])
optButtons[1].display = (row) => {
    return row.id != adminInfo.id
}

const xTable = new xTableClass(
    new xTableApi('/admin/system/Admin/'),
    {
        column: [
            { type: 'selection', align: 'center', operator: false },
            { label: t('Id'), prop: 'id', align: 'center', operator: '=', operatorPlaceholder: t('Id'), width: 70 },
            { label: t('system.admin.username'), prop: 'username', align: 'center', operator: 'LIKE', operatorPlaceholder: t('Fuzzy query') },
            { label: t('system.admin.nickname'), prop: 'nickname', align: 'center', operator: 'LIKE', operatorPlaceholder: t('Fuzzy query') },
            { label: t('system.admin.group'), prop: 'group_name_arr', align: 'center', operator: false, render: 'tags' },
            { label: t('system.admin.avatar'), prop: 'avatar', align: 'center', render: 'image', operator: false },
            { label: t('system.admin.email'), prop: 'email', align: 'center', operator: 'LIKE', operatorPlaceholder: t('Fuzzy query') },
            { label: t('system.admin.mobile'), prop: 'mobile', align: 'center', operator: 'LIKE', operatorPlaceholder: t('Fuzzy query') },
            {
                label: t('system.admin.Last login'),
                prop: 'last_login_time',
                align: 'center',
                render: 'datetime',
                sortable: 'custom',
                operator: 'RANGE',
                width: 160,
            },
            { label: t('Create time'), prop: 'create_time', align: 'center', render: 'datetime', sortable: 'custom', operator: 'RANGE', width: 160 },
            {
                label: t('State'),
                prop: 'status',
                align: 'center',
                render: 'tag',
                custom: { 0: 'danger', 1: 'success' },
                replaceValue: { 0: t('Disable'), 1: t('Enable') },
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
        dblClickNotEditColumn: [undefined, 'status'],
    },
    {
        defaultItems: {
            status: 1,
        },
    }
)

provide('xTable', xTable)

xTable.mount()
xTable.getData()
</script>

<style scoped lang="scss"></style>
