<template>
    <el-dialog class="x-operate-dialog" :close-on-click-modal="false" :model-value="props.modelValue" @close="closeForm">
        <template #header>
            <div class="title" v-drag="['.x-operate-dialog', '.el-dialog__header']" v-zoom="'.x-operate-dialog'">
                {{ t('system.config.Add configuration item') }}
            </div>
        </template>
        <el-scrollbar class="x-table-form-scrollbar">
            <div class="x-operate-form x-add-form" :style="config.layout.shrink ? '' : 'width: calc(100% - ' + state.labelWidth / 2 + 'px)'">
                <el-form
                    ref="formRef"
                    @keyup.enter="onAddSubmit()"
                    :rules="rules"
                    :model="{ ...state.addConfig, ...state.formItemData }"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="160"
                >
                    <el-form-item class="config-group-form-item" :label="t('system.config.Variable group')" prop="group">
                        <div class="config-group-select">
                            <el-select
                                v-model="state.addConfig.group"
                                class="config-group-select-input"
                                clearable
                                :placeholder="t('Please select field', { field: t('system.config.Variable group') })"
                            >
                                <el-option v-for="(label, key) in props.configGroup" :key="key" :label="label" :value="key" />
                            </el-select>
                            <el-button v-if="props.canManageGroups" v-blur icon="el-icon-Plus" @click="state.showGroupForm = true">
                                {{ t('system.config.Add group') }}
                            </el-button>
                        </div>
                    </el-form-item>
                    <CreateFormItemData v-model="state.formItemData" />
                    <FormItem :label="t('Sort')" type="number" v-model="state.addConfig.sort" prop="sort" />
                </el-form>
            </div>
        </el-scrollbar>
        <GroupForm
            v-model="state.showGroupForm"
            :config-group-list="props.configGroupList"
            :scope-params="props.scopeParams"
            @success="onGroupSuccess"
        />
        <template #footer>
            <div :style="'width: calc(100% - ' + state.labelWidth / 1.8 + 'px)'">
                <el-button @click="closeForm">{{ t('Cancel') }}</el-button>
                <el-button v-blur :loading="state.submitLoading" @click="onAddSubmit()" type="primary"> {{ t('Add') }} </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { reactive, useTemplateRef } from 'vue'
import FormItem from '/@/components/formItem/index.vue'
import type { FormRules } from 'element-plus'
import { buildValidatorData } from '/@/utils/validate'
import { postData } from '/@/api/admin/system/config'
import CreateFormItemData from '/@/components/formItem/createData.vue'
import GroupForm from './group.vue'
import { useI18n } from 'vue-i18n'
import { useConfig } from '/@/stores/config'

const config = useConfig()

type ConfigGroupItem = {
    key: string
    value: string
}

interface Props {
    modelValue: boolean
    configGroup: anyObj
    configGroupList: ConfigGroupItem[]
    canManageGroups: boolean
    scopeParams: anyObj
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: false,
    configGroup: () => {
        return {}
    },
    configGroupList: () => [],
    canManageGroups: false,
    scopeParams: () => {
        return {}
    },
})

const emits = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}>()

const closeForm = () => {
    emits('update:modelValue', false)
}

const { t } = useI18n()
const formRef = useTemplateRef('formRef')

const state: {
    inputTypes: anyObj
    labelWidth: number
    submitLoading: boolean
    showGroupForm: boolean
    addConfig: {
        group: string
        sort: number
        content: string
    }
    formItemData: anyObj
} = reactive({
    inputTypes: {},
    labelWidth: 180,
    submitLoading: false,
    showGroupForm: false,
    addConfig: {
        group: '',
        sort: 0,
        content: '',
    },
    formItemData: {
        dict: `key1=value1
key2=value2`,
    },
})

const onGroupSuccess = () => {
    emits('success')
}

const rules = reactive<FormRules>({
    group: [
        buildValidatorData({
            name: 'required',
            trigger: 'change',
            message: t('Please select field', { field: t('system.config.Variable group') }),
        }),
    ],
    name: [
        buildValidatorData({ name: 'required', title: t('system.config.Variable name') }),
        buildValidatorData({ name: 'varName', message: t('Please enter the correct field', { field: t('system.config.Variable name') }) }),
    ],
    title: [buildValidatorData({ name: 'required', title: t('system.config.Variable title') })],
    type: [
        buildValidatorData({
            name: 'required',
            trigger: 'change',
            message: t('Please select field', { field: t('system.config.Variable type') }),
        }),
    ],
    sort: [buildValidatorData({ name: 'integer', title: t('system.config.number') })],
})

const onAddSubmit = () => {
    formRef.value?.validate((valid) => {
        if (valid) {
            const formItemData = { ...state.formItemData }
            const content = formItemData.dict ?? ''
            delete formItemData.dict
            state.submitLoading = true
            postData('add', { ...state.addConfig, content, ...formItemData }, props.scopeParams)
                .then(() => {
                    emits('success')
                    emits('update:modelValue', false)
                })
                .finally(() => {
                    state.submitLoading = false
                })
        }
    })
}
</script>

<style scoped lang="scss">
.config-group-select {
    display: flex;
    width: 100%;
    gap: 12px;
    align-items: center;
}

.config-group-select-input {
    flex: 1;
}
</style>
