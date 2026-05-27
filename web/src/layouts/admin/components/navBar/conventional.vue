<template>
    <div class="layout-stacked-nav">
        <div class="nav-bar">
            <div v-if="config.layout.shrink && config.layout.menuCollapse" class="unfold">
                <Icon @click="onMenuCollapse" name="fa fa-indent" :color="config.getColorVal('menuActiveColor')" size="18" />
            </div>
            <div v-else class="breadcrumb-container">
                <div class="fold-btn" @click="onMenuFold">
                    <Icon
                        :name="config.layout.menuCollapse ? 'fa fa-indent' : 'fa fa-dedent'"
                        :color="config.getColorVal('menuActiveColor')"
                        size="18"
                    />
                </div>
                <div class="breadcrumb" v-if="breadcrumbItems.length > 0">
                    <span v-for="(item, index) in breadcrumbItems" :key="index" class="breadcrumb-item">
                        <span class="breadcrumb-title">{{ item.title }}</span>
                        <span v-if="index < breadcrumbItems.length - 1" class="breadcrumb-separator">></span>
                    </span>
                </div>
            </div>
            <NavMenus />
        </div>
        <NavTabsChrome ref="layoutNavTabsRef" />
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useConfig } from '/@/stores/config'
import { useNavTabs } from '/@/stores/navTabs'
import NavTabsChrome from '/@/layouts/admin/components/navBar/navTabsChrome.vue'
import { layoutNavTabsRef } from '/@/stores/refs'
import NavMenus from '../navMenus.vue'
import { showShade } from '/@/utils/pageShade'
import type { RouteRecordRaw } from 'vue-router'

const { t } = useI18n()
const config = useConfig()
const navTabs = useNavTabs()
const route = useRoute()

const onMenuCollapse = () => {
    showShade('x-aside-menu-shade', () => {
        config.setLayout('menuCollapse', true)
    })
    config.setLayout('menuCollapse', false)
}

const onMenuFold = () => {
    config.setLayout('menuCollapse', !config.layout.menuCollapse)
}

const findMenuPath = (targetPath: string, menus: RouteRecordRaw[], path: RouteRecordRaw[] = []): RouteRecordRaw[] | null => {
    for (const menu of menus) {
        const currentPath = [...path, menu]
        const menuPath = menu.path.split('?')[0].split('#')[0]
        const routePath = targetPath.split('?')[0].split('#')[0]

        if (menuPath === routePath || menu.path === targetPath) {
            return currentPath
        }
        if (menu.children && menu.children.length > 0) {
            const found = findMenuPath(targetPath, menu.children as RouteRecordRaw[], currentPath)
            if (found) {
                return found
            }
        }
    }
    return null
}

const breadcrumbItems = computed(() => {
    const currentPath = route.fullPath || route.path
    const menuPath = findMenuPath(currentPath, navTabs.state.tabsViewRoutes)

    if (!menuPath || menuPath.length === 0) {
        return []
    }

    return menuPath.map((menu) => {
        let title = (menu.meta?.title as string) || ''
        if (typeof title === 'string' && title) {
            title = t(title, title)
        }
        return { title, path: menu.path }
    })
})
</script>

<style scoped lang="scss">
.layout-stacked-nav {
    width: 100%;
    background-color: v-bind('config.getColorVal("headerBarBackground")');
}
.nav-bar {
    display: flex;
    height: 50px;
    width: 100%;
    background-color: v-bind('config.getColorVal("headerBarBackground")');
    border-bottom: 1px solid var(--el-border-color-light);
    box-sizing: border-box;
}
.unfold {
    align-self: center;
    padding-left: var(--x-main-space);
}
.breadcrumb-container {
    display: flex;
    align-items: center;
    flex: 1;
    padding-left: var(--x-main-space);
    .fold-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        height: 100%;
    }
    .breadcrumb {
        display: flex;
        align-items: center;
        margin-left: 12px;
        .breadcrumb-item {
            display: flex;
            align-items: center;
            .breadcrumb-title {
                color: v-bind('config.getColorVal("headerBarTabColor")');
                font-size: 14px;
            }
            .breadcrumb-separator {
                margin: 0 8px;
                color: #c0c4cc;
                font-size: 14px;
            }
        }
    }
}
</style>
