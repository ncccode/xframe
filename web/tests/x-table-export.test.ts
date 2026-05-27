import assert from 'node:assert/strict'
import { buildExportDownloadUrl, buildExportPayload } from '../src/utils/xTableExport'

const payload = buildExportPayload({
    title: '管理员',
    columns: [{ type: 'selection' }, { label: 'ID', prop: 'id' }, { label: '操作', render: 'buttons' }],
    filter: { quickSearch: 'admin', limit: 10 },
})

assert.equal(payload.title, '管理员')
assert.equal(payload.quickSearch, 'admin')
assert.equal(payload.column.length, 3)

assert.equal(
    buildExportDownloadUrl({
        apiBaseUrl: 'http://localhost:8000',
        routePath: '/admin/system/Admin',
        taskId: 9,
        token: 'abc',
    }),
    'http://localhost:8000/admin/system/Admin/export?subId=0&download=true&id=9&xtoken=abc&server=1'
)
