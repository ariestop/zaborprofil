import { apiRequest } from './client'

export async function issueDangerousActionToken(action: string) {
  return apiRequest<{ confirmToken: string, expiresAt: string }>('/admin/api/system/security/confirm-token', {
    method: 'POST',
    body: { action },
  })
}
