import { beforeEach, describe, expect, it, vi } from 'vitest'
import { requestMigrationAction } from './api'

const apiRequest = vi.fn()

vi.mock('../../shared/api/client', () => ({
  apiRequest: (...args: unknown[]) => apiRequest(...args),
}))

describe('requestMigrationAction', () => {
  beforeEach(() => {
    apiRequest.mockReset()
  })

  it('issues a confirm token for the exact action and version before applying', async () => {
    apiRequest
      .mockResolvedValueOnce({ confirmToken: 'token-1', expiresAt: '2030-01-01T00:00:00+00:00' })
      .mockResolvedValueOnce({ status: 'applied', executedCount: 1 })

    const result = await requestMigrationAction('apply', 'DoctrineMigrations\\Version1')

    expect(result).toEqual({ status: 'applied', executedCount: 1 })
    expect(apiRequest).toHaveBeenNthCalledWith(1, '/admin/api/system/security/confirm-token', {
      method: 'POST',
      body: { action: 'migration.apply:DoctrineMigrations\\Version1' },
    })
    expect(apiRequest).toHaveBeenNthCalledWith(2, '/admin/api/settings/migrations/DoctrineMigrations%5CVersion1/apply', {
      method: 'POST',
      body: { confirmToken: 'token-1' },
    })
  })

  it('uses the rollback action for token and endpoint', async () => {
    apiRequest
      .mockResolvedValueOnce({ confirmToken: 'token-2', expiresAt: '2030-01-01T00:00:00+00:00' })
      .mockResolvedValueOnce({ status: 'rolled_back', executedCount: 1 })

    await requestMigrationAction('rollback', 'DoctrineMigrations\\Version2')

    expect(apiRequest).toHaveBeenNthCalledWith(1, '/admin/api/system/security/confirm-token', {
      method: 'POST',
      body: { action: 'migration.rollback:DoctrineMigrations\\Version2' },
    })
    expect(apiRequest).toHaveBeenNthCalledWith(2, '/admin/api/settings/migrations/DoctrineMigrations%5CVersion2/rollback', {
      method: 'POST',
      body: { confirmToken: 'token-2' },
    })
  })

  it('does not call the migration endpoint when the token cannot be issued', async () => {
    apiRequest.mockRejectedValueOnce(new Error('Access denied.'))

    await expect(requestMigrationAction('apply', 'DoctrineMigrations\\Version1')).rejects.toThrow('Access denied.')
    expect(apiRequest).toHaveBeenCalledTimes(1)
  })
})
