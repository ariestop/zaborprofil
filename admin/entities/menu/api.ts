import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiRequest } from '../../shared/api/client'

export type MenuPosition = 'header' | 'footer' | 'service'

export interface MenuItem {
  id: string
  position: MenuPosition
  parentId: string | null
  label: string
  url: string
  hint: string | null
  sortOrder: number
  isActive: boolean
  updatedAt: string
}

export interface MenuPositionOption {
  value: MenuPosition
  label: string
}

interface MenuItemsResponse {
  items: MenuItem[]
  positions: MenuPositionOption[]
}

/** Поля пункта для создания и правки. sortOrder не передаём: сервер ставит новый пункт в конец. */
export interface MenuItemPayload {
  position: MenuPosition
  label: string
  url: string
  hint: string | null
  isActive: boolean
  parentId: string | null
}

export interface MenuOrderEntry {
  id: string
  parentId: string | null
}

export const menuQueryKey = ['admin', 'menu'] as const

export function useMenuQuery() {
  return useQuery({
    queryKey: menuQueryKey,
    queryFn: () => apiRequest<MenuItemsResponse>('/admin/api/menu/items'),
  })
}

function useMenuMutation<TVariables, TResult>(mutationFn: (variables: TVariables) => Promise<TResult>) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    onSettled: async () => {
      await queryClient.invalidateQueries({ queryKey: menuQueryKey })
    },
  })
}

export function useCreateMenuItemMutation() {
  return useMenuMutation((payload: MenuItemPayload) =>
    apiRequest<MenuItem>('/admin/api/menu/items', { method: 'POST', body: { ...payload } }),
  )
}

export function useUpdateMenuItemMutation() {
  return useMenuMutation(({ id, payload }: { id: string, payload: MenuItemPayload }) =>
    apiRequest<MenuItem>(`/admin/api/menu/items/${id}`, { method: 'PUT', body: { ...payload } }),
  )
}

export function useDeleteMenuItemMutation() {
  return useMenuMutation((id: string) => apiRequest<null>(`/admin/api/menu/items/${id}`, { method: 'DELETE' }))
}

/** Новый порядок позиции: сразу показываем его в списке, при ошибке список перезагружается с сервера. */
export function useReorderMenuMutation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ position, items }: { position: MenuPosition, items: MenuOrderEntry[] }) =>
      apiRequest<{ items: MenuItem[] }>(`/admin/api/menu/positions/${position}/order`, { method: 'PUT', body: { items } }),
    onMutate: async ({ position, items }) => {
      await queryClient.cancelQueries({ queryKey: menuQueryKey })
      queryClient.setQueryData<MenuItemsResponse>(menuQueryKey, (current) => {
        if (current === undefined) {
          return current
        }
        const order = new Map(items.map((entry, index) => [entry.id, { index, parentId: entry.parentId }]))
        const others = current.items.filter((item) => item.position !== position)
        const moved = current.items
          .filter((item) => item.position === position)
          .map((item) => {
            const next = order.get(item.id)
            return next === undefined ? item : { ...item, parentId: next.parentId, sortOrder: (next.index + 1) * 10 }
          })

        return { ...current, items: [...others, ...moved] }
      })
    },
    onSettled: async () => {
      await queryClient.invalidateQueries({ queryKey: menuQueryKey })
    },
  })
}
