import assert from 'node:assert/strict'
import { normalizeAdminUrl } from '../src/utils/adminUrl'

assert.equal(normalizeAdminUrl('/admin/system/Admin/index'), '/admin/system/Admin/index')
assert.equal(normalizeAdminUrl('/admin/system.Admin/index'), '/admin/system.Admin/index')
assert.equal(normalizeAdminUrl('/admin/system/Admin/index', '/backend'), '/backend/system/Admin/index')
assert.equal(normalizeAdminUrl('/api/common/refreshToken', '/backend'), '/api/common/refreshToken')
