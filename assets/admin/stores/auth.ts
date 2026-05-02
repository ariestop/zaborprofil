import { reactive } from 'vue'

export interface AdminAuthState {
  userEmail: string
  logoutUrl: string
  logoutToken: string
}

const state = reactive<AdminAuthState>({
  userEmail: '',
  logoutUrl: '/admin/logout',
  logoutToken: '',
})

export function useAuthStore(): AdminAuthState {
  return state
}

export function initializeAuthStore(payload: AdminAuthState): void {
  state.userEmail = payload.userEmail
  state.logoutUrl = payload.logoutUrl
  state.logoutToken = payload.logoutToken
}
