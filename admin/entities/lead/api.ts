import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'
import type { LeadItem } from '../../types/api'

export interface LeadListResponse {
  leads: LeadItem[]
  statuses: LeadItem['status'][]
}

function leadsQueryKey() {
  return ['admin', 'crm', 'leads'] as const
}

export function useLeadsQuery() {
  return useQuery({
    queryKey: leadsQueryKey(),
    queryFn: () => apiRequest<LeadListResponse>('/admin/api/leads'),
  })
}

export function useLeadStatusMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ leadId, status }: { leadId: string, status: LeadItem['status'] }) => apiRequest<LeadItem>(`/admin/api/leads/${leadId}/status`, {
      method: 'PATCH',
      body: { status },
    }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: leadsQueryKey() })
    },
  })
}
