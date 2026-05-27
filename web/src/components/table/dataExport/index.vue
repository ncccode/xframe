<template>
    <el-dialog
        class="x-operate-dialog"
        :close-on-click-modal="false"
        :destroy-on-close="true"
        :model-value="xTable.table.showDataExport"
        :title="t('Export data')"
        draggable
        @close="closeDialog"
    >
        <el-scrollbar max-height="60vh">
            <el-alert
                v-if="task.name"
                class="data-export-alert"
                :title="`${task.name} ${t('Export task is running')}`"
                type="error"
                :closable="false"
                effect="dark"
            />
            <el-table :data="task.subtask" border style="width: 100%">
                <el-table-column prop="id" :label="t('No')" align="center" width="70" />
                <el-table-column :label="t('Task')">
                    <template #default="{ row }"> {{ row.min }} - {{ row.min + row.max }} </template>
                </el-table-column>
                <el-table-column align="center" :label="t('Status')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)">
                            {{ t(statusText(row.status)) }}
                        </el-tag>
                    </template>
                </el-table-column>
            </el-table>
            <el-progress class="task-progress" :stroke-width="16" :percentage="task.lastprogress" :text-inside="true" />
            <el-result v-if="Number(task.lastprogress) >= 100" icon="success" :title="t('Export task ready')">
                <template #extra>
                    <el-button type="primary" @click="onDownloadTaskZip">{{ t('Download ZIP') }}</el-button>
                </template>
            </el-result>
        </el-scrollbar>
    </el-dialog>
</template>

<script setup lang="ts">
import type { TagProps } from 'element-plus'
import { inject, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import type xTableClass from '/@/utils/xTable'

interface ExportSubtask {
    id: number
    status: number
    min: number
    max: number
    sql?: string
}

interface ExportTask {
    id?: number
    name?: string
    subtask: ExportSubtask[]
    subtask_progress: number
    lastprogress: number
}

interface ExportTaskPayload {
    task: ExportTask
    subtaskPage: ExportSubtask[][]
}

const { t } = useI18n()
const xTable = inject('xTable') as xTableClass

const state = reactive({
    requestIdx: 0,
    subtaskPage: [] as ExportSubtask[][],
})

const task = reactive<ExportTask>({
    subtask: [],
    subtask_progress: 0,
    lastprogress: 0,
})

const closeDialog = () => {
    xTable.table.showDataExport = false
}

const setTask = (data: ExportTaskPayload) => {
    Object.assign(task, data.task)
    state.subtaskPage = data.subtaskPage
    state.requestIdx = 0
    startTask()
}

const startTask = () => {
    const current = state.subtaskPage[state.requestIdx] ?? []
    if (!current.length) {
        task.lastprogress = 100
        return
    }

    const requests = current.map((item) => {
        setSubTaskStatus(item.id, 1)
        return xTable.api
            .export({
                id: task.id,
                subId: item.id,
                download: 0,
            })
            .then((res) => {
                setSubTaskStatus(res.data.subId, 2)
                task.lastprogress = Math.min(100, task.lastprogress + task.subtask_progress)
            })
            .catch(() => {
                setSubTaskStatus(item.id, 3)
                task.lastprogress = Math.min(100, task.lastprogress + task.subtask_progress)
            })
    })

    Promise.all(requests).then(() => {
        state.requestIdx++
        startTask()
    })
}

const setSubTaskStatus = (id: number, status: number) => {
    const item = task.subtask.find((subtask) => subtask.id === id)
    if (item) {
        item.status = status
    }
}

const onDownloadTaskZip = () => {
    xTable.api
        .export({
            id: task.id,
            getTaskZip: 1,
        })
        .then((res) => {
            window.location.href = res.data.url
        })
}

const statusText = (status: number) => {
    const labels: Record<number, string> = {
        0: 'Ready',
        1: 'Running',
        2: 'Completed',
        3: 'Failed',
    }
    return labels[status] ?? 'Unknown'
}

const statusTagType = (status: number): TagProps['type'] => {
    const types: Record<number, TagProps['type']> = {
        0: 'info',
        1: 'primary',
        2: 'success',
        3: 'danger',
    }
    return types[status] ?? 'info'
}

defineExpose({
    setTask,
})
</script>

<style scoped lang="scss">
.data-export-alert {
    margin-bottom: 10px;
}

.task-progress {
    margin-top: 10px;
}
</style>
