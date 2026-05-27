<template>
    <div class="default-main x-table-box">
        <el-alert
            class="x-table-alert group-super-alert"
            v-if="!adminInfo.super"
            :title="t('system.group.Manage subordinate role groups here')"
            type="info"
            show-icon
        />
        <el-alert class="x-table-alert" v-if="xTable.table.remark" :title="xTable.table.remark" type="info" show-icon />

        <!-- 表格顶部菜单 -->
        <TableHeader
            :buttons="['refresh', 'add', 'edit', 'delete', 'unfold', 'quickSearch', 'columnDisplay']"
            :quick-search-placeholder="t('Quick search placeholder', { fields: t('system.group.GroupName') })"
        />

        <!-- 表格 -->
        <!-- 要使用`el-table`组件原有的属性，直接加在Table标签上即可 -->
        <Table ref="tableRef" :pagination="false" />

        <!-- 表单 -->
        <Form ref="formRef" />
    </div>
</template>

<script setup lang="ts">
import { onMounted, provide, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import Form from './form.vue'
import { getAdminRules } from '/@/api/admin/system/group'
import { xTableApi } from '/@/api/common'
import { defaultOptButtons } from '/@/components/table'
import TableHeader from '/@/components/table/header/index.vue'
import Table from '/@/components/table/index.vue'
import { useAdminInfo } from '/@/stores/adminInfo'
import xTableClass from '/@/utils/xTable'
import { getArrayKey } from '/@/utils/common'
import { uuid } from '/@/utils/random'

defineOptions({
    name: 'system/group',
})

const { t } = useI18n()
const adminInfo = useAdminInfo()
const formRef = useTemplateRef('formRef')
const tableRef = useTemplateRef('tableRef')

const xTable: xTableClass = new xTableClass(
    new xTableApi('/admin/system/Group/'),
    {
        expandAll: true,
        dblClickNotEditColumn: [undefined],
        column: [
            { type: 'selection', align: 'center' },
            { label: t('system.group.Group name'), prop: 'name', align: 'left', width: '200' },
            { label: t('system.group.jurisdiction'), prop: 'rules', align: 'center' },
            {
                label: t('State'),
                prop: 'status',
                align: 'center',
                render: 'tag',
                custom: { 0: 'danger', 1: 'success' },
                replaceValue: { 0: t('Disable'), 1: t('Enable') },
            },
            { label: t('Update time'), prop: 'update_time', align: 'center', width: '160', render: 'datetime' },
            { label: t('Create time'), prop: 'create_time', align: 'center', width: '160', render: 'datetime' },
            { label: t('Operate'), align: 'center', width: '130', render: 'buttons', buttons: defaultOptButtons(['edit', 'delete']) },
        ],
    },
    {
        defaultItems: {
            status: 1,
        },
    }
)

// 利用提交前钩子重写提交操作
xTable.before.onSubmit = ({ formEl, operate, items }) => {
    let submitCallback = () => {
        xTable.form.submitLoading = true
        xTable.api
            .postData(operate, {
                ...items,
                rules: formRef.value?.getCheckeds(),
            })
            .then((res) => {
                xTable.onTableHeaderAction('refresh', {})
                xTable.form.submitLoading = false
                xTable.form.operateIds?.shift()
                if (xTable.form.operateIds!.length > 0) {
                    xTable.toggleForm('Edit', xTable.form.operateIds)
                } else {
                    xTable.toggleForm()
                }
                xTable.runAfter('onSubmit', { res })
            })
            .catch(() => {
                xTable.form.submitLoading = false
            })
    }

    if (formEl) {
        xTable.form.ref = formEl
        formEl.validate((valid) => {
            if (valid) {
                submitCallback()
            }
        })
    } else {
        submitCallback()
    }
    return false
}

// 利用双击单元格前钩子重写双击操作
xTable.before.onTableDblclick = ({ row }) => {
    return xTable.table.extend!.adminGroup.indexOf(row.id) === -1
}

// 获取到数据后钩子
xTable.after.getData = ({ res }) => {
    if (!res) return
    xTable.table.extend!.adminGroup = res.data.group
    let buttonsKey = getArrayKey(xTable.table.column, 'render', 'buttons')
    xTable.table.column[buttonsKey].buttons!.forEach((value: OptButton) => {
        value.display = (row) => {
            return res.data.group.indexOf(row.id) === -1
        }
    })
}

// 切换表单后钩子
xTable.after.toggleForm = ({ operate }) => {
    if (operate == 'Add') {
        menuRuleTreeUpdate()
    }
}

// 编辑请求完成后钩子
xTable.after.getEditData = () => {
    menuRuleTreeUpdate()
}

const menuRuleTreeUpdate = () => {
    getAdminRules().then((res) => {
        xTable.form.extend!.menuRules = res.data.list

        if (xTable.form.items!.rules && xTable.form.items!.rules.length) {
            if (xTable.form.items!.rules.includes('*')) {
                let arr: number[] = []
                for (const key in xTable.form.extend!.menuRules) {
                    arr.push(xTable.form.extend!.menuRules[key].id)
                }
                xTable.form.extend!.defaultCheckedKeys = arr
            } else {
                xTable.form.extend!.defaultCheckedKeys = xTable.form.items!.rules
            }
        } else {
            xTable.form.extend!.defaultCheckedKeys = []
        }
        xTable.form.extend!.treeKey = uuid()
    })
}

provide('xTable', xTable)

onMounted(() => {
    xTable.table.ref = tableRef.value
    xTable.mount()
    xTable.getData()
})
</script>

<style scoped lang="scss">
.group-super-alert {
    margin-bottom: 10px;
}
</style>
