import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'
import { adminQueryKeys } from '../../shared/api/query'
import type {
  AuditLogEntryItem,
  SystemBackupsResponse,
  SystemCacheResponse,
  SystemCommandResult,
  SystemDatabaseResponse,
  SystemDeployResponse,
  SystemLogsResponse,
  SystemOverviewResponse,
  SystemProcessStatusResponse,
  SystemQueuesResponse,
  SystemSecurityResponse,
} from '../../types/api'

function invalidateSystemQueries(queryClient: ReturnType<typeof useQueryClient>) {
  return Promise.all([
    queryClient.invalidateQueries({ queryKey: adminQueryKeys.systemOverview }),
    queryClient.invalidateQueries({ queryKey: adminQueryKeys.systemProcesses }),
    queryClient.invalidateQueries({ queryKey: adminQueryKeys.systemQueues }),
    queryClient.invalidateQueries({ queryKey: adminQueryKeys.systemCache }),
    queryClient.invalidateQueries({ queryKey: adminQueryKeys.systemAudit }),
  ])
}

async function issueDangerousActionToken(action: string) {
  return apiRequest<{ confirmToken: string, expiresAt: string }>('/admin/api/system/security/confirm-token', {
    method: 'POST',
    body: { action },
  })
}

export function useSystemOverviewQuery() {
  return useQuery({
    queryKey: adminQueryKeys.systemOverview,
    queryFn: () => apiRequest<SystemOverviewResponse>('/admin/api/system/overview'),
  })
}

export function useSystemProcessesQuery() {
  return useQuery({
    queryKey: adminQueryKeys.systemProcesses,
    queryFn: () => apiRequest<SystemProcessStatusResponse>('/admin/api/system/processes'),
  })
}

export function useSystemLogsQuery(channel = '') {
  const normalizedChannel = channel.trim()
  const suffix = normalizedChannel === '' ? '' : `?channel=${encodeURIComponent(normalizedChannel)}`

  return useQuery({
    queryKey: adminQueryKeys.systemLogs(normalizedChannel),
    queryFn: () => apiRequest<SystemLogsResponse>(`/admin/api/system/logs${suffix}`),
  })
}

export function useSystemQueuesQuery() {
  return useQuery({
    queryKey: adminQueryKeys.systemQueues,
    queryFn: () => apiRequest<SystemQueuesResponse>('/admin/api/system/queues'),
  })
}

export function useSystemCacheQuery() {
  return useQuery({
    queryKey: adminQueryKeys.systemCache,
    queryFn: () => apiRequest<SystemCacheResponse>('/admin/api/system/cache'),
  })
}

export function useSystemDatabaseQuery() {
  return useQuery({
    queryKey: adminQueryKeys.systemDatabase,
    queryFn: () => apiRequest<SystemDatabaseResponse>('/admin/api/system/database'),
  })
}

export function useSystemSecurityQuery() {
  return useQuery({
    queryKey: adminQueryKeys.systemSecurity,
    queryFn: () => apiRequest<SystemSecurityResponse>('/admin/api/system/security'),
  })
}

export function useSystemBackupsQuery() {
  return useQuery({
    queryKey: adminQueryKeys.systemBackups,
    queryFn: () => apiRequest<SystemBackupsResponse>('/admin/api/system/backups'),
  })
}

export function useSystemDeployQuery() {
  return useQuery({
    queryKey: adminQueryKeys.systemDeploy,
    queryFn: () => apiRequest<SystemDeployResponse>('/admin/api/system/deploy'),
  })
}

export function useSystemAuditQuery(limit = 50) {
  return useQuery({
    queryKey: [...adminQueryKeys.systemAudit, limit],
    queryFn: () => apiRequest<AuditLogEntryItem[]>(`/admin/api/system/audit?limit=${limit}`),
  })
}

export function useProcessRestartMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ service }: { service: string }) => {
      const { confirmToken } = await issueDangerousActionToken(`process.restart:${service}`)

      return apiRequest<SystemCommandResult>('/admin/api/system/processes/restart', {
        method: 'POST',
        body: { service, confirmToken },
      })
    },
    onSuccess: async () => {
      await invalidateSystemQueries(queryClient)
    },
  })
}

export function useProcessReloadMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ service }: { service: string }) => {
      const { confirmToken } = await issueDangerousActionToken(`process.reload:${service}`)

      return apiRequest<SystemCommandResult>('/admin/api/system/processes/reload', {
        method: 'POST',
        body: { service, confirmToken },
      })
    },
    onSuccess: async () => {
      await invalidateSystemQueries(queryClient)
    },
  })
}

export function useQueueRetryFailedMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async () => {
      const { confirmToken } = await issueDangerousActionToken('queue.retry.failed')

      return apiRequest<SystemCommandResult>('/admin/api/system/queues/retry-failed', {
        method: 'POST',
        body: { confirmToken },
      })
    },
    onSuccess: async () => {
      await invalidateSystemQueries(queryClient)
    },
  })
}

export function useQueueRemoveFailedMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async () => {
      const { confirmToken } = await issueDangerousActionToken('queue.remove.failed')

      return apiRequest<SystemCommandResult>('/admin/api/system/queues/remove-failed', {
        method: 'POST',
        body: { confirmToken },
      })
    },
    onSuccess: async () => {
      await invalidateSystemQueries(queryClient)
    },
  })
}

export function useCacheClearMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async () => {
      const { confirmToken } = await issueDangerousActionToken('cache.clear.app')

      return apiRequest<SystemCommandResult>('/admin/api/system/cache/clear', {
        method: 'POST',
        body: { confirmToken },
      })
    },
    onSuccess: async () => {
      await invalidateSystemQueries(queryClient)
    },
  })
}
