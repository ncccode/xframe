<template>
    <div class="default-main">
        <div class="banner">
            <el-row :gutter="10">
                <el-col :md="24" :lg="18">
                    <div class="welcome suspension">
                        <img class="welcome-img" :src="headerSvg" alt="" />
                        <div class="welcome-text">
                            <div class="welcome-title">{{ adminInfo.nickname + t('utils.comma') + getGreet() }}</div>
                            <div class="welcome-note">{{ state.remark }}</div>
                        </div>
                    </div>
                </el-col>
                <el-col :lg="6" class="hidden-md-and-down">
                    <div class="working">
                        <img class="working-coffee" :src="coffeeSvg" alt="" />
                        <div class="working-text">
                            {{ t('dashboard.You have worked today') }}<span class="time">{{ state.workingTimeFormat }}</span>
                        </div>
                        <div @click="onChangeWorkState()" class="working-opt working-rest">
                            {{ state.pauseWork ? t('dashboard.Continue to work') : t('dashboard.have a bit of rest') }}
                        </div>
                    </div>
                </el-col>
            </el-row>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { index } from '/@/api/admin/dashboard'
import coffeeSvg from '/@/assets/dashboard/coffee.svg'
import headerSvg from '/@/assets/dashboard/header-1.svg'
import { useAdminInfo } from '/@/stores/adminInfo'
import { WORKING_TIME } from '/@/stores/constant/cacheKey'
import { getGreet } from '/@/utils/common'
import { Local } from '/@/utils/storage'

defineOptions({
    name: 'dashboard',
})

type WorkingTime = {
    date: string
    startTime: number
    pauseTime: number
    startPauseTime: number
}

let workTimer: number | undefined

const { t } = useI18n()
const adminInfo = useAdminInfo()

const state = reactive({
    remark: 'dashboard.Loading',
    workingTimeFormat: '',
    pauseWork: false,
})

const defaultWorkingTime = (): WorkingTime => ({
    date: '',
    startTime: 0,
    pauseTime: 0,
    startPauseTime: 0,
})

const getWorkingTime = (): WorkingTime => Local.get(WORKING_TIME) || defaultWorkingTime()

index().then((res) => {
    state.remark = res.data.remark
})

const onChangeWorkState = () => {
    const time = parseInt((new Date().getTime() / 1000).toString())
    const workingTime = getWorkingTime()
    if (state.pauseWork) {
        workingTime.pauseTime += time - workingTime.startPauseTime
        workingTime.startPauseTime = 0
        Local.set(WORKING_TIME, workingTime)
        state.pauseWork = false
        startWork()
    } else {
        workingTime.startPauseTime = time
        Local.set(WORKING_TIME, workingTime)
        if (workTimer) window.clearInterval(workTimer)
        state.pauseWork = true
    }
}

const startWork = () => {
    const workingTime = getWorkingTime()
    const date = new Date()
    const currentDate = date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate()
    const time = parseInt((new Date().getTime() / 1000).toString())

    if (workingTime.date != currentDate) {
        workingTime.date = currentDate
        workingTime.startTime = time
        workingTime.pauseTime = workingTime.startPauseTime = 0
        Local.set(WORKING_TIME, workingTime)
    }

    let startPauseTime = 0
    if (workingTime.startPauseTime <= 0) {
        state.pauseWork = false
        startPauseTime = 0
    } else {
        state.pauseWork = true
        startPauseTime = time - workingTime.startPauseTime
    }

    let workingSeconds = time - workingTime.startTime - workingTime.pauseTime - startPauseTime

    state.workingTimeFormat = formatSeconds(workingSeconds)
    if (!state.pauseWork) {
        workTimer = window.setInterval(() => {
            workingSeconds++
            state.workingTimeFormat = formatSeconds(workingSeconds)
        }, 1000)
    }
}

const formatSeconds = (seconds: number) => {
    let secondTime = 0
    let minuteTime = 0
    let hourTime = 0
    let dayTime = 0
    let result = ''

    if (seconds < 60) {
        secondTime = seconds
    } else {
        minuteTime = Math.floor(seconds / 60)
        secondTime = Math.floor(seconds % 60)
        if (minuteTime >= 60) {
            hourTime = Math.floor(minuteTime / 60)
            minuteTime = Math.floor(minuteTime % 60)
            if (hourTime >= 24) {
                dayTime = Math.floor(hourTime / 24)
                hourTime = Math.floor(hourTime % 24)
            }
        }
    }

    result =
        hourTime +
        t('dashboard.hour') +
        ((minuteTime >= 10 ? minuteTime : '0' + minuteTime) + t('dashboard.minute')) +
        ((secondTime >= 10 ? secondTime : '0' + secondTime) + t('dashboard.second'))
    if (dayTime > 0) {
        result = dayTime + t('dashboard.day') + result
    }
    return result
}

onMounted(() => {
    startWork()
})

onUnmounted(() => {
    if (workTimer) window.clearInterval(workTimer)
})
</script>

<style scoped lang="scss">
.welcome {
    background: #e1eaf9;
    border-radius: 6px;
    display: flex;
    align-items: center;
    padding: 15px 20px !important;
    box-shadow: 0 0 30px 0 rgba(82, 63, 105, 0.05);
    .welcome-img {
        height: 100px;
        margin-right: 10px;
        user-select: none;
    }
    .welcome-title {
        font-size: 1.5rem;
        line-height: 30px;
        color: var(--x-color-primary-light);
    }
    .welcome-note {
        padding-top: 6px;
        font-size: 15px;
        color: var(--el-text-color-primary);
    }
}
.working {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    height: 100%;
    position: relative;
    &:hover {
        .working-coffee {
            -webkit-transform: translateY(-4px) scale(1.02);
            -moz-transform: translateY(-4px) scale(1.02);
            -ms-transform: translateY(-4px) scale(1.02);
            -o-transform: translateY(-4px) scale(1.02);
            transform: translateY(-4px) scale(1.02);
            z-index: 999;
        }
    }
    .working-coffee {
        transition: all 0.3s ease;
        width: 80px;
    }
    .working-text {
        display: block;
        width: 100%;
        font-size: 15px;
        text-align: center;
        color: var(--el-text-color-primary);
    }
    .working-opt {
        position: absolute;
        top: -40px;
        right: 10px;
        background-color: rgba($color: #000000, $alpha: 0.3);
        padding: 10px 20px;
        border-radius: 20px;
        color: var(--x-bg-color-overlay);
        transition: all 0.3s ease;
        cursor: pointer;
        opacity: 0;
        z-index: 999;
        &:active {
            background-color: rgba($color: #000000, $alpha: 0.6);
        }
    }
    &:hover {
        .working-opt {
            opacity: 1;
            top: 0;
        }
        .working-done {
            opacity: 1;
            top: 50px;
        }
    }
}

@media screen and (max-width: 425px) {
    .welcome-img {
        display: none;
    }
}

html.dark {
    .welcome {
        background-color: var(--x-bg-color-overlay);
    }
    .working-opt {
        color: var(--el-text-color-primary);
        background-color: var(--x-border-color);
    }
}
</style>
