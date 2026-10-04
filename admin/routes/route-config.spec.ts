import { describe, expect, it } from 'vitest'
import { adminRoutes, sidebarRoutes } from './route-config'

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

describe('page editor routes', () => {
  it('declares /admin/pages/new before /admin/pages/:id so that "new" is not treated as an id', () => {
    const paths = adminRoutes.map((route) => route.path)

    expect(paths.indexOf('/admin/pages/new')).toBeGreaterThan(-1)
    expect(paths.indexOf('/admin/pages/new')).toBeLessThan(paths.indexOf('/admin/pages/:id'))
  })

  it('keeps editor routes out of the sidebar', () => {
    const sidebarPaths = sidebarRoutes.map((route) => route.path)

    expect(sidebarPaths).toContain('/admin/pages')
    expect(sidebarPaths).not.toContain('/admin/pages/new')
    expect(sidebarPaths).not.toContain('/admin/pages/:id')
    expect(sidebarPaths.some((path) => path.includes('/:'))).toBe(false)
  })
})
