import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'
import { adminQueryKeys } from '../../shared/api/query'
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
  if (filters.status !== 'all') {
    search.set('status', filters.status)
  }
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
  return useQuery({
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

export function useLeadNoteMutation() {
  return useLeadMutation(({ leadId, text }: { leadId: string, text: string }) =>
    apiRequest<LeadDetail>(`/admin/api/leads/${encodeURIComponent(leadId)}/notes`, { method: 'POST', body: { text } }),
  )
}
