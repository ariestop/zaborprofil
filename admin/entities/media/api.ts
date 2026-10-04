import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest, apiUpload } from '../../shared/api/client'
import { adminQueryKeys } from '../../shared/api/query'
import type { MediaAssetItem, MediaPagination } from '../../types/api'

export type MediaTypeFilter = '' | 'image' | 'document'
export type MediaSort = 'newest' | 'oldest' | 'name' | 'size'

export interface MediaListParams {
  page: number
  perPage: number
  q: string
  type: MediaTypeFilter
  sort: MediaSort
}

export interface MediaListResponse {
  assets: MediaAssetItem[]
  pagination: MediaPagination
}

export const DEFAULT_MEDIA_LIST_PARAMS: MediaListParams = {
  page: 1,
  perPage: 24,
  q: '',
  type: '',
  sort: 'newest',
}

export function buildMediaListPath(params: MediaListParams): string {
  const query = new URLSearchParams({
    page: String(params.page),
    perPage: String(params.perPage),
    sort: params.sort,
  })

  if (params.q.trim() !== '') {
    query.set('q', params.q.trim())
  }

  if (params.type !== '') {
    query.set('type', params.type)
  }

  return `/admin/api/media/assets?${query.toString()}`
}

export function fetchMediaAssets(params: MediaListParams, signal?: AbortSignal): Promise<MediaListResponse> {
  return apiRequest<MediaListResponse>(buildMediaListPath(params), { signal })
}

export function useMediaAssetsQuery(params: MediaListParams = DEFAULT_MEDIA_LIST_PARAMS) {
  return useQuery({
    queryKey: [...adminQueryKeys.media, 'assets', params] as const,
    queryFn: ({ signal }) => fetchMediaAssets(params, signal),
    placeholderData: keepPreviousData,
  })
}

export function uploadMediaAsset(
  file: File,
  options: { signal?: AbortSignal; onProgress?: (fraction: number) => void } = {},
): Promise<MediaAssetItem> {
  const body = new FormData()
  body.append('file', file)

  return apiUpload<MediaAssetItem>('/admin/api/media/assets', body, options)
}

export function useInvalidateMediaAssets(): () => Promise<void> {
  const queryClient = useQueryClient()

  return () => queryClient.invalidateQueries({ queryKey: adminQueryKeys.media })
}

export function useUpdateMediaAssetMutation() {
  const invalidate = useInvalidateMediaAssets()

  return useMutation({
    mutationFn: (input: { id: string; alt: string; title: string }) =>
      apiRequest<MediaAssetItem>(`/admin/api/media/assets/${input.id}`, {
        method: 'PATCH',
        body: { alt: input.alt, title: input.title },
      }),
    onSuccess: invalidate,
  })
}

export function useDeleteMediaAssetMutation() {
  const invalidate = useInvalidateMediaAssets()

  return useMutation({
    mutationFn: (id: string) => apiRequest<null>(`/admin/api/media/assets/${id}`, { method: 'DELETE' }),
    onSuccess: invalidate,
  })
}
