import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'

export type PortfolioMediaKind = 'photo' | 'video' | 'embed'
export type PortfolioStatus = 'draft' | 'published'

export interface PortfolioCategory {
  id: string
  slug: string
  title: string
  shortTitle: string | null
  description: string | null
  sortOrder: number
  path: string
  updatedAt: string
}

export interface PortfolioSpec {
  label: string
  value: string
}

export interface PortfolioMedia {
  id?: string
  /** Ключ нового, ещё не сохранённого элемента в редакторе; на сервер не отправляется. */
  clientKey?: string
  kind: PortfolioMediaKind
  src: string
  poster: string | null
  caption: string | null
  isCover: boolean
}

/** Работа в общем списке: без описания и медиа, только обложка и счётчики. */
export interface PortfolioProjectSummary {
  id: string
  categoryId: string
  slug: string
  title: string
  place: string | null
  year: number | null
  summary: string | null
  specs: PortfolioSpec[]
  status: PortfolioStatus
  sortOrder: number
  seoTitle: string | null
  seoDescription: string | null
  path: string
  cover: string | null
  photoCount: number
  videoCount: number
  publishedAt: string | null
  updatedAt: string
}

export interface PortfolioProject extends PortfolioProjectSummary {
  description: string
  media: PortfolioMedia[]
}

export interface PortfolioOverview {
  categories: PortfolioCategory[]
  projects: PortfolioProjectSummary[]
}

export interface PortfolioCategoryPayload {
  title: string
  slug: string
  shortTitle: string | null
  description: string | null
}

export interface PortfolioProjectPayload {
  categoryId: string
  title: string
  slug: string
  place: string | null
  year: number | null
  summary: string | null
  description: string
  specs: PortfolioSpec[]
  media: Array<Omit<PortfolioMedia, 'id'>>
  seoTitle: string | null
  seoDescription: string | null
}

export const portfolioQueryKey = ['admin', 'portfolio'] as const

export const portfolioProjectQueryKey = (id: string) => ['admin', 'portfolio', 'project', id] as const

export function usePortfolioQuery() {
  return useQuery({
    queryKey: portfolioQueryKey,
    queryFn: () => apiRequest<PortfolioOverview>('/admin/api/portfolio'),
  })
}

export function usePortfolioProjectQuery(id: string | null) {
  return useQuery({
    queryKey: portfolioProjectQueryKey(id ?? ''),
    queryFn: () => apiRequest<PortfolioProject>(`/admin/api/portfolio/projects/${id ?? ''}`),
    enabled: id !== null,
  })
}

function usePortfolioMutation<TVariables, TResult>(mutationFn: (variables: TVariables) => Promise<TResult>) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    onSettled: async () => {
      await queryClient.invalidateQueries({ queryKey: portfolioQueryKey })
    },
  })
}

export function useCreatePortfolioCategoryMutation() {
  return usePortfolioMutation((payload: PortfolioCategoryPayload) =>
    apiRequest<PortfolioCategory>('/admin/api/portfolio/categories', { method: 'POST', body: { ...payload } }),
  )
}

export function useUpdatePortfolioCategoryMutation() {
  return usePortfolioMutation(({ id, payload }: { id: string; payload: PortfolioCategoryPayload }) =>
    apiRequest<PortfolioCategory>(`/admin/api/portfolio/categories/${id}`, { method: 'PUT', body: { ...payload } }),
  )
}

export function useDeletePortfolioCategoryMutation() {
  return usePortfolioMutation((id: string) => apiRequest<null>(`/admin/api/portfolio/categories/${id}`, { method: 'DELETE' }))
}

export function useCreatePortfolioProjectMutation() {
  return usePortfolioMutation((payload: PortfolioProjectPayload) =>
    apiRequest<PortfolioProject>('/admin/api/portfolio/projects', { method: 'POST', body: { ...payload } }),
  )
}

export function useUpdatePortfolioProjectMutation() {
  return usePortfolioMutation(({ id, payload }: { id: string; payload: PortfolioProjectPayload }) =>
    apiRequest<PortfolioProject>(`/admin/api/portfolio/projects/${id}`, { method: 'PUT', body: { ...payload } }),
  )
}

export function useDeletePortfolioProjectMutation() {
  return usePortfolioMutation((id: string) => apiRequest<null>(`/admin/api/portfolio/projects/${id}`, { method: 'DELETE' }))
}

export function useSetPortfolioPublishedMutation() {
  return usePortfolioMutation(({ id, published }: { id: string; published: boolean }) =>
    apiRequest<PortfolioProject>(`/admin/api/portfolio/projects/${id}/status`, { method: 'PUT', body: { published } }),
  )
}

/** Порядок категорий и работ сразу показываем в списке; при ошибке список перезагружается с сервера. */
function useOptimisticOrderMutation<TVariables>(
  mutationFn: (variables: TVariables) => Promise<unknown>,
  apply: (current: PortfolioOverview, variables: TVariables) => PortfolioOverview,
) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    onMutate: async (variables: TVariables) => {
      await queryClient.cancelQueries({ queryKey: portfolioQueryKey })
      queryClient.setQueryData<PortfolioOverview>(portfolioQueryKey, (current) => (current === undefined ? current : apply(current, variables)))
    },
    onSettled: async () => {
      await queryClient.invalidateQueries({ queryKey: portfolioQueryKey })
    },
  })
}

function reorderBy<T extends { id: string; sortOrder: number }>(items: T[], ids: string[]): T[] {
  const position = new Map(ids.map((id, index) => [id, (index + 1) * 10]))

  return items.map((item) => {
    const sortOrder = position.get(item.id)
    return sortOrder === undefined ? item : { ...item, sortOrder }
  })
}

export function useReorderPortfolioCategoriesMutation() {
  return useOptimisticOrderMutation(
    (ids: string[]) => apiRequest('/admin/api/portfolio/categories-order', { method: 'PUT', body: { ids } }),
    (current, ids) => ({ ...current, categories: reorderBy(current.categories, ids) }),
  )
}

export function useReorderPortfolioProjectsMutation() {
  return useOptimisticOrderMutation(
    ({ categoryId, ids }: { categoryId: string; ids: string[] }) =>
      apiRequest(`/admin/api/portfolio/categories/${categoryId}/projects-order`, { method: 'PUT', body: { ids } }),
    (current, { ids }) => ({ ...current, projects: reorderBy(current.projects, ids) }),
  )
}
