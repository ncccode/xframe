<!-- 模块市场等地方的 XFrame 官方账户登录弹窗 -->
<template>
    <div>
        <el-dialog v-model="model" class="x-account-dialog" width="25%" :title="t('layouts.Member information')">
            <template v-if="xAccount.token">
                <div v-loading="state.loading" class="userinfo">
                    <div class="user-avatar-box">
                        <img class="user-avatar" :src="xAccount.avatar" alt="" />
                        <Icon
                            class="user-avatar-gender"
                            :name="xAccount.getGenderIcon()['name']"
                            size="14"
                            :color="xAccount.getGenderIcon()['color']"
                        />
                    </div>
                    <p class="username">{{ xAccount.nickname }}</p>
                    <p class="user-info">
                        <span>{{ xAccount.email || xAccount.mobile || xAccount.username }}</span>
                    </p>
                    <div class="userinfo-buttons">
                        <el-button @click="xAccount.logout()" v-blur size="default" type="warning">{{ $t('layouts.Logout') }}</el-button>
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="x-login">
                    <h3 class="x-title">{{ t('layouts.Login to the xframe') }}</h3>
                    <el-form
                        @keyup.enter="onXAccountSubmitPre()"
                        ref="xAccountFormRef"
                        :rules="xAccountFormRules"
                        class="x-account-login-form"
                        :model="state.user"
                    >
                        <FormItem
                            v-model="state.user.username"
                            type="string"
                            prop="username"
                            :placeholder="t('layouts.Please enter xframe account name or email')"
                            :input-attr="{
                                size: 'large',
                            }"
                        />
                        <FormItem
                            v-model="state.user.password"
                            type="password"
                            prop="password"
                            :placeholder="t('layouts.Please enter the xframe account password')"
                            :input-attr="{
                                size: 'large',
                            }"
                        />
                        <el-form-item class="form-buttons">
                            <el-button @click="onXAccountSubmitPre()" :loading="state.submitLoading" round type="primary" size="large">
                                {{ t('layouts.Login') }}
                            </el-button>
                        </el-form-item>
                    </el-form>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import type { FormItemRule } from 'element-plus'
import { reactive, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { xAccountCheckIn, xAccountGetUserInfo } from '/@/api/admin/index'
import FormItem from '/@/components/formItem/index.vue'
import { useXAccount } from '/@/stores/xAccount'
import { buildValidatorData } from '/@/utils/validate'

const { t } = useI18n()
const xAccount = useXAccount()
const model = defineModel<boolean>()
const xAccountFormRef = useTemplateRef('xAccountFormRef')

interface Props {
    loginCallback?: (res: ApiResponse) => void
}

const props = withDefaults(defineProps<Props>(), {
    loginCallback: () => {},
})

const state = reactive({
    loading: true,
    submitLoading: false,
    user: {
        tab: 'login',
        username: '',
        password: '',
        keep: false,
    },
})

const onXAccountSubmitPre = () => {
    xAccountFormRef.value?.validate((valid) => {
        if (valid) {
            onXAccountSubmit()
        }
    })
}

const onXAccountSubmit = () => {
    state.submitLoading = true
    xAccountCheckIn(state.user)
        .then((res) => {
            xAccount.dataFill(res.data.userInfo, false)
            state.loading = false
            props.loginCallback(res)
        })
        .finally(() => {
            state.submitLoading = false
        })
}

const xAccountFormRules: Partial<Record<string, FormItemRule[]>> = reactive({
    username: [buildValidatorData({ name: 'required', title: t('layouts.Username') })],
    password: [buildValidatorData({ name: 'required', title: t('layouts.Password') }), buildValidatorData({ name: 'password' })],
})

watch(
    () => model.value,
    (newVal) => {
        if (newVal && xAccount.token) {
            state.loading = true
            xAccountGetUserInfo()
                .then((res) => {
                    xAccount.dataFill(res.data.userInfo)
                })
                .catch(() => {
                    xAccount.removeToken()
                })
                .finally(() => {
                    state.loading = false
                })
        } else {
            state.loading = false
        }
    }
)
</script>

<style scoped lang="scss">
.userinfo {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    padding: 20px 0;
    .username {
        display: block;
        text-align: center;
        width: 100%;
        padding-top: 10px;
        font-size: var(--el-font-size-large);
        font-weight: bold;
    }
    .user-info {
        display: block;
        text-align: center;
        width: 100%;
        padding: 10px 0;
        font-size: var(--el-font-size-base);
        span {
            padding: 0 4px;
        }
    }
    .user-avatar-box {
        position: relative;
        cursor: pointer;
    }
    .user-avatar {
        display: block;
        width: 100px;
        border-radius: 50%;
        border: 1px solid var(--el-border-color-extra-light);
    }
    .user-avatar-gender {
        position: absolute;
        bottom: 0px;
        right: 10px;
        height: 22px;
        width: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #fff;
        border-radius: 50%;
        box-shadow: var(--el-box-shadow);
    }
    .userinfo-buttons {
        margin-top: 10px;
    }
}

.x-login {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    padding: 20px;
    .x-title {
        width: 100%;
        text-align: center;
    }
    .form-buttons {
        .el-button {
            width: 100%;
            letter-spacing: 2px;
            font-weight: 300;
            margin-top: 20px;
            margin-left: 0;
        }
    }
    .x-account-register {
        width: 100%;
        text-decoration: none;
    }
    .x-account-login-form {
        width: 350px;
        padding-top: 20px;
    }
}

/* 会员信息弹窗-s */
@media screen and (max-width: 1440px) {
    :deep(.x-account-dialog) {
        --el-dialog-width: 40% !important;
    }
}
@media screen and (max-width: 1024px) {
    :deep(.x-account-dialog) {
        --el-dialog-width: 70% !important;
    }
}
/* 会员信息弹窗-e */
</style>
