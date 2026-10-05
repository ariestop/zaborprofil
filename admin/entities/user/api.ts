import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'

export interface AdminUserItem {
  id: string
  email: string
  name: string | null
  roles: string[]
  active: boolean
  createdAt: string
  updatedAt: string
}

interface AdminUsersResponse {
  users: AdminUserItem[]
  availableRoles?: string[]
}

export const DEFAULT_ADMIN_ROLES = ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_EDITOR', 'ROLE_SEO', 'ROLE_MANAGER']

function usersQueryKey() {
  return ['admin', 'users'] as const
}

export function useAdminUsersQuery() {
  return useQuery({
    queryKey: usersQueryKey(),
    queryFn: async () => {
      const response = await apiRequest<AdminUsersResponse>('/admin/api/users')
      return {
        users: response.users,
        availableRoles: response.availableRoles ?? DEFAULT_ADMIN_ROLES,
      }
    },
  })
}

function useUsersMutation<TVariables, TResult>(mutationFn: (variables: TVariables) => Promise<TResult>) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: usersQueryKey() })
    },
  })
}

export function useCreateUserMutation() {
  return useUsersMutation(({ email, password, roles, name }: { email: string, password: string, roles: string[], name?: string }) =>
    apiRequest<AdminUserItem>('/admin/api/users', { method: 'POST', body: { email, password, roles, name: name === undefined || name.trim() === '' ? null : name.trim() } }),
  )
}

export function useRenameUserMutation() {
  return useUsersMutation(({ userId, name }: { userId: string, name: string }) =>
    apiRequest<AdminUserItem>(`/admin/api/users/${userId}/name`, { method: 'PATCH', body: { name: name.trim() === '' ? null : name.trim() } }),
  )
}

export function useUpdateUserRolesMutation() {
  return useUsersMutation(({ userId, roles }: { userId: string, roles: string[] }) =>
    apiRequest<AdminUserItem>(`/admin/api/users/${userId}/roles`, { method: 'PATCH', body: { roles } }),
  )
}

export function useSetUserActiveMutation() {
  return useUsersMutation(({ userId, active }: { userId: string, active: boolean }) =>
    apiRequest<AdminUserItem>(`/admin/api/users/${userId}/active`, { method: 'PATCH', body: { active } }),
  )
}

export function useResetUserPasswordMutation() {
  return useUsersMutation(({ userId, password }: { userId: string, password: string }) =>
    apiRequest<AdminUserItem>(`/admin/api/users/${userId}/password`, { method: 'POST', body: { password } }),
  )
}

export function useDeleteUserMutation() {
  return useUsersMutation((userId: string) =>
    apiRequest<void>(`/admin/api/users/${userId}`, { method: 'DELETE' }),
  )
}

export function useChangeOwnPasswordMutation() {
  return useMutation({
    mutationFn: ({ currentPassword, password }: { currentPassword: string, password: string }) =>
      apiRequest<{ status: string }>('/admin/api/users/me/password', { method: 'POST', body: { currentPassword, password } }),
  })
}
