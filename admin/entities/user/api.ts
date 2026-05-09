import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'

export interface AdminUserItem {
  id: string
  email: string
  roles: string[]
  active: boolean
  createdAt: string
  updatedAt: string
}

interface AdminUsersResponse {
  users: AdminUserItem[]
}

function usersQueryKey() {
  return ['admin', 'users'] as const
}

export function useAdminUsersQuery() {
  return useQuery({
    queryKey: usersQueryKey(),
    queryFn: async () => {
      const response = await apiRequest<AdminUsersResponse>('/admin/api/users')
      return response.users
    },
  })
}

export function useUpdateUserRolesMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ userId, roles }: { userId: string, roles: string[] }) => apiRequest<AdminUserItem>(`/admin/api/users/${userId}/roles`, {
      method: 'PATCH',
      body: { roles },
    }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: usersQueryKey() })
    },
  })
}
