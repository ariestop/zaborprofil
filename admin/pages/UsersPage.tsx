import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { createColumnHelper } from '@tanstack/react-table'
import { PageHeader, Card, Input, Select, Button, DataTable, ErrorState, PageLoadingState, ConfirmDialog, Dialog } from '../shared/ui'
import {
  useAdminUsersQuery,
  useChangeOwnPasswordMutation,
  useCreateUserMutation,
  useDeleteUserMutation,
  useResetUserPasswordMutation,
  useSetUserActiveMutation,
  useUpdateUserRolesMutation,
  type AdminUserItem,
} from '../entities/user/api'
import { useToast } from '../app/providers/toast-provider'
import { ApiError } from '../shared/api/client'
import { applyServerValidationErrors } from '../shared/api/validation'
import { useAuthStore } from '../stores/auth'

const MIN_PASSWORD_LENGTH = 12

const ROLE_LABELS: Record<string, string> = {
  ROLE_SUPER_ADMIN: 'Суперадминистратор',
  ROLE_ADMIN: 'Администратор',
  ROLE_EDITOR: 'Редактор',
  ROLE_SEO: 'SEO',
  ROLE_MANAGER: 'Менеджер',
}

const createUserSchema = z.object({
  email: z.string().email('Укажите корректный email'),
  password: z.string().min(MIN_PASSWORD_LENGTH, `Пароль должен содержать не менее ${MIN_PASSWORD_LENGTH} символов`),
})

const ownPasswordSchema = z.object({
  currentPassword: z.string().min(1, 'Укажите текущий пароль'),
  password: z.string().min(MIN_PASSWORD_LENGTH, `Пароль должен содержать не менее ${MIN_PASSWORD_LENGTH} символов`),
})

const resetPasswordSchema = z.object({
  password: z.string().min(MIN_PASSWORD_LENGTH, `Пароль должен содержать не менее ${MIN_PASSWORD_LENGTH} символов`),
})

type CreateUserFormData = z.infer<typeof createUserSchema>
type OwnPasswordFormData = z.infer<typeof ownPasswordSchema>
type ResetPasswordFormData = z.infer<typeof resetPasswordSchema>

function roleLabel(role: string): string {
  return ROLE_LABELS[role] ?? role
}

function errorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError && typeof error.payload === 'object' && error.payload !== null) {
    const payload = error.payload as { error?: unknown, details?: Array<{ message?: unknown }> }
    const detail = payload.details?.find((item) => typeof item.message === 'string')?.message
    if (typeof detail === 'string') {
      return detail
    }
    if (error.status === 409 || error.status === 403) {
      return typeof payload.error === 'string' ? payload.error : fallback
    }
  }

  return fallback
}

export default function UsersPage() {
  const currentEmail = useAuthStore((state) => state.userEmail).toLowerCase()
  const usersQuery = useAdminUsersQuery()
  const createMutation = useCreateUserMutation()
  const updateRolesMutation = useUpdateUserRolesMutation()
  const setActiveMutation = useSetUserActiveMutation()
  const resetPasswordMutation = useResetUserPasswordMutation()
  const deleteMutation = useDeleteUserMutation()
  const ownPasswordMutation = useChangeOwnPasswordMutation()
  const { push } = useToast()

  const [newRole, setNewRole] = useState('ROLE_EDITOR')
  const [resetTarget, setResetTarget] = useState<AdminUserItem | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<AdminUserItem | null>(null)

  const createForm = useForm<CreateUserFormData>({
    resolver: zodResolver(createUserSchema),
    defaultValues: { email: '', password: '' },
  })
  const ownPasswordForm = useForm<OwnPasswordFormData>({
    resolver: zodResolver(ownPasswordSchema),
    defaultValues: { currentPassword: '', password: '' },
  })
  const resetForm = useForm<ResetPasswordFormData>({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: { password: '' },
  })

  const submitCreate = createForm.handleSubmit(async (values) => {
    try {
      await createMutation.mutateAsync({ email: values.email, password: values.password, roles: [newRole] })
      createForm.reset()
      push({ title: 'Пользователь создан', description: `${values.email}: ${roleLabel(newRole)}.` })
    } catch (error) {
      applyServerValidationErrors(error, createForm.setError)
      push({ title: 'Не удалось создать пользователя', description: errorMessage(error, 'Проверьте данные и права users.manage.') })
    }
  })

  const submitOwnPassword = ownPasswordForm.handleSubmit(async (values) => {
    try {
      await ownPasswordMutation.mutateAsync(values)
      ownPasswordForm.reset()
      push({ title: 'Пароль изменён', description: 'Все сессии, включая текущую, потребуют повторного входа.' })
    } catch (error) {
      applyServerValidationErrors(error, ownPasswordForm.setError)
      push({ title: 'Не удалось сменить пароль', description: errorMessage(error, 'Проверьте введённые данные.') })
    }
  })

  const submitReset = resetForm.handleSubmit(async (values) => {
    if (resetTarget === null) {
      return
    }

    try {
      await resetPasswordMutation.mutateAsync({ userId: resetTarget.id, password: values.password })
      push({ title: 'Пароль сброшен', description: `Новый пароль задан для ${resetTarget.email}.` })
      resetForm.reset()
      setResetTarget(null)
    } catch (error) {
      applyServerValidationErrors(error, resetForm.setError)
      push({ title: 'Не удалось сбросить пароль', description: errorMessage(error, 'Проверьте введённые данные.') })
    }
  })

  const changeRole = async (user: AdminUserItem, role: string) => {
    try {
      await updateRolesMutation.mutateAsync({ userId: user.id, roles: [role] })
      push({ title: 'Роль обновлена', description: `${user.email}: ${roleLabel(role)}.` })
    } catch (error) {
      push({ title: 'Не удалось изменить роль', description: errorMessage(error, 'Проверьте права users.manage.') })
    }
  }

  const toggleActive = async (user: AdminUserItem) => {
    try {
      await setActiveMutation.mutateAsync({ userId: user.id, active: !user.active })
      push({ title: user.active ? 'Пользователь деактивирован' : 'Пользователь активирован', description: user.email })
    } catch (error) {
      push({ title: 'Не удалось изменить статус', description: errorMessage(error, 'Проверьте права users.manage.') })
    }
  }

  const confirmDelete = async () => {
    if (deleteTarget === null) {
      return
    }

    try {
      await deleteMutation.mutateAsync(deleteTarget.id)
      push({ title: 'Пользователь удалён', description: deleteTarget.email })
    } catch (error) {
      push({ title: 'Не удалось удалить пользователя', description: errorMessage(error, 'Проверьте права users.manage.') })
    } finally {
      setDeleteTarget(null)
    }
  }

  const columnHelper = createColumnHelper<AdminUserItem>()
  const availableRoles = usersQuery.data?.availableRoles ?? []
  const roleOptions = availableRoles.map((role) => ({ value: role, label: roleLabel(role) }))
  const columns = [
    columnHelper.accessor('email', { header: 'Email' }),
    columnHelper.accessor('roles', {
      header: 'Роль',
      cell: ({ row }) => {
        const user = row.original
        return (
          <Select
            value={user.roles[0] ?? 'ROLE_EDITOR'}
            onValueChange={(role) => void changeRole(user, role)}
            options={roleOptions}
          />
        )
      },
    }),
    columnHelper.accessor('active', {
      header: 'Активен',
      cell: ({ getValue }) => (getValue() ? 'Да' : 'Нет'),
    }),
    columnHelper.accessor('updatedAt', { header: 'Обновлён' }),
    columnHelper.display({
      id: 'actions',
      header: 'Действия',
      cell: ({ row }) => {
        const user = row.original
        const isSelf = user.email.toLowerCase() === currentEmail
        return (
          <div className="flex flex-wrap gap-2">
            <Button type="button" size="sm" variant="outline" onClick={() => setResetTarget(user)}>Сбросить пароль</Button>
            <Button type="button" size="sm" variant="outline" disabled={isSelf && user.active} onClick={() => void toggleActive(user)}>
              {user.active ? 'Деактивировать' : 'Активировать'}
            </Button>
            <Button type="button" size="sm" variant="danger" disabled={isSelf} onClick={() => setDeleteTarget(user)}>Удалить</Button>
          </div>
        )
      },
    }),
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
      <PageHeader title="Пользователи и роли" description="Создание пользователей, роли, пароли и деактивация. Все изменения пишутся в журнал аудита." />
      <Card title="Новый пользователь">
        <form className="grid gap-3 lg:grid-cols-2" onSubmit={submitCreate}>
          <div>
            <Input placeholder="Email пользователя" autoComplete="off" {...createForm.register('email')} />
            {createForm.formState.errors.email?.message !== undefined ? (
              <p className="mt-1 text-xs text-red-600">{createForm.formState.errors.email.message}</p>
            ) : null}
          </div>
          <div>
            <Input type="password" placeholder={`Пароль (от ${MIN_PASSWORD_LENGTH} символов)`} autoComplete="new-password" {...createForm.register('password')} />
            {createForm.formState.errors.password?.message !== undefined ? (
              <p className="mt-1 text-xs text-red-600">{createForm.formState.errors.password.message}</p>
            ) : null}
          </div>
          <Select value={newRole} onValueChange={setNewRole} options={roleOptions} />
          <div className="lg:col-span-2">
            <Button type="submit" disabled={createMutation.isPending}>
              {createMutation.isPending ? 'Создание...' : 'Создать пользователя'}
            </Button>
          </div>
        </form>
      </Card>
      <Card className="mt-4" title="Пользователи">
        <DataTable columns={columns} data={usersQuery.data?.users ?? []} />
      </Card>
      <Card className="mt-4" title="Сменить свой пароль">
        <form className="grid gap-3 lg:grid-cols-2" onSubmit={submitOwnPassword}>
          <div>
            <Input type="password" placeholder="Текущий пароль" autoComplete="current-password" {...ownPasswordForm.register('currentPassword')} />
            {ownPasswordForm.formState.errors.currentPassword?.message !== undefined ? (
              <p className="mt-1 text-xs text-red-600">{ownPasswordForm.formState.errors.currentPassword.message}</p>
            ) : null}
          </div>
          <div>
            <Input type="password" placeholder="Новый пароль" autoComplete="new-password" {...ownPasswordForm.register('password')} />
            {ownPasswordForm.formState.errors.password?.message !== undefined ? (
              <p className="mt-1 text-xs text-red-600">{ownPasswordForm.formState.errors.password.message}</p>
            ) : null}
          </div>
          <div className="lg:col-span-2">
            <Button type="submit" disabled={ownPasswordMutation.isPending}>
              {ownPasswordMutation.isPending ? 'Сохранение...' : 'Сменить пароль'}
            </Button>
          </div>
        </form>
      </Card>
      <Dialog
        open={resetTarget !== null}
        onOpenChange={(open) => {
          if (!open) {
            setResetTarget(null)
            resetForm.reset()
          }
        }}
        title="Сброс пароля"
        description={resetTarget === null ? undefined : `Новый пароль для ${resetTarget.email}. Действующие сессии пользователя будут завершены.`}
      >
        <form className="grid gap-3" onSubmit={submitReset}>
          <div>
            <Input type="password" placeholder="Новый пароль" autoComplete="new-password" {...resetForm.register('password')} />
            {resetForm.formState.errors.password?.message !== undefined ? (
              <p className="mt-1 text-xs text-red-600">{resetForm.formState.errors.password.message}</p>
            ) : null}
          </div>
          <div className="flex justify-end gap-2">
            <Button type="button" variant="ghost" onClick={() => setResetTarget(null)}>Отмена</Button>
            <Button type="submit" disabled={resetPasswordMutation.isPending}>Сбросить пароль</Button>
          </div>
        </form>
      </Dialog>
      <ConfirmDialog
        open={deleteTarget !== null}
        title="Удалить пользователя?"
        description={deleteTarget === null ? undefined : `${deleteTarget.email} потеряет доступ без возможности восстановления. Для временного отключения используйте деактивацию.`}
        confirmLabel="Удалить"
        onConfirm={() => void confirmDelete()}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  )
}
