<template>
    <div>
        <el-alert class="x-table-alert" v-if="state.table.remark" :title="state.table.remark" type="info" show-icon />
        <div class="modules-header">
            <div class="table-header-buttons">
                <el-button :title="$t('Refresh')" @click="onRefreshTableData" v-blur color="#40485b" type="info">
                    <Icon name="fa fa-refresh" color="#ffffff" size="14" />
                </el-button>
                <el-button-group class="ml10">
                    <el-button @click="uploadInstall" :title="t('module.Upload zip package for installation')" v-blur type="primary">
                        <Icon name="fa fa-upload" color="#ffffff" size="14" />
                        <span class="table-header-operate-text">{{ t('module.Upload installation') }}</span>
                    </el-button>
                    <el-button
                        @click="localModules"
                        :class="state.table.onlyLocal ? 'local-active' : ''"
                        :title="t('module.Uploaded / installed modules')"
                        v-blur
                        type="primary"
                    >
                        <Icon name="fa fa-desktop" color="#ffffff" size="14" />
                        <span class="table-header-operate-text">{{ t('module.Local module') }}</span>
                    </el-button>
                </el-button-group>
                <el-button v-if="!xAccount.token" @click="showXAccount = true" v-blur class="ml10" type="success">
                    <Icon name="fa fa-chain" color="#ffffff" size="14" />
                    <span class="table-header-operate-text">{{ t('module.Login official account') }}</span>
                </el-button>
                <el-button v-else @click="showXAccount = true" v-blur class="ml10" type="success">
                    <Icon name="fa fa-user-o" color="#ffffff" size="14" />
                    <span class="table-header-operate-text">{{ t('layouts.Member information') }}</span>
                </el-button>
            </div>
            <div class="table-search">
                <el-input
                    v-model="state.table.params.quickSearch"
                    class="xs-hidden"
                    @input="onSearchInput"
                    :placeholder="t('module.Search is actually very simple')"
                />
            </div>
        </div>
        <XAccountDialog v-model="showXAccount" />
    </div>
</template>

<script setup lang="ts">
import { debounce } from 'lodash-es'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import XAccountDialog from '/@/layouts/admin/components/xAccount.vue'
import { useXAccount } from '/@/stores/xAccount'
import { loadData, onRefreshTableData } from '../index'
import { state } from '../store'

const { t } = useI18n()
const xAccount = useXAccount()
const showXAccount = ref(false)
const localModules = () => {
    state.table.onlyLocal = !state.table.onlyLocal
    loadData()
}

const onSearchInput = debounce(() => {
    state.table.modulesEbak[state.table.params.activeTab] = undefined
    loadData()
}, 500)

const uploadInstall = () => {
    state.dialog.common = true
    state.common.quickClose = true
    state.common.dialogTitle = t('module.Upload installation')
    state.common.type = 'uploadInstall'
}
</script>

<style scoped lang="scss">
.ml10 {
    margin-left: 10px;
}
.x-table-alert {
    border: none;
}
.modules-header {
    display: flex;
    align-items: center;
    padding: 10px;
    margin-bottom: 10px;
    background-color: var(--x-bg-color-overlay);
    border-radius: var(--el-border-radius-base);
    border-top-left-radius: 0;
    border-top-right-radius: 0;
}
.table-header-operate-text {
    padding-left: 6px;
}
.table-search {
    margin-left: auto;
}
.local-active {
    border-color: var(--el-button-active-border-color);
    background-color: var(--el-button-active-bg-color);
}
</style>
