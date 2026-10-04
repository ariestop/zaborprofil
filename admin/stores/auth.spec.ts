import { describe, expect, it } from 'vitest'
import { initializeAuthStore, parsePermissions, parseRoles, useAuthStore } from './auth'

describe('auth store parsing', () => {
  it('parses the permission list from the data attribute and drops unknown values', () => {
    expect(parsePermissions('pages.view, leads.view,unknown.permission,')).toEqual(['pages.view', 'leads.view'])
    expect(parsePermissions('')).toEqual([])
    expect(parsePermissions(undefined)).toEqual([])
  })

  it('parses roles', () => {
    expect(parseRoles('ROLE_EDITOR,ROLE_SEO')).toEqual(['ROLE_EDITOR', 'ROLE_SEO'])
    expect(parseRoles(undefined)).toEqual([])
  })

  it('starts without permissions when the server did not send any', () => {
    initializeAuthStore({ userEmail: 'a@example.test', logoutUrl: '/admin/logout', logoutToken: 't' })

    expect(useAuthStore.getState().permissions).toEqual([])
    expect(useAuthStore.getState().roles).toEqual([])
  })
})
