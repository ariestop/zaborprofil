import { create } from 'zustand'

export interface AdminAuthState {
  userEmail: string
  logoutUrl: string
  logoutToken: string
}

interface AdminAuthStore extends AdminAuthState {
  initialize: (payload: AdminAuthState) => void
}

const defaultState: AdminAuthState = {
  userEmail: '',
  logoutUrl: '/admin/logout',
  logoutToken: '',
}

export const useAuthStore = create<AdminAuthStore>((set) => ({
  ...defaultState,
  initialize: (payload) => {
    set({
      userEmail: payload.userEmail,
      logoutUrl: payload.logoutUrl,
      logoutToken: payload.logoutToken,
    })
  },
}))

export function initializeAuthStore(payload: AdminAuthState): void {
  useAuthStore.getState().initialize(payload)
}
