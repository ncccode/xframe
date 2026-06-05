import assert from 'node:assert/strict'
import { buildExportDownloadPayload, buildExportPayload, resolveDownloadFilename } from '../src/utils/xTableExport'
import { sanitizeHtml } from '../src/utils/sanitizeHtml'

const payload = buildExportPayload({
    title: '管理员',
    columns: [{ type: 'selection' }, { label: 'ID', prop: 'id' }, { label: '操作', render: 'buttons' }],
    filter: { quickSearch: 'admin', limit: 10 },
})

assert.equal(payload.title, '管理员')
assert.equal(payload.quickSearch, 'admin')
assert.equal(payload.column.length, 3)

assert.deepEqual(buildExportDownloadPayload(9), { id: 9, subId: 0, download: true })
assert.deepEqual(buildExportDownloadPayload(9, { zip: true }), { id: 9, downloadZip: 1 })
assert.equal(resolveDownloadFilename('attachment; filename="admin.xlsx"', 'fallback'), 'admin.xlsx')
assert.equal(resolveDownloadFilename("attachment; filename*=UTF-8''%E7%AE%A1%E7%90%86%E5%91%98.xlsx", 'fallback'), '管理员.xlsx')
assert.equal(sanitizeHtml('<img src=x onerror=alert(1)>'), '&lt;img src=x onerror=alert(1)&gt;')
