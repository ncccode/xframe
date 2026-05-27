/**
 * references
 * 全局提供：引用（指向）一些对象（组件）的句柄
 */
import type { ScrollbarInstance } from 'element-plus'
import type { CSSProperties } from 'vue'
import { computed, ref } from 'vue'
import NavTabs from '/@/layouts/admin/components/navBar/tabs.vue'
import NavTabsChrome from '/@/layouts/admin/components/navBar/navTabsChrome.vue'
import { mainHeight } from '/@/utils/layout'

/**
 * 后台顶栏 tabs 组件 ref
 */
export const layoutNavTabsRef = ref<InstanceType<typeof NavTabs> | InstanceType<typeof NavTabsChrome>>()

/**
 * 前后台布局的主体的滚动条组件ref
 */
export const layoutMainScrollbarRef = ref<ScrollbarInstance>()

/**
 * 前后台布局的主体滚动条的额外样式，包括高度
 */
export const layoutMainScrollbarStyle = computed<CSSProperties>(() => mainHeight())

/**
 * 前后台布局的菜单组件ref
 */
export const layoutMenuRef = ref<ScrollbarInstance>()

/**
 * 前后台布局的菜单栏滚动条组件ref
 */
export const layoutMenuScrollbarRef = ref<ScrollbarInstance>()
