<template>
    <div>
        <el-table :data="state.common.versions" class="w100" stripe>
            <el-table-column property="version" align="center" :label="t('module.Version')" />
            <el-table-column property="short_describe" :show-overflow-tooltip="true" align="center" :label="t('module.Description')" />
            <el-table-column property="available_system_version_text" align="center" :label="t('module.Available system version')">
                <template #default="scope">
                    <div v-if="scope.row.available_system_version && state.sysVersion">
                        <div class="available-system-version">
                            <Icon
                                v-if="compareVersion(scope.row.available_system_version)"
                                name="el-icon-CircleCheckFilled"
                                color="var(--el-color-success)"
                                size="14"
                            />
                            <Icon v-else name="el-icon-CircleCloseFilled" size="14" color="var(--el-color-danger)" />
                            <div class="available-system-version-text">{{ scope.row.available_system_version_text }}</div>
                        </div>
                    </div>
                    <div v-else>-</div>
                </template>
            </el-table-column>
            <el-table-column property="createtime_text" align="center" :label="t('Create time')" />
            <el-table-column :label="t('module.Install')" align="center" :min-width="140">
                <template #default="scope">
                    <div v-if="isLocalModuleVersion(scope.row.version)" class="renewal-text">{{ t('module.Current installed version') }}</div>
                    <div v-else-if="!compareVersion(scope.row.available_system_version)">{{ t('module.Insufficient system version') }}</div>
                    <div v-else>
                        <el-button type="primary" @click="onInstall(scope.row.uid, scope.row.version_id || scope.row.id, scope.row.version)">
                            {{ t('module.Click to install') }}
                        </el-button>
                    </div>
                </template>
            </el-table-column>
        </el-table>
    </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { execInstall, showCommonLoading } from '../index'
import { state } from '../store'

const { t } = useI18n()

const normalizeVersion = (version: string) => {
    return (version.match(/\d+/g) || []).map((item) => {
        return Number.parseInt(item, 10)
    })
}

const compareVersionSegments = (currentVersion: number[], requiredVersion: number[]) => {
    const maxLength = Math.max(currentVersion.length, requiredVersion.length)

    for (let i = 0; i < maxLength; i++) {
        const current = currentVersion[i] || 0
        const required = requiredVersion[i] || 0
        if (current > required) return 1
        if (current < required) return -1
    }

    return 0
}

const isLocalModuleVersion = (version: string) => {
    const localModule = state.installedModule.find((item) => {
        return item.uid == state.common.uid
    })

    if (localModule) {
        if (version.replace(/^v/i, '') == localModule.version.replace(/^v/i, '')) {
            return true
        }
    }

    return false
}

const compareVersion = (version: string): boolean => {
    if (!version) return true

    const operator = version.trim().match(/^(>=|<=|>|<|=)\s*/)?.[1] || '>='
    const result = compareVersionSegments(normalizeVersion(state.sysVersion), normalizeVersion(version))

    if (operator == '>') return result > 0
    if (operator == '<') return result < 0
    if (operator == '<=') return result <= 0
    if (operator == '=') return result == 0
    return result >= 0
}

const onInstall = (uid: string, id: number, version: string) => {
    state.dialog.common = true
    state.common.dialogTitle = t('module.Install')
    showCommonLoading('download')

    state.dialog.goodsInfo = false

    execInstall(uid, id, version, state.common.update)
}
</script>

<style scoped lang="scss">
.renewal {
    display: flex;
    align-items: center;
    justify-content: center;
    .renewal-text {
        font-size: 12px;
        margin-right: 6px;
    }
}
.available-system-version {
    display: flex;
    align-items: center;
    justify-content: center;
    .available-system-version-text {
        margin-left: 4px;
    }
}
</style>
