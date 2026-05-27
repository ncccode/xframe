<template>
    <div class="default-main">
        <el-row v-loading="state.loading" :gutter="20">
            <el-col :span="24">
                <el-form
                    v-if="!state.loading"
                    ref="formRef"
                    @submit.prevent=""
                    @keyup.enter="onSubmit()"
                    :model="state.form"
                    :rules="state.rules"
                    :label-position="'top'"
                    :key="state.formKey"
                >
                    <el-tabs v-model="state.activeTab" type="border-card" :before-leave="onBeforeLeave">
                        <el-tab-pane class="config-tab-pane" v-for="(group, key) in state.config" :key="key" :name="key" :label="group.title">
                            <div class="config-form-item" v-for="(item, idx) in group.list" :key="idx">
                                <template v-if="item.group == state.activeTab">
                                    <FormItem
                                        v-if="item.type == 'number'"
                                        :label="item.title"
                                        :type="item.type"
                                        v-model="state.form[item.name]"
                                        :attr="{ prop: item.name, ...item.extend }"
                                        :input-attr="{ ...item.input_extend }"
                                        :tip="item.tip"
                                        :key="'number-' + item.id"
                                    />
                                    <!-- 富文本在dialog内全屏编辑器时必须拥有很高的z-index，此处选择单独为editor设定较小的z-index -->
                                    <FormItem
                                        v-else-if="item.type == 'editor'"
                                        :label="item.title"
                                        :type="item.type"
                                        @keyup.enter.stop=""
                                        @keyup.ctrl.enter="onSubmit()"
                                        v-model="state.form[item.name]"
                                        :attr="{ prop: item.name, ...item.extend }"
                                        :input-attr="{
                                            style: {
                                                zIndex: 99,
                                            },
                                            ...item.input_extend,
                                        }"
                                        :tip="item.tip"
                                        :key="'editor-' + item.id"
                                    />
                                    <FormItem
                                        v-else-if="item.type == 'textarea'"
                                        :label="item.title"
                                        :type="item.type"
                                        @keyup.enter.stop=""
                                        @keyup.ctrl.enter="onSubmit()"
                                        v-model="state.form[item.name]"
                                        :attr="{ prop: item.name, ...item.extend }"
                                        :input-attr="{ rows: 3, ...item.input_extend }"
                                        :tip="item.tip"
                                        :key="'textarea-' + item.id"
                                    />
                                    <FormItem
                                        v-else
                                        :label="item.title"
                                        :type="item.type"
                                        v-model="state.form[item.name]"
                                        :attr="{ prop: item.name, ...item.extend }"
                                        :input-attr="!isEmpty(item.content) ? { content: item.content, ...item.input_extend } : item.input_extend"
                                        :tip="item.tip"
                                        :key="'other-' + item.id"
                                    />
                                    <div class="config-form-item-name">${{ item.name }}</div>
                                    <div class="del-config-form-item">
                                        <el-popconfirm
                                            @confirm="onDelConfig(item)"
                                            v-if="item.allow_del && state.permissions.del"
                                            :confirmButtonText="t('Delete')"
                                            :title="t('system.config.Are you sure to delete the configuration item?')"
                                        >
                                            <template #reference>
                                                <Icon class="close-icon" size="15" name="el-icon-Close" />
                                            </template>
                                        </el-popconfirm>
                                    </div>
                                </template>
                            </div>
                            <el-button v-if="group.list?.length" type="primary" @click="onSubmit()">{{ t('Save') }}</el-button>
                        </el-tab-pane>
                        <el-tab-pane
                            v-if="state.permissions.add"
                            name="add_config"
                            class="config-tab-pane config-tab-pane-add"
                            :label="t('system.config.Add configuration item')"
                        ></el-tab-pane>
                    </el-tabs>
                </el-form>
            </el-col>
        </el-row>

        <AddFrom
            v-if="!state.loading && state.permissions.add"
            v-model="state.showAddForm"
            :config-group="state.configGroup"
            :config-group-list="state.configGroupList"
            :can-manage-groups="state.canManageGroups"
            :scope-params="getScopeParams()"
            @success="getData"
        />
    </div>
</template>

<script setup lang="ts">
import type { FormItemRule } from 'element-plus'
import { isEmpty } from 'lodash-es'
import { onActivated, onDeactivated, onMounted, onUnmounted, reactive, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import AddFrom from './add.vue'
import { del, index, postData } from '/@/api/admin/system/config'
import FormItem from '/@/components/formItem/index.vue'
import { adminBaseRoutePath } from '/@/router/static/adminBase'
import type { SiteConfig } from '/@/stores/interface'
import { useSiteConfig } from '/@/stores/siteConfig'
import { uuid } from '/@/utils/random'
import { buildValidatorData, type buildValidatorParams } from '/@/utils/validate'
import { closeHotUpdate, openHotUpdate } from '/@/utils/vite'

defineOptions({
    name: 'system/config',
})

const { t } = useI18n()
const route = useRoute()
const siteConfig = useSiteConfig()

const formRef = useTemplateRef('formRef')

type ConfigGroupItem = {
    key: string
    value: string
}

const state: {
    loading: boolean
    config: anyObj
    remark: string
    configGroup: anyObj
    configGroupList: ConfigGroupItem[]
    canManageGroups: boolean
    permissions: {
        add: boolean
        del: boolean
    }
    activeTab: string
    showAddForm: boolean
    rules: Partial<Record<string, FormItemRule[]>>
    form: anyObj
    formKey: string
} = reactive({
    loading: true,
    config: [],
    remark: '',
    configGroup: {},
    configGroupList: [],
    canManageGroups: false,
    permissions: {
        add: false,
        del: false,
    },
    activeTab: '',
    showAddForm: false,
    rules: {},
    form: {},
    formKey: uuid(),
})

const getScopeParams = () => {
    return { ...route.query }
}

const getData = () => {
    index(getScopeParams())
        .then((res) => {
            state.config = res.data.list
            state.remark = res.data.remark
            state.configGroup = res.data.configGroup
            state.configGroupList = res.data.configGroupList ?? []
            state.canManageGroups = !!res.data.canManageGroups
            state.permissions = {
                add: !!res.data.permissions?.add,
                del: !!res.data.permissions?.del,
            }
            state.loading = false
            state.activeTab = ''
            for (const key in state.configGroup) {
                state.activeTab = key
                break
            }
            let formNames: anyObj = {}
            let rules: Partial<Record<string, FormItemRule[]>> = {}
            for (const key in state.config) {
                const configList = state.config[key].list ?? []
                for (const lKey in configList) {
                    if (configList[lKey].rule) {
                        let ruleStr = configList[lKey].rule.split(',')
                        let ruleArr: anyObj = []
                        ruleStr.forEach((item: string) => {
                            ruleArr.push(buildValidatorData({ name: item as buildValidatorParams['name'], title: configList[lKey].title }))
                        })
                        rules = Object.assign(rules, {
                            [configList[lKey].name]: ruleArr,
                        })
                    }
                    formNames[configList[lKey].name] = configList[lKey].type == 'number' ? parseFloat(configList[lKey].value) : configList[lKey].value
                }
            }

            state.form = formNames
            state.rules = rules
            state.formKey = uuid()
        })
        .catch(() => {
            state.loading = false
        })
}

const onBeforeLeave = (newTabName: string | number) => {
    if (newTabName == 'add_config') {
        if (!state.permissions.add) {
            return false
        }
        state.showAddForm = true
        return false
    }
}

const onSubmit = () => {
    formRef.value?.validate((valid) => {
        if (valid) {
            // 只提交当前tab的表单数据
            const formData: anyObj = {}
            for (const key in state.config) {
                if (key != state.activeTab) {
                    continue
                }
                const configList = state.config[key].list ?? []
                for (const lKey in configList) {
                    formData[configList[lKey].name] = state.form[configList[lKey].name] ?? ''
                }
            }
            postData('edit', formData, getScopeParams()).then(() => {
                for (const key in siteConfig.$state) {
                    if (formData[key] && siteConfig.$state[key as keyof SiteConfig] != formData[key]) {
                        ;(siteConfig.$state[key as keyof SiteConfig] as any) = formData[key]
                    }
                }

                if (formData.admin_entrance && formData.admin_entrance != adminBaseRoutePath) {
                    const entranceUrl = new URL(window.location.href)
                    entranceUrl.pathname = entranceUrl.pathname.replace(new RegExp(`^${adminBaseRoutePath}(?=/|$)`), formData.admin_entrance)
                    window.open(entranceUrl.toString())
                    window.close()
                }
            })
        }
    })
}

const onDelConfig = (config: anyObj) => {
    del([config.id], getScopeParams()).then(() => {
        getData()
    })
}

onMounted(() => {
    getData()
    closeHotUpdate('config')
})
onActivated(() => {
    closeHotUpdate('config')
})
onDeactivated(() => {
    openHotUpdate('config')
})
onUnmounted(() => {
    openHotUpdate('config')
})
</script>

<style scoped lang="scss">
.el-tabs--border-card {
    border: none;
    box-shadow: var(--el-box-shadow-light);
    border-radius: var(--el-border-radius-base);
}
.el-tabs--border-card :deep(.el-tabs__header) {
    background-color: var(--x-bg-color);
    border-bottom: none;
    border-top-left-radius: var(--el-border-radius-base);
    border-top-right-radius: var(--el-border-radius-base);
}
.el-tabs--border-card :deep(.el-tabs__item.is-active) {
    border: 1px solid transparent;
}
.el-tabs--border-card :deep(.el-tabs__nav-wrap) {
    border-top-left-radius: var(--el-border-radius-base);
    border-top-right-radius: var(--el-border-radius-base);
}
.config-tab-pane {
    padding: 5px;
}
.config-tab-pane-add {
    width: 80%;
}
.config-form-item {
    display: flex;
    align-items: center;
    .el-form-item {
        flex: 13;
    }
    .config-form-item-name {
        opacity: 0;
        flex: 3;
        font-size: 13px;
        color: var(--el-text-color-disabled);
        padding-left: 20px;
    }
    .del-config-form-item {
        cursor: pointer;
        flex: 1;
        padding-left: 10px;
    }
    .close-icon {
        display: none;
    }
    &:hover {
        .config-form-item-name {
            opacity: 1;
        }
        .close-icon {
            display: block;
            color: var(--el-text-color-disabled) !important;
        }
    }
}
</style>
