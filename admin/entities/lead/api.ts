import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'
import { adminQueryKeys } from '../../shared/api/query'
import { useCan } from '../../stores/auth'
import type { LeadDetail, LeadStatus } from '../../types/api'
import type { LeadAssigneeOption, LeadFilters, LeadListParams, LeadListResponse, LeadSummary } from './model'

const leadsKey = [...adminQueryKeys.crm, 'leads'] as const

export const leadQueryKeys = {
  all: leadsKey,
  list: (params: LeadListParams) => [...leadsKey, 'list', params] as const,
  summary: [...leadsKey, 'summary'] as const,
  assignees: [...leadsKey, 'assignees'] as const,
  detail: (leadId: string) => [...leadsKey, 'detail', leadId] as const,
}

function filtersToSearch(filters: LeadFilters): URLSearchParams {
  const search = new URLSearchParams()
  if (filters.q.trim() !== '') {
    search.set('q', filters.q.trim())
  }
  // «Все» в рабочем месте — это все заявки, кроме спама.
  search.set('status', filters.status === 'all' ? 'active' : filters.status)
  if (filters.source !== '') {
    search.set('source', filters.source)
  }
  if (filters.from !== '') {
    search.set('from', filters.from)
  }
  if (filters.to !== '') {
    search.set('to', filters.to)
  }
  if (filters.assignee !== 'all') {
    search.set('assignee', filters.assignee)
  }
  if (filters.b2b) {
    search.set('b2b', '1')
  }
  if (filters.waitingHours > 0) {
    search.set('waiting', String(filters.waitingHours))
  }

  return search
}

export function buildLeadsUrl(params: LeadListParams): string {
  const search = filtersToSearch(params)
  search.set('sort', params.sort)
  search.set('direction', params.direction)
  search.set('page', String(params.page))
  search.set('perPage', String(params.perPage))

  return `/admin/api/leads?${search.toString()}`
}

export function buildLeadsExportUrl(filters: LeadFilters, sort: LeadListParams['sort'], direction: LeadListParams['direction']): string {
  const search = filtersToSearch(filters)
  search.set('sort', sort)
  search.set('direction', direction)

  return `/admin/api/leads/export?${search.toString()}`
}

export function useLeadsQuery(params: LeadListParams) {
  return useQuery({
    queryKey: leadQueryKeys.list(params),
    queryFn: () => apiRequest<LeadListResponse>(buildLeadsUrl(params)),
    placeholderData: keepPreviousData,
  })
}

export function useLeadSummaryQuery() {
  const enabled = useCan('leads.view')

  return useQuery({
    enabled,
    queryKey: leadQueryKeys.summary,
    queryFn: () => apiRequest<LeadSummary>('/admin/api/leads/summary'),
  })
}

export function useLeadAssigneesQuery() {
  return useQuery({
    queryKey: leadQueryKeys.assignees,
    queryFn: () => apiRequest<{ items: LeadAssigneeOption[] }>('/admin/api/leads/assignees'),
    staleTime: 60_000,
  })
}

export function useLeadQuery(leadId: string) {
  return useQuery({
    queryKey: leadQueryKeys.detail(leadId),
    queryFn: () => apiRequest<LeadDetail>(`/admin/api/leads/${encodeURIComponent(leadId)}`),
  })
}

export function exportLeadsCsv(filters: LeadFilters, sort: LeadListParams['sort'], direction: LeadListParams['direction']): Promise<string> {
  return apiRequest<string>(buildLeadsExportUrl(filters, sort, direction))
}

function useLeadMutation<TVariables>(mutationFn: (variables: TVariables) => Promise<LeadDetail>) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    onSuccess: async (lead) => {
      queryClient.setQueryData(leadQueryKeys.detail(lead.id), lead)
      await queryClient.invalidateQueries({ queryKey: leadsKey })
    },
  })
}

export function useLeadStatusMutation() {
  return useLeadMutation(({ leadId, status }: { leadId: string, status: LeadStatus }) =>
    apiRequest<LeadDetail>(`/admin/api/leads/${encodeURIComponent(leadId)}/status`, { method: 'PATCH', body: { status } }),
  )
}

export function useLeadAssigneeMutation() {
  return useLeadMutation(({ leadId, assigneeId }: { leadId: string, assigneeId: string | null }) =>
    apiRequest<LeadDetail>(`/admin/api/leads/${encodeURIComponent(leadId)}/assignee`, { method: 'PATCH', body: { assigneeId } }),
  )
}

export interface NewLeadInput {
  name: string
  phone: string
  email: string
  message: string
}

export function useLeadCreateMutation() {
  return useLeadMutation((input: NewLeadInput) =>
    apiRequest<LeadDetail>('/admin/api/leads', {
      method: 'POST',
      body: { name: input.name, phone: input.phone, email: input.email.trim() === '' ? null : input.email, message: input.message.trim() === '' ? null : input.message },
    }),
  )
}

/** Отметка «прочитана»: убирает маркер новой заявки в списке. Не меняет статус и не пишется в историю. */
export function useLeadReadMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (leadId: string) =>
      apiRequest<{ id: string, readAt: string | null }>(`/admin/api/leads/${encodeURIComponent(leadId)}/read`, { method: 'PATCH', body: {} }),
    onSuccess: async (result) => {
      queryClient.setQueryData<LeadDetail>(leadQueryKeys.detail(result.id), (lead) => (lead === undefined ? lead : { ...lead, readAt: result.readAt }))
      await queryClient.invalidateQueries({ queryKey: [...leadsKey, 'list'] })
    },
  })
}

export function useLeadNoteMutation() {
  return useLeadMutation(({ leadId, text }: { leadId: string, text: string }) =>
    apiRequest<LeadDetail>(`/admin/api/leads/${encodeURIComponent(leadId)}/notes`, { method: 'POST', body: { text } }),
  )
}
