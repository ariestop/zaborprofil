import { describe, expect, it } from 'vitest'
import { adminRoutes } from './route-config'

describe('system center routes', () => {
  it('contains all required /admin/system sections', () => {
    const requiredPaths = [
      '/admin/system',
      '/admin/system/processes',
      '/admin/system/logs',
      '/admin/system/queues',
      '/admin/system/cache',
      '/admin/system/database',
      '/admin/system/security',
      '/admin/system/backups',
      '/admin/system/deploy',
      '/admin/system/audit',
    ]

    const actualPaths = new Set(adminRoutes.map((route) => route.path))
    for (const requiredPath of requiredPaths) {
      expect(actualPaths.has(requiredPath)).toBe(true)
    }
  })
})
