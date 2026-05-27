<template>
    <el-dialog
        class="x-operate-dialog error-log-dialog"
        :model-value="xTable.form.operate === 'Info'"
        @close="xTable.toggleForm"
        :destroy-on-close="true"
        width="70%"
    >
        <template #header>
            <div class="title" v-drag="['.x-operate-dialog', '.el-dialog__header']" v-zoom="'.x-operate-dialog'">
                {{ t('system.errorLog.Log info') }}
            </div>
        </template>
        <div v-loading="xTable.form.loading" class="x-operate-form x-Info-form error-log-info">
            <el-input v-model="content" class="error-log-textarea" type="textarea" readonly resize="none" />
        </div>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type XTable from '/@/utils/xTable'

const xTable = inject('xTable') as XTable
const { t } = useI18n()

const content = computed({
    get: () => String(xTable.form.items?.content ?? ''),
    set: (value: string) => {
        xTable.form.items = {
            ...xTable.form.items,
            content: value,
        }
    },
})
</script>

<style scoped lang="scss">
:global(.error-log-dialog) {
    padding-bottom: 0;
}

:global(.error-log-dialog .el-dialog__body) {
    height: min(68vh, 720px);
    overflow: hidden;
    padding-top: 0;
}

.error-log-info {
    box-sizing: border-box;
    width: 100%;
    height: 100%;
}

.error-log-textarea {
    height: 100%;
}

.error-log-textarea :deep(.el-textarea__inner) {
    height: 100% !important;
    max-height: 100% !important;
    min-height: 100% !important;
    font-family: Menlo, Monaco, Consolas, 'Courier New', monospace;
    line-height: 1.5;
    overflow: auto;
    white-space: pre;
}
</style>
