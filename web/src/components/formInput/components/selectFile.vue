<template>
    <el-dialog
        @close="emits('update:modelValue', false)"
        width="860px"
        :model-value="modelValue"
        class="x-upload-select-dialog"
        :title="t('utils.Select File')"
        :append-to-body="true"
        :destroy-on-close="true"
        top="4vh"
    >
        <div class="x-upload-select-material">
            <AttachmentMaterial
                ref="materialRef"
                :type="type"
                mode="picker"
                :limit="limit"
                file-size="100px"
                :page-size="12"
                @change="onSelectionChange"
            />
        </div>
        <template #footer>
            <div class="x-upload-select-footer">
                <span v-if="limit !== 0">
                    {{ t('system.attachment.Selected count', { count: state.selected.length }) }}
                    <template v-if="limit > 0"> / {{ limit }}</template>
                </span>
                <span v-else>{{ t('system.attachment.Selected count', { count: state.selected.length }) }}</span>
                <div>
                    <el-button v-blur @click="emits('update:modelValue', false)">{{ t('Cancel') }}</el-button>
                    <el-button v-blur type="primary" :disabled="!state.selected.length" @click="onChoice">
                        <Icon name="fa fa-check" color="#ffffff" />
                        <span>{{ t('utils.choice') }}</span>
                    </el-button>
                </div>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { reactive, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AttachmentMaterial from '/@/components/attachmentMaterial/index.vue'
import type { AttachmentItem, AttachmentMediaType } from '/@/api/admin/system/attachment'

interface Props {
    type?: AttachmentMediaType
    limit?: number
    modelValue: boolean
    returnFullUrl?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    type: 'file',
    limit: 0,
    modelValue: false,
    returnFullUrl: false,
})

const emits = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
    (e: 'choice', value: string[]): void
}>()

const { t } = useI18n()
const materialRef = useTemplateRef('materialRef')
const state = reactive({
    selected: [] as AttachmentItem[],
})

const onSelectionChange = (selected: AttachmentItem[]) => {
    state.selected = selected
}

const onChoice = () => {
    emits(
        'choice',
        state.selected.map((item) => (props.returnFullUrl ? item.full_url : item.url))
    )
    materialRef.value?.clearSelect()
    state.selected = []
}

watch(
    () => props.modelValue,
    (visible) => {
        if (!visible) {
            materialRef.value?.clearSelect()
            state.selected = []
        }
    }
)
</script>

<style scoped lang="scss">
.x-upload-select-material {
    height: 560px;
}

.x-upload-select-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.x-upload-select-footer :deep(.el-button span) {
    margin-left: 6px;
}
</style>
