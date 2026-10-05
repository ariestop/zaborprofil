import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'
import { adminQueryKeys } from '../../shared/api/query'
import type {
  NotFoundListParams,
  NotFoundListResponse,
  RedirectAnalysis,
  RedirectImportReport,
  RedirectListParams,
  RedirectListResponse,
  RedirectPayload,
  RedirectSaveResult,
  RobotsPreview,
  RobotsSettings,
  SeoAuditResult,
} from './model'

const redirectsKey = [...adminQueryKeys.seo, 'redirects'] as const
const robotsKey = [...adminQueryKeys.seo, 'robots'] as const
const notFoundKey = [...adminQueryKeys.seo, 'not-found'] as const

export const seoQueryKeys = {
  redirects: redirectsKey,
  redirectAnalysis: [...redirectsKey, 'analysis'] as const,
  robots: robotsKey,
  notFound: notFoundKey,
}

export function buildRedirectsUrl(params: RedirectListParams): string {
  const search = new URLSearchParams({
    page: String(params.page),
    perPage: String(params.perPage),
    sort: params.sort,
    direction: params.direction,
    status: params.status,
  })
  if (params.q.trim() !== '') {
    search.set('q', params.q.trim())
  }

  return `/admin/api/seo/redirects?${search.toString()}`
}

export function buildNotFoundUrl(params: NotFoundListParams): string {
  const search = new URLSearchParams({
    page: String(params.page),
    perPage: String(params.perPage),
    sort: params.sort,
  })
  if (params.q.trim() !== '') {
    search.set('q', params.q.trim())
  }

  return `/admin/api/seo/not-found?${search.toString()}`
}

export function useRedirectsQuery(params: RedirectListParams) {
  return useQuery({
    queryKey: [...redirectsKey, 'list', params],
    queryFn: () => apiRequest<RedirectListResponse>(buildRedirectsUrl(params)),
    placeholderData: keepPreviousData,
  })
}

export function useRedirectAnalysisQuery(enabled: boolean) {
  return useQuery({
    queryKey: seoQueryKeys.redirectAnalysis,
    queryFn: () => apiRequest<RedirectAnalysis>('/admin/api/seo/redirects/analysis'),
    enabled,
    staleTime: 0,
  })
}

function useSeoMutation<TVariables, TResult>(mutationFn: (variables: TVariables) => Promise<TResult>) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: adminQueryKeys.seo })
    },
  })
}

export function useCreateRedirectMutation() {
  return useSeoMutation((payload: RedirectPayload) =>
    apiRequest<RedirectSaveResult>('/admin/api/seo/redirects', { method: 'POST', body: payload }),
  )
}

export function useUpdateRedirectMutation() {
  return useSeoMutation(({ id, payload }: { id: string, payload: RedirectPayload }) =>
    apiRequest<RedirectSaveResult>(`/admin/api/seo/redirects/${id}`, { method: 'PUT', body: payload }),
  )
}

export function useDeleteRedirectMutation() {
  return useSeoMutation((id: string) =>
    apiRequest<void>(`/admin/api/seo/redirects/${id}`, { method: 'DELETE' }),
  )
}

export function importRedirects(csv: string, options: { dryRun: boolean, updateExisting: boolean }): Promise<RedirectImportReport> {
  return apiRequest<RedirectImportReport>('/admin/api/seo/redirects/import', {
    method: 'POST',
    body: { csv, dryRun: options.dryRun, updateExisting: options.updateExisting },
  })
}

export function useImportRedirectsMutation() {
  return useSeoMutation(({ csv, dryRun, updateExisting }: { csv: string, dryRun: boolean, updateExisting: boolean }) =>
    importRedirects(csv, { dryRun, updateExisting }),
  )
}

export function exportRedirectsCsv(): Promise<string> {
  return apiRequest<string>('/admin/api/seo/redirects/export')
}

export function useRobotsQuery() {
  return useQuery({
    queryKey: robotsKey,
    queryFn: () => apiRequest<RobotsSettings>('/admin/api/seo/robots'),
  })
}

export function previewRobots(body: string): Promise<RobotsPreview> {
  return apiRequest<RobotsPreview>('/admin/api/seo/robots/preview', { method: 'POST', body: { body } })
}

export function useSaveRobotsMutation() {
  return useSeoMutation((body: string | null) =>
    apiRequest<RobotsSettings>('/admin/api/seo/robots', { method: 'PUT', body: { body } }),
  )
}

export function useNotFoundQuery(params: NotFoundListParams) {
  return useQuery({
    queryKey: [...notFoundKey, 'list', params],
    queryFn: () => apiRequest<NotFoundListResponse>(buildNotFoundUrl(params)),
    placeholderData: keepPreviousData,
  })
}

export function useDeleteNotFoundMutation() {
  return useSeoMutation((id: string) =>
    apiRequest<void>(`/admin/api/seo/not-found/${id}`, { method: 'DELETE' }),
  )
}

export function useClearNotFoundMutation() {
  return useSeoMutation((olderThanDays: number | null) =>
    apiRequest<{ removed: number }>(
      olderThanDays === null ? '/admin/api/seo/not-found' : `/admin/api/seo/not-found?olderThanDays=${olderThanDays}`,
      { method: 'DELETE' },
    ),
  )
}

export function fetchPageSeoAudit(pageId: string): Promise<SeoAuditResult> {
  return apiRequest<SeoAuditResult>(`/admin/api/seo/audit/pages/${pageId}`)
}
