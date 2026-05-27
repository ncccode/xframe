<template>
    <!-- 对话框表单 -->
    <el-dialog
        class="x-operate-dialog"
        :close-on-click-modal="false"
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
                    @keyup.enter="xTable.onSubmit()"
                    v-model="xTable.form.items"
                    :label-position="config.layout.shrink ? 'top' : 'right'"
                    :label-width="xTable.form.labelWidth + 'px'"
                >
                    <el-form-item :label="t('utils.preview')">
                        <el-image
                            class="preview-img"
                            :preview-src-list="[xTable.form.items!.full_url]"
                            :src="previewRenderFormatter(xTable.form.items!, {}, xTable.form.items!.suffix)"
                        ></el-image>
                    </el-form-item>
                    <el-form-item :label="t('utils.Breakdown')">
                        <el-input
                            v-model="xTable.form.items!.topic"
                            type="string"
                            :placeholder="
                                t(
                                    'system.attachment.The file is saved in the directory, and the file will not be automatically transferred if the record is modified'
                                )
                            "
                            readonly
                        ></el-input>
                    </el-form-item>
                    <el-form-item :label="t('system.attachment.Physical path')">
                        <el-input
                            v-model="xTable.form.items!.url"
                            type="string"
                            :placeholder="t('system.attachment.File saving path Modifying records will not automatically transfer files')"
                            readonly
                        ></el-input>
                    </el-form-item>
                    <el-form-item :label="t('system.attachment.image width')">
                        <el-input
                            v-model="xTable.form.items!.width"
                            type="number"
                            :placeholder="t('system.attachment.Width of picture file')"
                        ></el-input>
                    </el-form-item>
                    <el-form-item :label="t('system.attachment.Picture height')">
                        <el-input
                            v-model="xTable.form.items!.height"
                            type="number"
                            :placeholder="t('system.attachment.Height of picture file')"
                        ></el-input>
                    </el-form-item>
                    <el-form-item :label="t('utils.Original name')">
                        <el-input v-model="xTable.form.items!.name" type="string" :placeholder="t('system.attachment.Original file name')"></el-input>
                    </el-form-item>
                    <el-form-item :label="t('system.attachment.file size')">
                        <el-input v-model="xTable.form.items!.size" type="number" :placeholder="t('system.attachment.File size (bytes)')"></el-input>
                    </el-form-item>
                    <el-form-item :label="t('system.attachment.mime type')">
                        <el-input v-model="xTable.form.items!.mimetype" type="string" :placeholder="t('system.attachment.File MIME type')"></el-input>
                    </el-form-item>
                    <el-form-item :label="t('utils.Upload (Reference) times')">
                        <el-input
                            v-model="xTable.form.items!.quote"
                            type="number"
                            :placeholder="t('system.attachment.Upload (Reference) times of this file')"
                        ></el-input>
                        <span class="block-help">
                            {{
                                t(
                                    'system.attachment.When the same file is uploaded multiple times, only one attachment record will be saved and added'
                                )
                            }}
                        </span>
                    </el-form-item>
                    <el-form-item :label="t('system.attachment.Storage mode')">
                        <el-input
                            v-model="xTable.form.items!.storage"
                            type="string"
                            :placeholder="t('system.attachment.Storage mode')"
                            readonly
                        ></el-input>
                    </el-form-item>
                    <el-form-item :label="t('system.attachment.SHA1 code')">
                        <el-input
                            v-model="xTable.form.items!.sha1"
                            type="string"
                            :placeholder="t('system.attachment.SHA1 encoding of file')"
                            readonly
                        ></el-input>
                    </el-form-item>
                </el-form>
            </div>
        </el-scrollbar>
        <template #footer>
            <div :style="'width: calc(100% - ' + xTable.form.labelWidth! / 1.8 + 'px)'">
                <el-button @click="xTable.toggleForm('')">{{ t('Cancel') }}</el-button>
                <el-button v-blur :loading="xTable.form.submitLoading" @click="xTable.onSubmit()" type="primary">
                    {{ xTable.form.operateIds!.length > 1 ? t('Save and edit next item') : t('Save') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type xTableClass from '/@/utils/xTable'
import { previewRenderFormatter } from './index'
import { useConfig } from '/@/stores/config'

const config = useConfig()
const xTable = inject('xTable') as xTableClass

const { t } = useI18n()
</script>

<style scoped lang="scss">
.preview-img {
    width: 60px;
    height: 60px;
}
</style>
