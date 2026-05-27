<template>
    <div class="default-main attachment-page">
        <el-alert class="x-table-alert attachment-page__alert" :title="t('system.attachment.Remark lang')" type="info" show-icon />
        <div class="attachment-page__panel">
            <el-tabs v-model="state.activeTab" class="attachment-page__tabs">
                <el-tab-pane v-for="item in tabs" :key="item.type" :label="item.label" :name="item.type" lazy>
                    <AttachmentMaterial :type="item.type" mode="page" file-size="120px" :limit="-1" :page-size="20" />
                </el-tab-pane>
            </el-tabs>
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import AttachmentMaterial from '/@/components/attachmentMaterial/index.vue'
import type { AttachmentMediaType } from '/@/api/admin/system/attachment'

defineOptions({
    name: 'system/attachment',
})

const { t } = useI18n()
const state = reactive({
    activeTab: 'image' as AttachmentMediaType,
})

const tabs: { type: AttachmentMediaType; label: string }[] = [
    { type: 'image', label: t('system.attachment.Images') },
    { type: 'video', label: t('system.attachment.Videos') },
    { type: 'file', label: t('system.attachment.Files') },
]
</script>

<style scoped lang="scss">
.attachment-page {
    min-width: 760px;
    margin-bottom: 0;
}

.attachment-page__panel {
    overflow: hidden;
    border: 1px solid var(--el-border-color-light);
    border-radius: var(--el-border-radius-base);
    background: var(--x-bg-color-overlay);
    box-shadow: none;
}

.attachment-page__tabs {
    height: calc(100vh - 180px);
    min-height: 420px;
    display: flex;
    flex-direction: column;
}

.attachment-page__tabs :deep(.el-tabs__header) {
    padding: 0 16px;
    margin-bottom: 0;
    background: var(--x-bg-color-overlay);
    border-bottom: 1px solid var(--el-border-color-light);
}

.attachment-page__tabs :deep(.el-tabs__nav-wrap::after) {
    display: none;
}

.attachment-page__tabs :deep(.el-tabs__item) {
    height: 44px;
    line-height: 44px;
    padding: 0 24px;
}

.attachment-page__tabs :deep(.el-tabs__active-bar) {
    height: 2px;
}

.attachment-page__tabs :deep(.el-tabs__content),
.attachment-page__tabs :deep(.el-tab-pane) {
    display: flex;
    flex: 1;
    min-height: 0;
    flex-direction: column;
}

.attachment-page__tabs :deep(.el-tabs__content) {
    overflow: hidden;
    background: var(--x-bg-color-overlay);
}

.attachment-page__tabs :deep(.attachment-material) {
    border: 0;
    background: var(--x-bg-color-overlay);
}
</style>
