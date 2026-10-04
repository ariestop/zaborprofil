import { beforeEach } from 'vitest'
import { ADMIN_PERMISSIONS } from '../../entities/user/permissions'
import { initializeAuthStore } from '../../stores/auth'

/** По умолчанию тесты идут от имени суперадмина; тесты ограничений задают права сами. */
beforeEach(() => {
  initializeAuthStore({
    userEmail: 'admin@example.test',
    logoutUrl: '/admin/logout',
    logoutToken: 'token',
    roles: ['ROLE_SUPER_ADMIN'],
    permissions: [...ADMIN_PERMISSIONS],
  })
})
