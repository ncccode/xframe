import assert from 'node:assert/strict'
import { loadRouteLangMessages, resolveRouteLangLoadPaths } from '../src/router/langLoader'

const delay = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms))

async function run() {
    const to = {
        path: '/admin/system/errorLog',
        fullPath: '/admin/system/errorLog',
        name: 'system/errorLog',
    }

    const paths = resolveRouteLangLoadPaths({
        to,
        defaultLang: 'zh-cn',
        adminBaseRoutePath: '/admin',
        langAutoLoadMap: {},
        isAdminApp: () => true,
    })

    assert.deepEqual(paths, ['./admin/zh-cn/system/errorLog.ts', './admin/zh-cn.ts'])

    const loaded: string[] = []
    const merged: Array<{ message: Record<string, any>; pathName: string }> = []
    const loadLangHandle: Record<string, any> = {
        publicMessageLoaded: loaded,
        './admin/zh-cn/system/errorLog.ts': async () => {
            await delay(10)
            return {
                default: {
                    name: '文件名',
                    'Clear all': '一键清空',
                },
            }
        },
        './admin/zh-cn.ts': async () => {
            await delay(5)
            return {
                default: {
                    Operate: '操作',
                },
            }
        },
    }

    await loadRouteLangMessages({
        to,
        defaultLang: 'zh-cn',
        adminBaseRoutePath: '/admin',
        langAutoLoadMap: {},
        loadLangHandle,
        isAdminApp: () => true,
        mergeMessage: (message, pathName = '') => {
            merged.push({ message, pathName })
        },
    })

    assert.deepEqual(loaded, ['./admin/zh-cn.ts'])
    assert.equal(merged.find((item) => item.pathName === 'system/errorLog')?.message.name, '文件名')
    assert.equal(merged.find((item) => item.pathName === '')?.message.Operate, '操作')
}

run()
