<template>
    <el-dialog
        class="x-operate-dialog x-config-group-dialog"
        append-to-body
        :close-on-click-modal="false"
        :model-value="props.modelValue"
        width="min(760px, calc(100vw - 24px))"
        @open="resetForm"
        @close="closeForm"
    >
        <template #header>
            <div class="title" v-drag="['.x-config-group-dialog', '.el-dialog__header']" v-zoom="'.x-config-group-dialog'">
                {{ t('system.config.Config group') }}
            </div>
        </template>
        <el-scrollbar class="x-table-form-scrollbar">
            <div class="x-operate-form x-config-group-form" :style="config.layout.shrink ? '' : 'width: calc(100% - ' + state.labelWidth / 2 + 'px)'">
                <el-form
                    ref="formRef"
                    @submit.prevent=""
                    :rules="rules"
                    :model="state.form"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="160"
                >
                    <FormItem :label="t('system.config.Config group')" type="array" v-model="state.form.config_group" prop="config_group" />
                </el-form>
            </div>
        </el-scrollbar>
        <template #footer>
            <div class="x-config-group-footer" :style="'width: calc(100% - ' + state.labelWidth / 1.8 + 'px)'">
                <el-button @click="closeForm">{{ t('Cancel') }}</el-button>
                <el-button v-blur :loading="state.submitLoading" @click="onSubmit" type="primary">{{ t('Save') }}</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import type { FormRules } from 'element-plus'
import { reactive, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { postData } from '/@/api/admin/system/config'
import FormItem from '/@/components/formItem/index.vue'
import { useConfig } from '/@/stores/config'

type ConfigGroupItem = {
    key: string
    value: string
}

interface Props {
    modelValue: boolean
    configGroupList: ConfigGroupItem[]
    scopeParams: anyObj
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: false,
    configGroupList: () => [],
    scopeParams: () => {
        return {}
    },
})

const emits = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}>()

const { t } = useI18n()
const config = useConfig()
const formRef = useTemplateRef('formRef')

const state: {
    labelWidth: number
    submitLoading: boolean
    form: {
        config_group: ConfigGroupItem[]
    }
} = reactive({
    labelWidth: 180,
    submitLoading: false,
    form: {
        config_group: [],
    },
})

const resetForm = () => {
    state.form.config_group = props.configGroupList.map((item) => ({
        key: item.key,
        value: item.value,
    }))
}

const rules = reactive<FormRules>({
    config_group: [
        {
            validator: (_rule, value: ConfigGroupItem[], callback) => {
                if (!Array.isArray(value) || value.length < 1 || value.some((item) => !item.key || !item.value)) {
                    callback(new Error(t('system.config.Please complete configuration group')))
                    return
                }
                callback()
            },
            trigger: 'change',
        },
    ],
})

const closeForm = () => {
    emits('update:modelValue', false)
}

const onSubmit = () => {
    formRef.value?.validate((valid) => {
        if (!valid) {
            return
        }

        state.submitLoading = true
        postData(
            'edit',
            {
                config_group: state.form.config_group,
            },
            props.scopeParams
        )
            .then(() => {
                emits('success')
                emits('update:modelValue', false)
            })
            .finally(() => {
                state.submitLoading = false
            })
    })
}
</script>

<style scoped lang="scss">
.x-config-group-form {
    min-height: 160px;
}

.x-config-group-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

@media screen and (max-width: 640px) {
    .x-config-group-form {
        width: 100% !important;
    }

    .x-config-group-footer {
        width: 100% !important;
        gap: 8px;
    }

    .x-config-group-form :deep(.el-form-item) {
        display: block;
    }

    .x-config-group-form :deep(.el-form-item__label) {
        width: auto !important;
        justify-content: flex-start;
        margin-bottom: 8px;
    }

    .x-config-group-form :deep(.el-form-item__content) {
        margin-left: 0 !important;
    }

    .x-config-group-form :deep(.x-array-key),
    .x-config-group-form :deep(.x-array-value) {
        display: none;
    }

    .x-config-group-form :deep(.x-array-item) {
        display: flex;
        flex-wrap: wrap;
        row-gap: 8px;
    }

    .x-config-group-form :deep(.x-array-item .el-col) {
        flex: 0 0 100%;
        max-width: 100%;
    }

    .x-config-group-form :deep(.x-array-item .el-col:last-child) {
        flex: 0 0 auto;
        max-width: none;
    }

    .x-config-group-form :deep(.x-add-array-item) {
        float: none;
        width: 100%;
    }
}
</style>
