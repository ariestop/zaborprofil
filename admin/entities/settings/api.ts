import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'
import type { MigrationItem, SettingItem } from '../../types/api'

function settingsQueryKey(scope?: string) {
  return ['admin', 'settings', scope ?? 'all'] as const
}

interface UpsertSettingInput {
  scope: string
  key: string
  value: unknown
  description?: string | null
}

export function useSettingsQuery(scope?: string) {
  return useQuery({
    queryKey: settingsQueryKey(scope),
    queryFn: async () => {
      const query = scope === undefined ? '' : `?scope=${encodeURIComponent(scope)}`
      return apiRequest<SettingItem[]>(`/admin/api/settings${query}`)
    },
  })
}

export function useUpsertSettingMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ scope, key, value, description }: UpsertSettingInput) => apiRequest<SettingItem>(`/admin/api/settings/${encodeURIComponent(scope)}/${encodeURIComponent(key)}`, {
      method: 'PUT',
      body: {
        value,
        description: description ?? null,
      },
    }),
    onSuccess: async (_setting, variables) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['admin', 'settings'] }),
        queryClient.invalidateQueries({ queryKey: settingsQueryKey(variables.scope) }),
      ])
    },
  })
}

function settingsMigrationsQueryKey() {
  return ['admin', 'settings', 'migrations'] as const
}

export function useSettingsMigrationsQuery() {
  return useQuery({
    queryKey: settingsMigrationsQueryKey(),
    queryFn: () => apiRequest<MigrationItem[]>('/admin/api/settings/migrations'),
  })
}

interface MigrationActionResponse {
  status: 'applied' | 'rolled_back'
  executedCount: number
}

export function useApplyMigrationMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (version: string) => apiRequest<MigrationActionResponse>(`/admin/api/settings/migrations/${encodeURIComponent(version)}/apply`, {
      method: 'POST',
    }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: settingsMigrationsQueryKey() })
    },
  })
}

export function useRollbackMigrationMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (version: string) => apiRequest<MigrationActionResponse>(`/admin/api/settings/migrations/${encodeURIComponent(version)}/rollback`, {
      method: 'POST',
    }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: settingsMigrationsQueryKey() })
    },
  })
}
