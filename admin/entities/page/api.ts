import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'
import { adminQueryKeys, queryOptions } from '../../shared/api/query'
import type { ContentPageDetail, ContentPageItem, PageRevisionItem, PageSeoPayload, PageTemplateItem } from '../../types/api'
import type { BuilderBlock } from '../../modules/page-builder/types'
import { deserializeBuilderBlocks, serializeBuilderBlocks } from '../../modules/page-builder/utils/blockSerialization'

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

export type PageSeoUpdatePayload = Omit<PageSeoPayload, 'ogType'> & { ogType: string | null }

export type PageStatus = ContentPageItem['status']

export interface PageTemplatesResponse {
  templates: PageTemplateItem[]
}

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

export function fetchPageDetail(pageId: string): Promise<ContentPageDetail> {
  return apiRequest<ContentPageDetail>(`/admin/api/content/pages/${pageId}`)
}

export function usePageDetailQuery(pageId: string) {
  return useQuery(queryOptions(
    pageQueryKey(pageId),
    () => fetchPageDetail(pageId),
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

export function usePageBuilderVersionsQuery(pageId: string) {
  return useQuery(queryOptions(
    ['admin', 'pages', pageId, 'builder-versions'],
    async () => {
      const response = await apiRequest<PageRevisionsResponse>(`/admin/api/content/pages/${pageId}/builder/versions`)
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

export function usePageTemplatesQuery() {
  return useQuery(queryOptions(
    ['admin', 'page-templates'],
    async () => {
      const response = await apiRequest<PageTemplatesResponse>('/admin/api/content/templates')
      return response.templates
    },
  ))
}

export function fetchPagePreviewLink(pageId: string): Promise<PagePreviewResponse> {
  return apiRequest<PagePreviewResponse>(`/admin/api/content/pages/${pageId}/preview-link`)
}

export function useUpdatePageSeoMutation(pageId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: PageSeoUpdatePayload) => apiRequest<ContentPageItem>(`/admin/api/content/pages/${pageId}/seo`, {
      method: 'PUT',
      body: payload,
    }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: pagesQueryKey() })
    },
  })
}

export function useChangePageStatusMutation(pageId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (status: PageStatus) => apiRequest<ContentPageItem>(`/admin/api/content/pages/${pageId}/status`, {
      method: 'PATCH',
      body: { status },
    }),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: pagesQueryKey() }),
        queryClient.invalidateQueries({ queryKey: pageQueryKey(pageId) }),
      ])
    },
  })
}

export async function createPageStarterBlocks(pageId: string, template: PageTemplateItem): Promise<void> {
  for (const block of template.blocksSchema) {
    await apiRequest(`/admin/api/content/pages/${pageId}/blocks`, {
      method: 'POST',
      body: {
        type: block.type,
        name: block.name,
        position: block.position,
        content: block.content,
        settings: block.settings,
        isEnabled: block.type === 'faq' ? false : block.isEnabled,
      },
    })
  }
}

export interface PageEditorData {
  page: ContentPageDetail
  builder: BuilderDocumentResponse
}

/**
 * Данные единого редактора страницы. Кэш не переиспользуется между открытиями (gcTime: 0),
 * чтобы редактор всегда стартовал с актуальных блоков; при сохранении запрос обновляется
 * вместе с остальными ключами `['admin', 'pages', id, ...]`.
 */
export function usePageEditorDataQuery(pageId: string) {
  return useQuery({
    queryKey: [...pageQueryKey(pageId), 'editor'],
    queryFn: async (): Promise<PageEditorData> => {
      const [page, builder] = await Promise.all([fetchPageDetail(pageId), fetchPageBuilder(pageId)])
      return { page, builder }
    },
    staleTime: 0,
    gcTime: 0,
  })
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

function deserializeBuilderDocument(document: BuilderDocumentResponse): BuilderDocumentResponse {
  return {
    ...document,
    blocks: deserializeBuilderBlocks(document.blocks),
  }
}

export async function fetchPageBuilder(pageId: string): Promise<BuilderDocumentResponse> {
  return deserializeBuilderDocument(
    await apiRequest<BuilderDocumentResponse>(`/admin/api/content/pages/${pageId}/builder`),
  )
}

export function usePageBuilderQuery(pageId: string) {
  return useQuery(queryOptions(
    ['admin', 'pages', pageId, 'builder'],
    () => fetchPageBuilder(pageId),
  ))
}

export function useSavePageBuilderMutation(pageId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (blocks: BuilderBlock[]) => deserializeBuilderDocument(
      await apiRequest<BuilderDocumentResponse>(`/admin/api/content/pages/${pageId}/builder`, {
        method: 'PUT',
        body: { blocks: serializeBuilderBlocks(blocks) },
      }),
    ),
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
    body: { blocks: serializeBuilderBlocks(blocks) },
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

export function useRollbackPageBuilderMutation(pageId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (revisionId: string) => apiRequest<ContentPageItem>(`/admin/api/content/pages/${pageId}/builder/rollback`, {
      method: 'POST',
      body: { revisionId },
    }),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['admin', 'pages', pageId, 'builder'] }),
        queryClient.invalidateQueries({ queryKey: ['admin', 'pages', pageId, 'builder-versions'] }),
        queryClient.invalidateQueries({ queryKey: pageQueryKey(pageId) }),
      ])
    },
  })
}
