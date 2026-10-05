import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useWatch } from 'react-hook-form'
import { useToast } from '../../app/providers/toast-provider'
import { useChangePageStatusMutation, usePageTemplatesQuery, usePagesQuery, type PageStatus } from '../../entities/page/api'
import { Badge, Button, Card, ConfirmDialog, Input, Switch } from '../../shared/ui'
import type { ContentPageDetail } from '../../types/api'
import { describeApiError } from '../seo/redirects/redirect-rules'
import { Field, NativeSelect } from './fields'
import { pathFromSlug, slugify } from './form'
import { buildParentOptions } from './parent-options'
import { pageStatusLabels, pageTypeLabels, quickStatusActions, statusTone, templateLabels, visibilityLabels } from './page-status'
import type { PageEditorController } from './usePageEditorController'

interface SettingsTabProps {
  controller: PageEditorController
  page: ContentPageDetail
}

const statusActionLabels: Partial<Record<PageStatus, string>> = {
  draft: 'Вернуть в черновик',
  unpublished: 'Снять с публикации',
  archived: 'Отправить в архив',
}

export function SettingsTab({ controller, page }: SettingsTabProps) {
  const { form, savedValues } = controller
  const { register, setValue, control, formState: { errors } } = form
  const navigate = useNavigate()
  const { push } = useToast()
  const templatesQuery = usePageTemplatesQuery()
  const pagesQuery = usePagesQuery()
  const statusMutation = useChangePageStatusMutation(page.id)
  const [deleteOpen, setDeleteOpen] = useState(false)

  const type = useWatch({ control, name: 'type' })
  const path = useWatch({ control, name: 'path' })
  const slug = useWatch({ control, name: 'slug' })
  const parentId = useWatch({ control, name: 'parentId' })
  const template = useWatch({ control, name: 'template' })
  const isIndexable = useWatch({ control, name: 'isIndexable' })

  const typeOptions = useMemo(() => {
    const known = Object.keys(pageTypeLabels)
    return known.includes(type) ? known : [...known, type]
  }, [type])

  const templateOptions = useMemo(() => {
    const codes = (templatesQuery.data ?? [])
      .filter((item) => item.isActive && item.pageType === type)
      .map((item) => ({ code: item.code, label: templateLabels[item.code] ?? item.name }))
    const withDefault = [...codes, { code: 'default', label: templateLabels.default ?? 'Без шаблона' }]
    return withDefault.some((item) => item.code === template) ? withDefault : [...withDefault, { code: template, label: template }]
  }, [template, templatesQuery.data, type])

  const parentOptions = useMemo(
    () => buildParentOptions(pagesQuery.data ?? [], page.id, parentId),
    [page.id, pagesQuery.data, parentId],
  )

  const parentPath = (pagesQuery.data ?? []).find((item) => item.id === parentId)?.path ?? ''
  const pathChangedOnPublished = page.status === 'published' && path !== savedValues.path

  const changeStatus = async (status: PageStatus): Promise<void> => {
    try {
      await statusMutation.mutateAsync(status)
      push({ title: 'Статус изменён', description: pageStatusLabels[status] })
    } catch (error) {
      push({ title: 'Не удалось изменить статус', description: describeApiError(error, 'Проверьте права и допустимые переходы.') })
    }
  }

  const deletePage = async (): Promise<void> => {
    setDeleteOpen(false)
    try {
      await statusMutation.mutateAsync('deleted')
      controller.disableLeaveGuard()
      push({ title: 'Страница удалена' })
      navigate('/admin/pages')
    } catch (error) {
      push({ title: 'Не удалось удалить страницу', description: describeApiError(error, 'Проверьте права pages.delete.') })
    }
  }

  const statusActions = quickStatusActions(page.status)

  return (
    <div className="grid gap-4">
      <Card title="Адрес и шаблон" description="Эти поля сохраняются кнопкой «Сохранить» или Ctrl/Cmd+S — автосохранение их не затрагивает.">
        <div className="grid gap-4 md:grid-cols-2">
          <Field label="Название страницы" error={errors.title?.message}>
            {(id, describedBy) => <Input id={id} aria-describedby={describedBy} {...register('title')} />}
          </Field>
          <Field label="Заголовок H1" error={errors.h1?.message} hint="Единственный H1 на странице.">
            {(id, describedBy) => <Input id={id} aria-describedby={describedBy} {...register('h1')} />}
          </Field>
          <Field label="Тип страницы" error={errors.type?.message}>
            {(id) => (
              <NativeSelect id={id} {...register('type')}>
                {typeOptions.map((value) => <option key={value} value={value}>{pageTypeLabels[value] ?? value}</option>)}
              </NativeSelect>
            )}
          </Field>
          <Field label="Шаблон" error={errors.template?.message} hint="Шаблоны подбираются по типу страницы.">
            {(id) => (
              <NativeSelect id={id} {...register('template')}>
                {templateOptions.map((item) => <option key={item.code} value={item.code}>{item.label}</option>)}
              </NativeSelect>
            )}
          </Field>
          <Field label="Slug" error={errors.slug?.message} hint="Последняя часть адреса: латиница, цифры и дефисы.">
            {(id, describedBy) => <Input id={id} aria-describedby={describedBy} {...register('slug')} />}
          </Field>
          <Field label="Адрес страницы (path)" error={errors.path?.message} hint="Публичный URL, начинается и заканчивается «/».">
            {(id, describedBy) => (
              <div className="flex gap-2">
                <Input id={id} aria-describedby={describedBy} {...register('path')} />
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setValue('path', pathFromSlug(slugify(slug), parentPath), { shouldDirty: true, shouldValidate: true })}
                >
                  Из slug
                </Button>
              </div>
            )}
          </Field>
          <Field label="Родительская страница" error={errors.parentId?.message} hint="Хранится в структуре сайта; не меняется при сохранении остальных полей.">
            {(id) => (
              <NativeSelect id={id} {...register('parentId')}>
                <option value="">— Без родителя —</option>
                {parentOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
              </NativeSelect>
            )}
          </Field>
          <Field label="Порядок сортировки" error={errors.sortOrder?.message}>
            {(id, describedBy) => <Input id={id} type="number" aria-describedby={describedBy} {...register('sortOrder', { valueAsNumber: true })} />}
          </Field>
        </div>
        {pathChangedOnPublished ? (
          <p role="alert" className="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
            Страница опубликована: после сохранения старый адрес {savedValues.path} перестанет открываться.
            Настройте редирект в разделе SEO → «Редиректы».
          </p>
        ) : null}
      </Card>

      <Card title="Видимость и индексация">
        <div className="grid gap-4 md:grid-cols-2">
          <Field label="Видимость" error={errors.visibility?.message}>
            {(id) => (
              <NativeSelect id={id} {...register('visibility')}>
                {Object.entries(visibilityLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
              </NativeSelect>
            )}
          </Field>
          <div className="flex items-center gap-3 self-end pb-2">
            <Switch checked={isIndexable} onCheckedChange={(value) => setValue('isIndexable', value, { shouldDirty: true })} />
            <span className="text-sm text-ink dark:text-slate-200" id="indexable-label">
              Разрешить индексацию поисковиками ({isIndexable ? 'index' : 'noindex'})
            </span>
          </div>
        </div>
      </Card>

      <Card title="Статус страницы">
        <div className="flex flex-wrap items-center gap-3">
          <Badge tone={statusTone(page.status)}>{pageStatusLabels[page.status]}</Badge>
          {statusActions.map((status) => (
            <Button
              key={status}
              type="button"
              variant="outline"
              size="sm"
              disabled={statusMutation.isPending}
              onClick={() => void changeStatus(status)}
            >
              {statusActionLabels[status] ?? pageStatusLabels[status]}
            </Button>
          ))}
        </div>
        <p className="mt-2 text-xs text-graphite dark:text-slate-400">
          Публикация доступна в шапке редактора: сначала сохраняются изменения, затем выполняется SEO-аудит.
        </p>
      </Card>

      <Card title="Опасная зона">
        <p className="text-sm text-graphite dark:text-slate-300">Удалённая страница перестаёт отображаться на сайте и в списке страниц.</p>
        <Button type="button" variant="danger" className="mt-3" disabled={statusMutation.isPending || page.status === 'deleted'} onClick={() => setDeleteOpen(true)}>
          Удалить страницу
        </Button>
      </Card>

      <ConfirmDialog
        open={deleteOpen}
        title={`Удалить страницу «${page.title}»?`}
        description="Несохранённые изменения будут потеряны."
        confirmLabel="Удалить"
        onCancel={() => setDeleteOpen(false)}
        onConfirm={() => void deletePage()}
      />
    </div>
  )
}
