import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest, apiUpload } from '../../shared/api/client'
import { adminQueryKeys } from '../../shared/api/query'
import type { MediaAssetItem, MediaFolderItem, MediaPagination, MediaUsageResponse } from '../../types/api'

export type MediaTypeFilter = '' | 'image' | 'document'
export type MediaSort = 'newest' | 'oldest' | 'name' | 'size' | 'size_asc'
export type MediaFormatFilter = '' | 'jpeg' | 'png' | 'webp' | 'avif' | 'pdf'
export type MediaUsageFilter = '' | 'used' | 'unused'

/** Служебное значение фильтра по папке: файлы без папки. */
export const MEDIA_FOLDER_NONE = '__none__'

export interface MediaListParams {
  page: number
  perPage: number
  q: string
  type: MediaTypeFilter
  sort: MediaSort
  folder?: string
  format?: MediaFormatFilter
  usage?: MediaUsageFilter
  from?: string
  to?: string
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

  const optional: Array<[string, string | undefined]> = [
    ['folder', params.folder],
    ['format', params.format],
    ['usage', params.usage],
    ['from', params.from],
    ['to', params.to],
  ]
  for (const [key, value] of optional) {
    if (value !== undefined && value !== '') {
      query.set(key, value)
    }
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

export function useMediaFoldersQuery() {
  return useQuery({
    queryKey: [...adminQueryKeys.media, 'folders'] as const,
    queryFn: ({ signal }) => apiRequest<{ folders: MediaFolderItem[] }>('/admin/api/media/folders', { signal }),
    select: (data) => data.folders,
  })
}

export function useMediaUsagesQuery(id: string | null) {
  return useQuery({
    queryKey: [...adminQueryKeys.media, 'usages', id] as const,
    queryFn: ({ signal }) => apiRequest<MediaUsageResponse>(`/admin/api/media/assets/${id}/usages`, { signal }),
    enabled: id !== null,
    gcTime: 0,
  })
}

export function uploadMediaAsset(
  file: File,
  options: { signal?: AbortSignal; onProgress?: (fraction: number) => void; folder?: string } = {},
): Promise<MediaAssetItem> {
  const body = new FormData()
  body.append('file', file)
  if (options.folder !== undefined && options.folder !== '') {
    body.append('folder', options.folder)
  }

  return apiUpload<MediaAssetItem>('/admin/api/media/assets', body, options)
}

export function useInvalidateMediaAssets(): () => Promise<void> {
  const queryClient = useQueryClient()

  return () => queryClient.invalidateQueries({ queryKey: adminQueryKeys.media })
}

export function useUpdateMediaAssetMutation() {
  const invalidate = useInvalidateMediaAssets()

  return useMutation({
    mutationFn: (input: { id: string; alt: string; title: string; description?: string; folder?: string }) => {
      const { id, ...fields } = input

      return apiRequest<MediaAssetItem>(`/admin/api/media/assets/${id}`, { method: 'PATCH', body: fields })
    },
    onSuccess: invalidate,
  })
}

export function useDeleteMediaAssetMutation() {
  const invalidate = useInvalidateMediaAssets()

  return useMutation({
    mutationFn: (input: string | { id: string; force?: boolean }) => {
      const { id, force } = typeof input === 'string' ? { id: input, force: false } : input

      return apiRequest<null>(`/admin/api/media/assets/${id}${force === true ? '?force=1' : ''}`, { method: 'DELETE' })
    },
    onSuccess: invalidate,
  })
}
