import { create } from 'zustand'
import { isAdminPermission, type AdminPermission } from '../entities/user/permissions'

export interface AdminAuthState {
  userEmail: string
  logoutUrl: string
  logoutToken: string
  roles: string[]
  permissions: AdminPermission[]
}

interface AdminAuthStore extends AdminAuthState {
  initialize: (payload: Partial<AdminAuthState> & Pick<AdminAuthState, 'userEmail' | 'logoutUrl' | 'logoutToken'>) => void
}

const defaultState: AdminAuthState = {
  userEmail: '',
  logoutUrl: '/admin/logout',
  logoutToken: '',
  roles: [],
  permissions: [],
}

export const useAuthStore = create<AdminAuthStore>((set) => ({
  ...defaultState,
  initialize: (payload) => {
    set({
      userEmail: payload.userEmail,
      logoutUrl: payload.logoutUrl,
      logoutToken: payload.logoutToken,
      roles: payload.roles ?? [],
      permissions: payload.permissions ?? [],
    })
  },
}))

export function initializeAuthStore(payload: Parameters<AdminAuthStore['initialize']>[0]): void {
  useAuthStore.getState().initialize(payload)
}

/** Разбор списка из data-атрибута `a,b,c`; неизвестные права отбрасываются. */
export function parsePermissions(raw: string | undefined): AdminPermission[] {
  return (raw ?? '').split(',').map((item) => item.trim()).filter(isAdminPermission)
}

export function parseRoles(raw: string | undefined): string[] {
  return (raw ?? '').split(',').map((item) => item.trim()).filter((item) => item !== '')
}

/** Есть ли у текущего пользователя право. Без `permission` доступ открыт всем вошедшим. */
export function useCan(permission: AdminPermission | undefined): boolean {
  return useAuthStore((state) => permission === undefined || state.permissions.includes(permission))
}
