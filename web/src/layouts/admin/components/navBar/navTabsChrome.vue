<template>
    <div class="nav-tabs-wrapper">
        <div class="nav-tabs" ref="tabScrollbarRef">
            <div
                v-for="(item, idx) in navTabs.state.tabsView"
                :key="idx"
                class="x-chrome-tab"
                :class="navTabs.state.activeIndex == idx ? 'active' : ''"
                :ref="tabsRefs.set"
                @click="onTab(item)"
                @contextmenu.prevent="onContextmenu(item, $event)"
            >
                <span class="tab-context-catcher"></span>
                <span class="tab-title">{{ item.meta.title }}</span>
                <transition @after-leave="onTabTransitionLeave" name="el-fade-in">
                    <Icon v-show="navTabs.state.tabsView.length > 1" class="close-icon" size="14" name="el-icon-Close" @click.stop="closeTab(item)" />
                </transition>
            </div>
        </div>
        <el-dropdown trigger="hover" placement="bottom-end" @visible-change="tabsMoreChange">
            <span class="tabs-more" :class="{ 'tabs-more-active': state.tabsMoreActive }">
                <span class="tabs-more-icon">
                    <i class="box box-t"></i>
                    <i class="box box-b"></i>
                </span>
            </span>
            <template #dropdown>
                <el-dropdown-menu>
                    <el-dropdown-item @click="onDropCloseOther">{{ t('layouts.Tabs close others') }}</el-dropdown-item>
                    <el-dropdown-item @click="closeLeftTabs">{{ t('layouts.Tabs close left') }}</el-dropdown-item>
                    <el-dropdown-item @click="closeRightTabs">{{ t('layouts.Tabs close right') }}</el-dropdown-item>
                    <el-dropdown-item divided @click="closeAllTab()">{{ t('layouts.Tabs close all') }}</el-dropdown-item>
                </el-dropdown-menu>
            </template>
        </el-dropdown>
        <Contextmenu ref="contextmenuRef" :items="state.contextmenuItems" @menuClick="onContextMenuClick" />
    </div>
</template>

<script setup lang="ts">
import { nextTick, onMounted, reactive, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter, onBeforeRouteUpdate, type RouteLocationNormalized } from 'vue-router'
import { useTemplateRefsList } from '@vueuse/core'
import Contextmenu from '/@/components/contextmenu/index.vue'
import type { ContextMenuItem, ContextMenuItemClickEmitArg } from '/@/components/contextmenu/interface'
import useCurrentInstance from '/@/utils/useCurrentInstance'
import { adminBaseRoutePath } from '/@/router/static/adminBase'
import { useConfig } from '/@/stores/config'
import { useNavTabs } from '/@/stores/navTabs'
import horizontalScroll from '/@/utils/horizontalScroll'
import { getFirstRoute, routePush } from '/@/utils/router'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const config = useConfig()
const navTabs = useNavTabs()
const { proxy } = useCurrentInstance()

const tabsRefs = useTemplateRefsList<HTMLDivElement>()
const tabScrollbarRef = useTemplateRef('tabScrollbarRef')
const contextmenuRef = useTemplateRef('contextmenuRef')

const state: {
    tabsMoreActive: boolean
    contextmenuItems: ContextMenuItem[]
} = reactive({
    tabsMoreActive: false,
    contextmenuItems: [
        { name: 'refresh', label: '重新加载', icon: 'fa fa-refresh' },
        { name: 'close', label: '关闭标签', icon: 'fa fa-times' },
        { name: 'fullScreen', label: '当前标签全屏', icon: 'el-icon-FullScreen' },
        { name: 'closeOther', label: '关闭其他标签', icon: 'fa fa-minus' },
        { name: 'closeAll', label: '关闭全部标签', icon: 'fa fa-stop' },
    ],
})

const tabsMoreChange = (val: boolean) => {
    state.tabsMoreActive = val
}

const onDropCloseOther = () => {
    const r = navTabs.state.activeRoute
    if (r) closeOtherTab(r)
}

const onTabTransitionLeave = () => {
    const el = tabsRefs.value[navTabs.state.activeIndex]
    if (el) selectNavTab(el)
}

const onTab = (menu: RouteLocationNormalized) => {
    router.push(menu.fullPath)
}

const selectNavTab = (dom: HTMLDivElement) => {
    if (!dom) return
    const bar = tabScrollbarRef.value
    if (!bar) return
    const scrollLeft = dom.offsetLeft + dom.clientWidth - bar.clientWidth
    if (dom.offsetLeft < bar.scrollLeft) {
        bar.scrollTo(dom.offsetLeft, 0)
    } else if (scrollLeft > bar.scrollLeft) {
        bar.scrollTo(scrollLeft, 0)
    }
}

const toLastTab = () => {
    const lastTab = navTabs.state.tabsView.slice(-1)[0]
    if (lastTab) {
        router.push(lastTab.fullPath)
    } else {
        router.push(adminBaseRoutePath)
    }
}

const closeTab = (r: RouteLocationNormalized) => {
    navTabs._closeTab(r)
    proxy.eventBus.emit('onTabViewClose', r)
    if (navTabs.state.activeRoute?.fullPath === r.fullPath) {
        toLastTab()
    } else {
        navTabs._setActiveRoute(navTabs.state.activeRoute!)
        nextTick(() => {
            const el = tabsRefs.value[navTabs.state.activeIndex]
            if (el) selectNavTab(el)
        })
    }
    contextmenuRef.value?.onHideContextmenu()
}

const closeOtherTab = (menu: RouteLocationNormalized) => {
    navTabs._closeTabs(menu)
    navTabs._setActiveRoute(menu)
    if (navTabs.state.activeRoute?.fullPath !== route.fullPath) {
        router.push(menu.fullPath)
    }
}

const closeLeftTabs = () => {
    const activeIndex = navTabs.state.activeIndex
    if (activeIndex <= 0) return
    const activeRoute = navTabs.state.activeRoute
    const tabsToClose: RouteLocationNormalized[] = []
    for (let i = 0; i < activeIndex; i++) {
        tabsToClose.push(navTabs.state.tabsView[i])
    }
    for (let i = tabsToClose.length - 1; i >= 0; i--) {
        const tab = tabsToClose[i]
        navTabs._closeTab(tab)
        proxy.eventBus.emit('onTabViewClose', tab)
    }
    nextTick(() => {
        if (activeRoute) navTabs._setActiveRoute(activeRoute)
        const el = tabsRefs.value[navTabs.state.activeIndex]
        if (el) selectNavTab(el)
    })
}

const closeRightTabs = () => {
    const activeIndex = navTabs.state.activeIndex
    const len = navTabs.state.tabsView.length
    if (activeIndex >= len - 1) return
    const activeRoute = navTabs.state.activeRoute
    const tabsToClose: RouteLocationNormalized[] = []
    for (let i = activeIndex + 1; i < len; i++) {
        tabsToClose.push(navTabs.state.tabsView[i])
    }
    for (let i = tabsToClose.length - 1; i >= 0; i--) {
        const tab = tabsToClose[i]
        navTabs._closeTab(tab)
        proxy.eventBus.emit('onTabViewClose', tab)
    }
    nextTick(() => {
        if (activeRoute) navTabs._setActiveRoute(activeRoute)
        const el = tabsRefs.value[navTabs.state.activeIndex]
        if (el) selectNavTab(el)
    })
}

const closeAllTab = (menu?: RouteLocationNormalized) => {
    const firstRoute = getFirstRoute(navTabs.state.tabsViewRoutes)
    if (menu && firstRoute && firstRoute.path == menu.fullPath) {
        return closeOtherTab(menu)
    }
    if (firstRoute && firstRoute.path == navTabs.state.activeRoute?.fullPath) {
        return closeOtherTab(navTabs.state.activeRoute!)
    }
    navTabs._closeTabs(false)
    if (firstRoute) routePush(firstRoute.path)
}

const onContextmenu = (menu: RouteLocationNormalized, el: MouseEvent) => {
    state.contextmenuItems[0].disabled = route.fullPath !== menu.fullPath
    state.contextmenuItems[4].disabled = state.contextmenuItems[3].disabled = navTabs.state.tabsView.length == 1
    contextmenuRef.value?.onShowContextmenu(menu, { x: el.clientX, y: el.clientY })
}

const onContextMenuClick = (item: ContextMenuItemClickEmitArg<RouteLocationNormalized>) => {
    const { name, sourceData } = item
    if (!sourceData) return
    switch (name) {
        case 'refresh':
            proxy.eventBus.emit('onTabViewRefresh', sourceData)
            break
        case 'close':
            closeTab(sourceData)
            break
        case 'closeOther':
            closeOtherTab(sourceData)
            break
        case 'closeAll':
            closeAllTab(sourceData)
            break
        case 'fullScreen':
            if (route.fullPath !== sourceData.fullPath) {
                router.push(sourceData.fullPath as string)
            }
            navTabs.setFullScreen(true)
            break
    }
}

const updateTab = (newRoute: RouteLocationNormalized) => {
    navTabs._addTab(newRoute)
    navTabs._setActiveRoute(newRoute)
    nextTick(() => {
        const el = tabsRefs.value[navTabs.state.activeIndex]
        if (el) selectNavTab(el)
    })
}

onBeforeRouteUpdate(async (to) => {
    updateTab(to)
})

onMounted(() => {
    updateTab(router.currentRoute.value)
    if (tabScrollbarRef.value) {
        new horizontalScroll(tabScrollbarRef.value)
    }
})

const closeTabByPath = (fullPath: string) => {
    for (const key in navTabs.state.tabsView) {
        if (navTabs.state.tabsView[key].fullPath == fullPath) {
            closeTab(navTabs.state.tabsView[key])
            break
        }
    }
}

const updateTabTitle = (fullPath: string, title: string) => {
    navTabs._updateTabTitle(fullPath, title)
    nextTick(() => {
        const el = tabsRefs.value[navTabs.state.activeIndex]
        if (el) selectNavTab(el)
    })
}

defineExpose({
    closeAllTab,
    closeTabByPath,
    updateTabTitle,
})
</script>

<style scoped lang="scss">
/* 与 tgbot navTabs.vue 一致：底部外扩圆角标签（mask 异形） */
.nav-tabs-wrapper {
    display: flex;
    align-items: center;
    background: v-bind('config.getColorVal("headerBarBackground")');
    border-bottom: 1px solid var(--el-border-color-light);
    padding-left: 4px;
    padding-right: 20px;
    box-sizing: border-box;
}

.dark {
    .close-icon {
        color: v-bind('config.getColorVal("headerBarTabColor")') !important;
    }
    .x-chrome-tab.active {
        .close-icon {
            color: v-bind('config.getColorVal("headerBarTabActiveColor")') !important;
        }
    }
}

.nav-tabs {
    display: flex;
    align-items: center;
    overflow-x: auto;
    overflow-y: hidden;
    flex: 1;
    min-width: 0;
    scrollbar-width: none;

    &::-webkit-scrollbar {
        height: 5px;
    }
    &::-webkit-scrollbar-thumb {
        background: #eaeaea;
        border-radius: var(--el-border-radius-base);
        box-shadow: none;
        -webkit-box-shadow: none;
    }
    &::-webkit-scrollbar-track {
        background: transparent;
    }
    &:hover {
        &::-webkit-scrollbar-thumb:hover {
            background: #c8c9cc;
        }
    }
}

.x-chrome-tab {
    display: flex;
    align-items: center;
    white-space: nowrap;
    height: 40px;
    padding: 0 20px;
    cursor: pointer;
    user-select: none;
    color: v-bind('config.getColorVal("headerBarTabColor")');
    background: transparent;
    transition: all 0.2s;
    position: relative;
    margin-top: 6px;
    margin-right: -18px;
    z-index: 1;

    &.active {
        z-index: 2;
    }

    &:hover {
        z-index: 3;
    }

    .tab-context-catcher {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: transparent;
        transition: background 0.2s;
        pointer-events: none;
        -webkit-mask: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAANoAAAAkBAMAAAAdqzmBAAAAMFBMVEVHcEwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlTPQ5AAAAD3RSTlMAr3DvEM8wgCBA379gj5//tJBPAAAAnUlEQVRIx2NgAAM27fj/tAO/xBsYkIHyf9qCT8iWMf6nNQhAsk2f5rYheY7Dnua2/U+A28ZEe8v+F9Ax2v7/F4DbxkUH2wzgtvHTwbYPo7aN2jZq26hto7aN2jZq25Cy7Qvctnw62PYNbls9HWz7S8/G6//PsI6H4396gAUQy1je08W2jxDbpv6nD4gB2uWp+J9eYPsEhv/0BPS1DQBvoBLVZ3BppgAAAABJRU5ErkJggg==);
        mask: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAANoAAAAkBAMAAAAdqzmBAAAAMFBMVEVHcEwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlTPQ5AAAAD3RSTlMAr3DvEM8wgCBA379gj5//tJBPAAAAnUlEQVRIx2NgAAM27fj/tAO/xBsYkIHyf9qCT8iWMf6nNQhAsk2f5rYheY7Dnua2/U+A28ZEe8v+F9Ax2v7/F4DbxkUH2wzgtvHTwbYPo7aN2jZq26hto7aN2jZq25Cy7Qvctnw62PYNbls9HWz7S8/G6//PsI6H4396gAUQy1je08W2jxDbpv6nD4gB2uWp+J9eYPsEhv/0BPS1DQBvoBLVZ3BppgAAAABJRU5ErkJggg==);
        -webkit-mask-size: 100% 100%;
        mask-size: 100% 100%;
    }

    .tab-title {
        flex: 1;
        position: relative;
        z-index: 1;
    }

    .close-icon {
        margin-left: 6px;
        color: v-bind('config.getColorVal("headerBarTabColor")');
        opacity: 0;
        transition: opacity 0.2s;
        position: relative;
        z-index: 1;
        &:hover {
            opacity: 1 !important;
        }
    }

    &.active {
        color: v-bind('config.getColorVal("headerBarTabActiveColor")');
        .tab-context-catcher {
            background: v-bind('config.getColorVal("headerBarTabActiveBackground")');
        }
        .close-icon {
            color: v-bind('config.getColorVal("headerBarTabActiveColor")');
            opacity: 0.8;
        }
    }

    &:hover:not(.active) {
        color: v-bind('config.getColorVal("headerBarTabActiveColor")');
        .tab-context-catcher {
            background: v-bind('config.getColorVal("headerBarHoverBackground")');
        }
        .close-icon {
            opacity: 0.8;
        }
    }

    &:hover.active {
        .close-icon {
            opacity: 0.8;
        }
    }
}

.tabs-more {
    position: relative;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    cursor: pointer;
    margin-left: 8px;
    flex-shrink: 0;
}

.tabs-more-active .tabs-more-icon,
.tabs-more:hover .tabs-more-icon {
    transform: rotate(90deg);
}

.tabs-more-active .tabs-more-icon .box-t:before,
.tabs-more:hover .tabs-more-icon .box-t:before {
    transform: rotate(45deg);
}

.tabs-more-active .tabs-more-icon .box:after,
.tabs-more-active .tabs-more-icon .box:before,
.tabs-more:hover .tabs-more-icon .box:after,
.tabs-more:hover .tabs-more-icon .box:before {
    background: var(--el-color-primary);
}

.tabs-more-icon {
    display: inline-block;
    color: #9a9a9a;
    transition: transform 0.3s ease-out;
}

.tabs-more-icon .box {
    position: relative;
    display: block;
    width: 14px;
    height: 8px;
}

.tabs-more-icon .box:before {
    position: absolute;
    top: 2px;
    left: 0;
    width: 6px;
    height: 6px;
    content: '';
    background: #9a9a9a;
    transition: background 0.3s ease-out;
}

.tabs-more-icon .box:after {
    position: absolute;
    top: 2px;
    left: 8px;
    width: 6px;
    height: 6px;
    content: '';
    background: #9a9a9a;
    transition: background 0.3s ease-out;
}

.tabs-more-icon .box-t:before {
    transition: transform 0.3s ease-out 0.3s;
}
</style>
