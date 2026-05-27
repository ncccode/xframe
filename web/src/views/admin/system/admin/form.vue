<template>
    <!-- 对话框表单 -->
    <el-dialog
        class="x-operate-dialog"
        :close-on-click-modal="false"
        :model-value="['Add', 'Edit'].includes(xTable.form.operate!)"
        @close="xTable.toggleForm"
        :destroy-on-close="true"
    >
        <template #header>
            <div class="title" v-drag="['.x-operate-dialog', '.el-dialog__header']" v-zoom="'.x-operate-dialog'">
                {{ xTable.form.operate ? t(xTable.form.operate) : '' }}
            </div>
        </template>
        <el-scrollbar v-loading="xTable.form.loading" class="x-table-form-scrollbar">
            <div
                class="x-operate-form"
                :class="'x-' + xTable.form.operate + '-form'"
                :style="config.layout.shrink ? '' : 'width: calc(100% - ' + xTable.form.labelWidth! / 2 + 'px)'"
            >
                <el-form
                    ref="formRef"
                    @keyup.enter="xTable.onSubmit(formRef)"
                    :model="xTable.form.items"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="xTable.form.labelWidth + 'px'"
                    :rules="rules"
                    v-if="!xTable.form.loading"
                >
                    <FormItem
                        :label="t('system.admin.username')"
                        v-model="xTable.form.items!.username"
                        type="string"
                        prop="username"
                        :placeholder="t('system.admin.Administrator login')"
                    />
                    <FormItem
                        :label="t('system.admin.nickname')"
                        v-model="xTable.form.items!.nickname"
                        type="string"
                        prop="nickname"
                        :placeholder="t('Please input field', { field: t('system.admin.nickname') })"
                    />
                    <FormItem
                        :label="t('system.admin.group')"
                        v-model="xTable.form.items!.group_arr"
                        prop="group_arr"
                        type="remoteSelect"
                        :key="'group-' + xTable.form.items!.id"
                        :input-attr="{
                            multiple: true,
                            params: { isTree: true, absoluteAuth: adminInfo.id == xTable.form.items!.id ? 0 : 1 },
                            field: 'name',
                            remoteUrl: '/admin/system/Group/index',
                            placeholder: t('Click select'),
                        }"
                    />
                    <FormItem :label="t('system.admin.avatar')" type="image" v-model="xTable.form.items!.avatar" />
                    <FormItem
                        :label="t('system.admin.email')"
                        prop="email"
                        v-model="xTable.form.items!.email"
                        type="string"
                        :placeholder="t('Please input field', { field: t('system.admin.email') })"
                    />
                    <FormItem
                        :label="t('system.admin.mobile')"
                        prop="mobile"
                        v-model="xTable.form.items!.mobile"
                        type="string"
                        :placeholder="t('Please input field', { field: t('system.admin.mobile') })"
                    />
                    <FormItem
                        :label="t('system.admin.Password')"
                        prop="password"
                        v-model="xTable.form.items!.password"
                        type="password"
                        :input-attr="{ autocomplete: 'new-password' }"
                        :placeholder="
                            xTable.form.operate == 'Add'
                                ? t('Please input field', { field: t('system.admin.Password') })
                                : t('system.admin.Please leave blank if not modified')
                        "
                    />
                    <el-form-item prop="motto" :label="t('system.admin.Personal signature')">
                        <el-input
                            @keyup.enter.stop=""
                            @keyup.ctrl.enter="xTable.onSubmit(formRef)"
                            v-model="xTable.form.items!.motto"
                            type="textarea"
                            :placeholder="t('Please input field', { field: t('system.admin.Personal signature') })"
                        ></el-input>
                    </el-form-item>
                    <FormItem
                        :label="t('State')"
                        v-model="xTable.form.items!.status"
                        type="radio"
                        :input-attr="{
                            border: true,
                            content: { 0: t('Disable'), 1: t('Enable') },
                        }"
                    />
                </el-form>
            </div>
        </el-scrollbar>
        <template #footer>
            <div :style="'width: calc(100% - ' + xTable.form.labelWidth! / 1.8 + 'px)'">
                <el-button @click="xTable.toggleForm('')">{{ t('Cancel') }}</el-button>
                <el-button v-blur :loading="xTable.form.submitLoading" @click="xTable.onSubmit(formRef)" type="primary">
                    {{ xTable.form.operateIds && xTable.form.operateIds.length > 1 ? t('Save and edit next item') : t('Save') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { reactive, inject, watch, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import type xTableClass from '/@/utils/xTable'
import { regularPassword, buildValidatorData } from '/@/utils/validate'
import type { FormItemRule } from 'element-plus'
import FormItem from '/@/components/formItem/index.vue'
import { useAdminInfo } from '/@/stores/adminInfo'
import { useConfig } from '/@/stores/config'

const config = useConfig()
const adminInfo = useAdminInfo()
const formRef = useTemplateRef('formRef')
const xTable = inject('xTable') as xTableClass

const { t } = useI18n()

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    username: [buildValidatorData({ name: 'required', title: t('system.admin.username') }), buildValidatorData({ name: 'account' })],
    nickname: [buildValidatorData({ name: 'required', title: t('system.admin.nickname') })],
    group_arr: [buildValidatorData({ name: 'required', message: t('Please select field', { field: t('system.admin.group') }) })],
    email: [buildValidatorData({ name: 'email', message: t('Please enter the correct field', { field: t('system.admin.email') }) })],
    mobile: [buildValidatorData({ name: 'mobile', message: t('Please enter the correct field', { field: t('system.admin.mobile') }) })],
    password: [
        {
            validator: (rule: any, val: string, callback: Function) => {
                if (xTable.form.operate == 'Add') {
                    if (!val) {
                        return callback(new Error(t('Please input field', { field: t('system.admin.Password') })))
                    }
                } else {
                    if (!val) {
                        return callback()
                    }
                }
                if (!regularPassword(val)) {
                    return callback(new Error(t('validate.Please enter the correct password')))
                }
                return callback()
            },
            trigger: 'blur',
        },
    ],
})

watch(
    () => xTable.form.operate,
    (newVal) => {
        rules.password![0].required = newVal == 'Add'
    }
)
</script>

<style scoped lang="scss">
.avatar-uploader {
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    border-radius: var(--el-border-radius-small);
    box-shadow: var(--el-box-shadow-light);
    border: 1px dashed var(--el-border-color);
    cursor: pointer;
    overflow: hidden;
    width: 110px;
    height: 110px;
}
.avatar-uploader:hover {
    border-color: var(--el-color-primary);
}
.avatar {
    width: 110px;
    height: 110px;
    display: block;
}
.image-slot {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
}
</style>
