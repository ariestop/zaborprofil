import { useQuery } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'
import type { RedirectItem } from '../../types/api'

export function useRedirectsQuery() {
  return useQuery({
    queryKey: ['admin', 'seo', 'redirects'],
    queryFn: () => apiRequest<RedirectItem[]>('/admin/api/seo/redirects'),
  })
}
