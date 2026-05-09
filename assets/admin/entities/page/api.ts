import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'
import { adminQueryKeys, queryOptions } from '../../shared/api/query'
import type { ContentPageDetail, ContentPageItem, PageRevisionItem } from '../../types/api'
import type { BuilderBlock } from '../../modules/page-builder/types'

export interface PageListResponse {
  pages: ContentPageItem[]
}

export interface PageRevisionsResponse {
  revisions: PageRevisionItem[]
}

export interface PagePreviewResponse {
  previewUrl: string
  robots: string
}

export interface PageUpdatePayload {
  type: string
  title: string
  slug: string
  path: string
  h1: string
  template: string
  sortOrder: number
  isIndexable: boolean
  parentId: string | null
  visibility: 'public' | 'hidden' | 'unlisted'
}

export type PageCreatePayload = PageUpdatePayload

function pagesQueryKey() {
  return adminQueryKeys.pages
}

function pageQueryKey(pageId: string) {
  return adminQueryKeys.pageById(pageId)
}

export function usePagesQuery() {
  return useQuery(queryOptions(
    pagesQueryKey(),
    async () => {
      const response = await apiRequest<PageListResponse>('/admin/api/content/pages')
      return response.pages
    },
  ))
}

export function usePageDetailQuery(pageId: string) {
  return useQuery(queryOptions(
    pageQueryKey(pageId),
    () => apiRequest<ContentPageDetail>(`/admin/api/content/pages/${pageId}`),
  ))
}

export function usePageRevisionsQuery(pageId: string) {
  return useQuery(queryOptions(
    ['admin', 'pages', pageId, 'revisions'],
    async () => {
      const response = await apiRequest<PageRevisionsResponse>(`/admin/api/content/pages/${pageId}/revisions`)
      return response.revisions
    },
  ))
}

export function usePagePreviewLinkQuery(pageId: string) {
  return useQuery(queryOptions(
    ['admin', 'pages', pageId, 'preview-link'],
    () => apiRequest<PagePreviewResponse>(`/admin/api/content/pages/${pageId}/preview-link`),
  ))
}

export function useUpdatePageMutation(pageId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: PageUpdatePayload) => apiRequest<ContentPageItem>(`/admin/api/content/pages/${pageId}`, {
      method: 'PUT',
      body: payload,
    }),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: pagesQueryKey() }),
        queryClient.invalidateQueries({ queryKey: pageQueryKey(pageId) }),
      ])
    },
  })
}

export function usePublishPageMutation(pageId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => apiRequest<ContentPageItem>(`/admin/api/content/pages/${pageId}/publish`, {
      method: 'POST',
      body: {},
    }),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: pagesQueryKey() }),
        queryClient.invalidateQueries({ queryKey: pageQueryKey(pageId) }),
      ])
    },
  })
}

export function useCreatePageMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: PageCreatePayload) => apiRequest<ContentPageItem>('/admin/api/content/pages', {
      method: 'POST',
      body: payload,
    }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: pagesQueryKey() })
    },
  })
}

export interface BuilderDocumentResponse {
  pageId: string
  updatedAt: string | null
  blocks: BuilderBlock[]
}

export interface BuilderPreviewResponse {
  html: string
}

export function usePageBuilderQuery(pageId: string) {
  return useQuery(queryOptions(
    ['admin', 'pages', pageId, 'builder'],
    () => apiRequest<BuilderDocumentResponse>(`/admin/api/content/pages/${pageId}/builder`),
  ))
}

export function useSavePageBuilderMutation(pageId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (blocks: BuilderBlock[]) => apiRequest<BuilderDocumentResponse>(`/admin/api/content/pages/${pageId}/builder`, {
      method: 'PUT',
      body: { blocks },
    }),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['admin', 'pages', pageId, 'builder'] }),
        queryClient.invalidateQueries({ queryKey: pageQueryKey(pageId) }),
      ])
    },
  })
}

export async function previewPageBuilder(pageId: string, blocks: BuilderBlock[]): Promise<BuilderPreviewResponse> {
  return apiRequest<BuilderPreviewResponse>(`/admin/api/content/pages/${pageId}/builder/preview`, {
    method: 'POST',
    body: { blocks },
  })
}

export function usePublishPageBuilderMutation(pageId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => apiRequest<ContentPageItem>(`/admin/api/content/pages/${pageId}/builder/publish`, {
      method: 'POST',
      body: {},
    }),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['admin', 'pages', pageId, 'builder'] }),
        queryClient.invalidateQueries({ queryKey: pageQueryKey(pageId) }),
        queryClient.invalidateQueries({ queryKey: pagesQueryKey() }),
      ])
    },
  })
}
