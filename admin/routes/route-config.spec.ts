import { describe, expect, it } from 'vitest'
import { ROLE_PERMISSIONS } from '../shared/testing/roles'
import { adminRoutes, findRoute, isRouteAllowed, navGroups, routesInNavGroup, sidebarRoutes } from './route-config'

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

describe('sidebar groups', () => {
  it('puts every sidebar section into a known group', () => {
    const groupKeys = new Set(navGroups.map((group) => group.key))

    for (const route of sidebarRoutes) {
      expect(route.navGroup, route.path).toBeDefined()
      expect(groupKeys.has(route.navGroup!)).toBe(true)
    }
  })

  it('keeps leads next to the dashboard and folds technical sections into «Сервер»', () => {
    expect(routesInNavGroup('main').map((route) => route.key)).toEqual(['dashboard', 'crm'])
    expect(routesInNavGroup('server').map((route) => route.path)).toEqual(expect.arrayContaining([
      '/admin/system',
      '/admin/system/logs',
      '/admin/system/backups',
      '/admin/settings/migrations',
    ]))
    expect(routesInNavGroup('manage').map((route) => route.path)).toContain('/admin/system/audit')
  })
})

describe('route permissions', () => {
  const navKeys = (permissions: Parameters<typeof routesInNavGroup>[1]) => navGroups
    .flatMap((group) => routesInNavGroup(group.key, permissions))
    .map((route) => route.key)

  it('requires a permission for every section except the dashboard', () => {
    for (const route of adminRoutes) {
      if (route.key === 'dashboard') {
        expect(route.permission, route.key).toBeUndefined()
      } else {
        expect(route.permission, route.key).toBeDefined()
      }
    }
  })

  it('shows an editor pages and media only', () => {
    expect(navKeys(ROLE_PERMISSIONS.ROLE_EDITOR)).toEqual(['dashboard', 'pages', 'media'])
  })

  it('shows an SEO specialist pages and SEO only', () => {
    expect(navKeys(ROLE_PERMISSIONS.ROLE_SEO)).toEqual(['dashboard', 'pages', 'seo'])
  })

  it('shows a manager leads only', () => {
    expect(navKeys(ROLE_PERMISSIONS.ROLE_MANAGER)).toEqual(['dashboard', 'crm'])
  })

  it('keeps the system center and settings away from roles below administrator', () => {
    for (const permissions of Object.values(ROLE_PERMISSIONS)) {
      const keys = navKeys(permissions)
      expect(keys.some((key) => key.startsWith('system'))).toBe(false)
      expect(keys).not.toContain('settings')
      expect(keys).not.toContain('users')
    }
  })

  it('does not filter the menu when permissions are not passed', () => {
    expect(navKeys(undefined)).toEqual(expect.arrayContaining(['users', 'settings', 'systemQueues']))
  })

  it('decides access to nested routes by the same permission', () => {
    const editorPage = findRoute('pageDetail')!
    const lead = findRoute('leadDetail')!

    expect(isRouteAllowed(editorPage, ROLE_PERMISSIONS.ROLE_SEO)).toBe(true)
    expect(isRouteAllowed(lead, ROLE_PERMISSIONS.ROLE_EDITOR)).toBe(false)
    expect(isRouteAllowed(lead, ROLE_PERMISSIONS.ROLE_MANAGER)).toBe(true)
  })
})
