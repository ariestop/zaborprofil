import type { QueryKey } from '@tanstack/react-query'

export const adminQueryKeys = {
  dashboard: ['admin', 'dashboard'] as const,
  pages: ['admin', 'pages'] as const,
  pageById: (id: string) => ['admin', 'pages', id] as const,
  media: ['admin', 'media'] as const,
  seo: ['admin', 'seo'] as const,
  crm: ['admin', 'crm'] as const,
  settings: ['admin', 'settings'] as const,
  users: ['admin', 'users'] as const,
  systemOverview: ['admin', 'system', 'overview'] as const,
  systemProcesses: ['admin', 'system', 'processes'] as const,
  systemLogs: (channel: string) => ['admin', 'system', 'logs', channel] as const,
  systemQueues: ['admin', 'system', 'queues'] as const,
  systemCache: ['admin', 'system', 'cache'] as const,
  systemDatabase: ['admin', 'system', 'database'] as const,
  systemSecurity: ['admin', 'system', 'security'] as const,
  systemBackups: ['admin', 'system', 'backups'] as const,
  systemDeploy: ['admin', 'system', 'deploy'] as const,
  systemAudit: ['admin', 'system', 'audit'] as const,
}

export function queryOptions<TData>(queryKey: QueryKey, queryFn: () => Promise<TData>) {
  return { queryKey, queryFn }
}
