import { existsSync } from 'node:fs'
import { cp, mkdir, readdir, rename, rm } from 'node:fs/promises'
import { dirname, join, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const scriptDir = dirname(fileURLToPath(import.meta.url))
const webRoot = resolve(scriptDir, '../..')
const projectRoot = resolve(webRoot, '..')
const distDir = resolve(webRoot, 'dist')
const publicDir = resolve(projectRoot, 'public')

if (!existsSync(distDir)) {
    throw new Error(`构建产物不存在: ${relative(projectRoot, distDir)}`)
}

const distEntries = await readdir(distDir, { withFileTypes: true })

if (distEntries.length === 0) {
    throw new Error(`构建产物为空: ${relative(projectRoot, distDir)}`)
}

await mkdir(publicDir, { recursive: true })

for (const entry of distEntries) {
    const from = join(distDir, entry.name)
    const to = join(publicDir, entry.name)

    await rm(to, { recursive: true, force: true })

    try {
        await rename(from, to)
    } catch (error) {
        if (error?.code !== 'EXDEV') {
            throw error
        }

        await cp(from, to, { recursive: true, force: true })
        await rm(from, { recursive: true, force: true })
    }
}

await rm(distDir, { recursive: true, force: true })

console.log(`已发布 ${distEntries.length} 个构建产物到 ${relative(projectRoot, publicDir)}/`)
