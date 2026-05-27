<template>
    <!-- 对话框表单 -->
    <el-dialog
        class="x-operate-dialog"
        :close-on-click-modal="false"
        :destroy-on-close="true"
        :model-value="['Add', 'Edit'].includes(xTable.form.operate!)"
        @close="xTable.toggleForm"
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
                        type="remoteSelect"
                        prop="pid"
                        :label="t('system.rule.Superior menu rule')"
                        v-model="xTable.form.items!.pid"
                        :placeholder="t('Click select')"
                        :input-attr="{
                            params: { isTree: true },
                            field: 'title',
                            remoteUrl: xTable.api.actionUrl.get('index'),
                            emptyValues: ['', null, undefined, 0],
                            valueOnClear: 0,
                        }"
                    />
                    <FormItem
                        :label="t('system.rule.Rule type')"
                        v-model="xTable.form.items!.type"
                        type="radio"
                        :input-attr="{
                            border: true,
                            content: {
                                menu_dir: t('system.rule.type menu_dir'),
                                menu: t('system.rule.type menu'),
                                button: t('system.rule.type button'),
                            },
                        }"
                    />
                    <el-form-item prop="title" :label="t('system.rule.Rule title')">
                        <el-input
                            v-model="xTable.form.items!.title"
                            type="string"
                            :placeholder="t('Please input field', { field: t('system.rule.Rule title') })"
                        ></el-input>
                    </el-form-item>
                    <el-form-item prop="name" :label="t('system.rule.Rule name')">
                        <el-input
                            v-model="xTable.form.items!.name"
                            type="string"
                            :placeholder="t('system.rule.English name, which does not need to start with `/admin`, such as system/menu')"
                        ></el-input>
                        <div class="block-help">
                            {{ t('system.rule.It will be registered as the web side routing name and used as the server side API authentication') }}
                        </div>
                    </el-form-item>
                    <el-form-item prop="path" v-if="xTable.form.items!.type != 'button'" :label="t('system.rule.Routing path')">
                        <el-input
                            v-model="xTable.form.items!.path"
                            type="string"
                            :placeholder="t('system.rule.The web side routing path (path) does not need to start with `/admin`, such as system/menu')"
                        ></el-input>
                        <div class="block-help">{{ t('system.rule.System config path query tips') }}</div>
                    </el-form-item>
                    <FormItem
                        v-if="xTable.form.operate && xTable.form.items!.type != 'button'"
                        type="icon"
                        :label="t('system.rule.Rule Icon')"
                        v-model="xTable.form.items!.icon"
                        :input-attr="{
                            showIconName: true,
                        }"
                    />
                    <FormItem
                        v-if="xTable.form.items!.type == 'menu'"
                        :label="t('system.rule.Menu type')"
                        v-model="xTable.form.items!.menu_type"
                        type="radio"
                        :input-attr="{
                            border: true,
                            content: { tab: t('system.rule.Menu type tab'), link: t('system.rule.Menu type link (offsite)'), iframe: 'Iframe' },
                        }"
                    />
                    <el-form-item
                        prop="url"
                        v-if="xTable.form.items!.menu_type != 'tab' && xTable.form.items!.type == 'menu'"
                        :label="t('system.rule.Link address')"
                    >
                        <el-input
                            v-model="xTable.form.items!.url"
                            type="string"
                            :placeholder="t('system.rule.Please enter the URL address of the link or iframe')"
                        ></el-input>
                    </el-form-item>
                    <el-form-item
                        prop="component"
                        v-if="xTable.form.items!.type == 'menu' && xTable.form.items!.menu_type == 'tab'"
                        :label="t('system.rule.Component path')"
                    >
                        <el-input
                            v-model="xTable.form.items!.component"
                            type="string"
                            :placeholder="t('system.rule.Web side component path, please start with /src, such as: /src/views/admin/dashboard')"
                        ></el-input>
                    </el-form-item>
                    <el-form-item
                        v-if="xTable.form.items!.type == 'menu' && xTable.form.items!.menu_type == 'tab'"
                        :label="t('system.rule.Extended properties')"
                    >
                        <el-select
                            class="w100"
                            v-model="xTable.form.items!.extend"
                            :placeholder="t('Please select field', { field: t('system.rule.Extended properties') })"
                        >
                            <el-option :label="t('system.rule.none')" value="none"></el-option>
                            <el-option :label="t('system.rule.Add as route only')" value="add_rules_only"></el-option>
                            <el-option :label="t('system.rule.Add as menu only')" value="add_menu_only"></el-option>
                        </el-select>
                        <div class="block-help">{{ t('system.rule.extend Title') }}</div>
                    </el-form-item>
                    <el-form-item :label="t('system.rule.Rule comments')">
                        <el-input
                            @keyup.enter.stop=""
                            @keyup.ctrl.enter="xTable.onSubmit(formRef)"
                            v-model="xTable.form.items!.remark"
                            type="textarea"
                            :autosize="{ minRows: 2, maxRows: 5 }"
                            :placeholder="
                                t(
                                    'system.rule.Use in controller `get_ route_ Remark()` function, which can obtain the value of this field for your own use, such as the banner file of the console'
                                )
                            "
                        ></el-input>
                    </el-form-item>
                    <el-form-item :label="t('system.rule.Rule sort')">
                        <el-input
                            v-model="xTable.form.items!.sort"
                            type="number"
                            :placeholder="t('system.rule.Please enter the sort value of menu rule')"
                        ></el-input>
                    </el-form-item>
                    <FormItem
                        v-if="xTable.form.operate == 'Add' && xTable.form.items!.type == 'menu'"
                        :label="t('system.rule.Create Page Button')"
                        v-model="xTable.form.items!.buttons"
                        type="selects"
                        :input-attr="{
                            content: {
                                index: t('system.rule.Create Page Button index'),
                                add: t('system.rule.Create Page Button add'),
                                edit: t('system.rule.Create Page Button edit'),
                                del: t('system.rule.Create Page Button del'),
                                sortable: t('system.rule.Create Page Button sortable'),
                            },
                        }"
                        :placeholder="t('system.rule.Please select the button for automatically creating the desired page')"
                        :block-help="t('system.rule.Create Page Button tips')"
                    />
                    <FormItem
                        :label="t('system.rule.cache')"
                        v-model="xTable.form.items!.keepalive"
                        type="radio"
                        :input-attr="{
                            border: true,
                            content: { 0: t('Disable'), 1: t('Enable') },
                        }"
                    />
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
import { reactive, inject, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import type xTableClass from '/@/utils/xTable'
import FormItem from '/@/components/formItem/index.vue'
import { buildValidatorData } from '/@/utils/validate'
import type { FormItemRule } from 'element-plus'
import { useConfig } from '/@/stores/config'

const config = useConfig()
const formRef = useTemplateRef('formRef')
const xTable = inject('xTable') as xTableClass

const { t } = useI18n()

const rules: Partial<Record<string, FormItemRule[]>> = reactive({
    title: [buildValidatorData({ name: 'required', title: t('system.rule.Rule title') })],
    name: [buildValidatorData({ name: 'required', title: t('system.rule.Rule name') })],
    path: [buildValidatorData({ name: 'required', title: t('system.rule.Routing path') })],
    url: [
        buildValidatorData({ name: 'required', title: t('system.rule.Link address') }),
        buildValidatorData({ name: 'url', message: t('system.rule.Please enter the correct URL') }),
    ],
    component: [buildValidatorData({ name: 'required', message: t('system.rule.Component path') })],
    pid: [
        {
            validator: (rule: any, val: string, callback: Function) => {
                if (!val) {
                    return callback()
                }
                if (parseInt(val) == parseInt(xTable.form.items!.id)) {
                    return callback(new Error(t('system.rule.The superior menu rule cannot be the rule itself')))
                }
                return callback()
            },
            trigger: 'blur',
        },
    ],
})
</script>

<style scoped lang="scss"></style>
