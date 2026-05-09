import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { createColumnHelper } from '@tanstack/react-table'
import { PageHeader, Card, Input, Select, Button, DataTable, ErrorState, PageLoadingState } from '../shared/ui'
import { useAdminUsersQuery, useUpdateUserRolesMutation, type AdminUserItem } from '../entities/user/api'
import { useToast } from '../app/providers/toast-provider'
import { applyServerValidationErrors } from '../shared/api/validation'

const assignRoleSchema = z.object({
  email: z.string().email('Укажите корректный email'),
  role: z.enum(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_EDITOR', 'ROLE_SEO']),
})

type AssignRoleFormData = z.infer<typeof assignRoleSchema>

export default function UsersPage() {
  const [role, setRole] = useState<'ROLE_SUPER_ADMIN' | 'ROLE_ADMIN' | 'ROLE_EDITOR' | 'ROLE_SEO'>('ROLE_EDITOR')
  const usersQuery = useAdminUsersQuery()
  const updateRolesMutation = useUpdateUserRolesMutation()
  const { push } = useToast()
  const form = useForm<AssignRoleFormData>({
    resolver: zodResolver(assignRoleSchema),
    defaultValues: { email: '', role: 'ROLE_EDITOR' },
  })
  const submit = form.handleSubmit(async (values) => {
    try {
      const user = usersQuery.data?.find((item) => item.email === values.email)
      if (user === undefined) {
        form.setError('email', { type: 'manual', message: 'Пользователь не найден' })
        return
      }

      await updateRolesMutation.mutateAsync({
        userId: user.id,
        roles: [values.role],
      })
      push({
        title: 'Роли обновлены',
        description: `Пользователь ${values.email} получил роль ${values.role}.`,
      })
      await usersQuery.refetch()
    } catch (error) {
      applyServerValidationErrors(error, form.setError)
      push({
        title: 'Ошибка обновления роли',
        description: 'Проверьте права доступа users.manage.',
      })
    }
  })

  const columnHelper = createColumnHelper<AdminUserItem>()
  const columns = [
    columnHelper.accessor('email', { header: 'Email' }),
    columnHelper.accessor('roles', {
      header: 'Роли',
      cell: ({ getValue }) => getValue().join(', '),
    }),
    columnHelper.accessor('active', {
      header: 'Активен',
      cell: ({ getValue }) => (getValue() ? 'Да' : 'Нет'),
    }),
    columnHelper.accessor('updatedAt', { header: 'Обновлён' }),
  ]

  if (usersQuery.isPending) {
    return <PageLoadingState />
  }

  if (usersQuery.isError) {
    return (
      <ErrorState
        title="Не удалось загрузить пользователей"
        description="Проверьте endpoint /admin/api/users и права users.manage."
      />
    )
  }

  return (
    <div>
      <PageHeader title="Users & Roles" description="Foundation для управления пользователями, ролями и политиками доступа." />
      <Card title="Role assignment draft">
        <form className="grid gap-3 lg:grid-cols-2" onSubmit={submit}>
          <div>
            <Input placeholder="Email пользователя" {...form.register('email')} />
            {form.formState.errors.email?.message !== undefined ? (
              <p className="mt-1 text-xs text-red-600">{form.formState.errors.email.message}</p>
            ) : null}
          </div>
          <Select
            value={role}
            onValueChange={(nextValue) => {
              const nextRole = nextValue as 'ROLE_SUPER_ADMIN' | 'ROLE_ADMIN' | 'ROLE_EDITOR' | 'ROLE_SEO'
              setRole(nextRole)
              form.setValue('role', nextRole)
            }}
            options={[
              { value: 'ROLE_SUPER_ADMIN', label: 'Суперадминистратор' },
              { value: 'ROLE_ADMIN', label: 'Администратор' },
              { value: 'ROLE_EDITOR', label: 'Редактор' },
              { value: 'ROLE_SEO', label: 'SEO' },
            ]}
          />
          <div className="lg:col-span-2">
            <Button type="submit" disabled={updateRolesMutation.isPending}>
              {updateRolesMutation.isPending ? 'Сохранение...' : 'Сохранить роль'}
            </Button>
          </div>
        </form>
      </Card>
      <Card className="mt-4" title="Пользователи">
        <DataTable columns={columns} data={usersQuery.data ?? []} />
      </Card>
    </div>
  )
}
