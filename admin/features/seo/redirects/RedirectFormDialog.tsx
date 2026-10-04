import { useEffect } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Dialog, Input, Select, Switch } from '../../../shared/ui'
import { useToast } from '../../../app/providers/toast-provider'
import { applyServerValidationErrors } from '../../../shared/api/validation'
import { useCreateRedirectMutation, useUpdateRedirectMutation } from '../../../entities/seo/api'
import type { RedirectItem } from '../../../types/api'
import {
  describeApiError,
  decodeForDisplay,
  REDIRECT_STATUS_CODES,
  REDIRECT_STATUS_LABELS,
  redirectFormSchema,
  type RedirectFormValues,
} from './redirect-rules'

export interface RedirectDraft {
  sourcePath: string
  targetPath?: string
}

interface RedirectFormDialogProps {
  open: boolean
  redirect: RedirectItem | null
  draft?: RedirectDraft | null
  onClose: () => void
}

const statusOptions = REDIRECT_STATUS_CODES.map((code) => ({ value: String(code), label: REDIRECT_STATUS_LABELS[code] ?? String(code) }))

function defaultValues(redirect: RedirectItem | null, draft: RedirectDraft | null | undefined): RedirectFormValues {
  if (redirect !== null) {
    return {
      sourcePath: decodeForDisplay(redirect.sourcePath),
      targetPath: decodeForDisplay(redirect.targetPath),
      statusCode: String(redirect.statusCode),
      isActive: redirect.isActive,
    }
  }

  return {
    sourcePath: draft === null || draft === undefined ? '' : decodeForDisplay(draft.sourcePath),
    targetPath: draft?.targetPath ?? '',
    statusCode: '301',
    isActive: true,
  }
}

export function RedirectFormDialog({ open, redirect, draft, onClose }: RedirectFormDialogProps) {
  const { push } = useToast()
  const createMutation = useCreateRedirectMutation()
  const updateMutation = useUpdateRedirectMutation()
  const form = useForm<RedirectFormValues>({
    resolver: zodResolver(redirectFormSchema),
    defaultValues: defaultValues(redirect, draft),
  })

  useEffect(() => {
    if (open) {
      form.reset(defaultValues(redirect, draft))
    }
  }, [open, redirect, draft, form])

  const isEditing = redirect !== null
  const pending = createMutation.isPending || updateMutation.isPending

  const submit = form.handleSubmit(async (values) => {
    const payload = {
      sourcePath: values.sourcePath,
      targetPath: values.targetPath,
      statusCode: Number(values.statusCode),
      isActive: values.isActive,
    }

    try {
      const saved = isEditing
        ? await updateMutation.mutateAsync({ id: redirect.id, payload })
        : await createMutation.mutateAsync(payload)
      const warnings = saved.warnings.map((warning) => warning.message).join(' ')
      push({
        title: isEditing ? 'Редирект обновлён' : 'Редирект создан',
        description: warnings === '' ? undefined : warnings,
      })
      onClose()
    } catch (error) {
      applyServerValidationErrors(error, form.setError)
      push({ title: 'Не удалось сохранить редирект', description: describeApiError(error, 'Проверьте поля формы.') })
    }
  })

  const errors = form.formState.errors

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          onClose()
        }
      }}
      title={isEditing ? 'Редактирование редиректа' : 'Новый редирект'}
      description="Старый адрес указывается путём без домена. Кириллица сохраняется в закодированном виде, как её присылает браузер."
      contentClassName="max-w-xl"
    >
      <form className="grid gap-3" onSubmit={submit} noValidate>
        <div>
          <label className="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300" htmlFor="redirect-source">Старый URL</label>
          <Input id="redirect-source" placeholder="/old-page/" autoComplete="off" {...form.register('sourcePath')} />
          {errors.sourcePath?.message !== undefined ? <p className="mt-1 text-xs text-red-600">{errors.sourcePath.message}</p> : null}
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300" htmlFor="redirect-target">Новый URL</label>
          <Input id="redirect-target" placeholder="/new-page/ или https://example.com/page/" autoComplete="off" {...form.register('targetPath')} />
          {errors.targetPath?.message !== undefined ? <p className="mt-1 text-xs text-red-600">{errors.targetPath.message}</p> : null}
        </div>
        <div className="flex flex-wrap items-center gap-4">
          <Controller
            control={form.control}
            name="statusCode"
            render={({ field }) => (
              <Select value={field.value} onValueChange={field.onChange} options={statusOptions} />
            )}
          />
          <Controller
            control={form.control}
            name="isActive"
            render={({ field }) => (
              <label className="inline-flex items-center gap-2 text-sm">
                <Switch checked={field.value} onCheckedChange={field.onChange} />
                <span>{field.value ? 'Активен' : 'Отключён'}</span>
              </label>
            )}
          />
        </div>
        {errors.statusCode?.message !== undefined ? <p className="text-xs text-red-600">{errors.statusCode.message}</p> : null}
        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>Отмена</Button>
          <Button type="submit" disabled={pending}>{pending ? 'Сохранение...' : 'Сохранить'}</Button>
        </div>
      </form>
    </Dialog>
  )
}
