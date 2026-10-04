import { beforeEach } from 'vitest'
import { ADMIN_PERMISSIONS } from '../../entities/user/permissions'
import { initializeAuthStore } from '../../stores/auth'

/** Своё хранилище в памяти: в Node 25 глобальный localStorage без --localstorage-file не поддерживает clear() и removeItem(). */
function installMemoryStorage(): void {
  const data = new Map<string, string>()
  const storage = {
    getItem: (key: string) => data.get(key) ?? null,
    setItem: (key: string, value: string) => void data.set(key, String(value)),
    removeItem: (key: string) => void data.delete(key),
    clear: () => data.clear(),
    key: (index: number) => Array.from(data.keys())[index] ?? null,
    get length() {
      return data.size
    },
  }
  Object.defineProperty(window, 'localStorage', { value: storage, configurable: true })
}

/** По умолчанию тесты идут от имени суперадмина; тесты ограничений задают права сами. */
beforeEach(() => {
  installMemoryStorage()
  initializeAuthStore({
    userEmail: 'admin@example.test',
    logoutUrl: '/admin/logout',
    logoutToken: 'token',
    roles: ['ROLE_SUPER_ADMIN'],
    permissions: [...ADMIN_PERMISSIONS],
  })
})
