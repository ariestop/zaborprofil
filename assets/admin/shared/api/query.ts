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
}

export function queryOptions<TData>(queryKey: QueryKey, queryFn: () => Promise<TData>) {
  return { queryKey, queryFn }
}
