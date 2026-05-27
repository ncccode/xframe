<template>
    <!-- 查看详情 -->
    <el-dialog class="x-operate-dialog" :model-value="xTable.form.operate ? true : false" @close="xTable.toggleForm">
        <template #header>
            <div class="title" v-drag="['.x-operate-dialog', '.el-dialog__header']" v-zoom="'.x-operate-dialog'">{{ t('Info') }}</div>
        </template>
        <el-scrollbar v-loading="xTable.form.loading" class="x-table-form-scrollbar">
            <div class="x-operate-form" :class="'x-' + xTable.form.operate + '-form'">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('Id')">
                        {{ xTable.form.extend!.info.id }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('system.adminLog.Operation administrator')">
                        {{ xTable.form.extend!.info.username }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('system.adminLog.title')">
                        {{ xTable.form.extend!.info.title }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('system.adminLog.Operator IP')">
                        {{ xTable.form.extend!.info.ip }}
                    </el-descriptions-item>
                    <el-descriptions-item :width="120" :span="2" label="URL">
                        {{ xTable.form.extend!.info.url }}
                    </el-descriptions-item>
                    <el-descriptions-item :width="120" :span="2" label="User Agent">
                        {{ xTable.form.extend!.info.useragent }}
                    </el-descriptions-item>
                    <el-descriptions-item :width="120" :span="2" :label="t('Create time')">
                        {{ timeFormat(xTable.form.extend!.info.create_time) }}
                    </el-descriptions-item>
                    <el-descriptions-item :width="120" :span="2" :label="t('system.adminLog.Request data')">
                        <el-tree class="table-el-tree" :data="xTable.form.extend!.info.data" :props="{ label: 'label', children: 'children' }" />
                    </el-descriptions-item>
                </el-descriptions>
            </div>
        </el-scrollbar>
    </el-dialog>
</template>

<script setup lang="ts">
import { inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type XTable from '/@/utils/xTable'
import { timeFormat } from '/@/utils/common'

const xTable = inject('xTable') as XTable

const { t } = useI18n()
</script>

<style scoped lang="scss"></style>
