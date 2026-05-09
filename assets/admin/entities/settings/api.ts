import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'
import type { MigrationItem } from '../../types/api'

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
