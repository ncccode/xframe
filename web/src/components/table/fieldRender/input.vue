<template>
    <div>
        <el-input v-if="field.prop" v-model="cellValue" v-bind="inputAttr" :disabled="loading || inputAttr.disabled" @change="onChange" />
    </div>
</template>

<script setup lang="ts">
import { InputProps, TableColumnCtx } from 'element-plus'
import { computed, inject, ref } from 'vue'
import { getCellValue, invokeTableContextDataFun } from '/@/components/table/index'
import type xTableClass from '/@/utils/xTable'

interface Props {
    row: TableRow
    field: TableColumn
    column: TableColumnCtx<TableRow>
    index: number
}

const loading = ref(false)
const props = defineProps<Props>()
const xTable = inject('xTable') as xTableClass
const cellValue = ref(getCellValue(props.row, props.field, props.column, props.index))
const savedValue = ref(cellValue.value)

const inputAttr = computed(() => {
    return invokeTableContextDataFun<InputProps>(props.field.customRenderAttr?.input, {
        row: props.row,
        field: props.field,
        cellValue: cellValue.value,
        column: props.column,
        index: props.index,
    })
})

const onChange = (value: string | number) => {
    if (!props.field.prop || value === savedValue.value) return

    loading.value = true
    xTable.api
        .postData('edit', {
            [xTable.table.pk!]: props.row[xTable.table.pk!],
            [props.field.prop]: value,
        })
        .then(() => {
            cellValue.value = value
            savedValue.value = value
            xTable.onTableAction('field-change', { value: value, ...props })
        })
        .catch(() => {
            cellValue.value = savedValue.value
        })
        .finally(() => {
            loading.value = false
        })
}
</script>
