import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'
import { adminQueryKeys, queryOptions } from '../../shared/api/query'
import type { ContentBlockItem, ContentPageDetail, ContentPageItem, PageRevisionItem } from '../../types/api'

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

const BUILDER_BLOCK_TYPE = 'builder_canvas'

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

interface BuilderSnapshot {
  html: string
  css: string
}

interface UpsertBuilderCanvasArgs {
  pageId: string
  snapshot: BuilderSnapshot
  existingBlock?: ContentBlockItem
}

export async function upsertBuilderCanvasBlock({
  pageId,
  snapshot,
  existingBlock,
}: UpsertBuilderCanvasArgs): Promise<ContentBlockItem> {
  if (existingBlock !== undefined) {
    return apiRequest<ContentBlockItem>(`/admin/api/content/blocks/${existingBlock.id}`, {
      method: 'PUT',
      body: {
        type: existingBlock.type,
        name: existingBlock.name,
        content: {
          ...existingBlock.content,
          html: snapshot.html,
          css: snapshot.css,
        },
        settings: existingBlock.settings,
        isEnabled: existingBlock.isEnabled,
        visibility: existingBlock.visibility,
      },
    })
  }

  return apiRequest<ContentBlockItem>(`/admin/api/content/pages/${pageId}/blocks`, {
    method: 'POST',
    body: {
      type: BUILDER_BLOCK_TYPE,
      name: 'Builder Canvas',
      position: 0,
      content: {
        html: snapshot.html,
        css: snapshot.css,
      },
      settings: {
        source: 'page_builder',
      },
      isEnabled: true,
      visibility: 'public',
    },
  })
}

export async function reorderPageBlocks(pageId: string, blockIds: string[]): Promise<ContentBlockItem[]> {
  const response = await apiRequest<{ blocks: ContentBlockItem[] }>(`/admin/api/content/pages/${pageId}/blocks/reorder`, {
    method: 'POST',
    body: { blockIds },
  })

  return response.blocks
}

export async function updateBlockRichText(block: ContentBlockItem, richText: string): Promise<ContentBlockItem> {
  return apiRequest<ContentBlockItem>(`/admin/api/content/blocks/${block.id}`, {
    method: 'PUT',
    body: {
      type: block.type,
      name: block.name,
      content: {
        ...block.content,
        richText,
      },
      settings: block.settings,
      isEnabled: block.isEnabled,
      visibility: block.visibility,
    },
  })
}

export function findBuilderCanvasBlock(blocks: ContentBlockItem[]): ContentBlockItem | undefined {
  return blocks.find((block) => block.type === BUILDER_BLOCK_TYPE)
}
