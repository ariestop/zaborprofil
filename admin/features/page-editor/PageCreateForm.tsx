import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { useToast } from '../../app/providers/toast-provider'
import { useCreatePageMutation, usePageTemplatesQuery, usePagesQuery } from '../../entities/page/api'
import { applyServerValidationErrors } from '../../shared/api/validation'
import { Button, Card, Input } from '../../shared/ui'
import { describeApiError } from '../seo/redirects/redirect-rules'
import { Field, NativeSelect } from './fields'
import { emptyFormValues, pageEditorSchema, pathFromSlug, slugify, toPagePayload, type PageEditorFormValues } from './form'
import { LeaveGuard, useLeaveBypass } from './LeaveGuard'
import { pageTypeLabels, templateLabels, visibilityLabels } from './page-status'
import { buildParentOptions } from './parent-options'
import { TemplatePreview } from './TemplatePreview'

const createSchema = pageEditorSchema.pick({
  type: true,
  title: true,
  h1: true,
  slug: true,
  path: true,
  template: true,
  parentId: true,
  visibility: true,
})

type CreateFormValues = Pick<PageEditorFormValues, 'type' | 'title' | 'h1' | 'slug' | 'path' | 'template' | 'parentId' | 'visibility'>

const defaults = emptyFormValues()

export function PageCreateForm() {
  const navigate = useNavigate()
  const { push } = useToast()
  const createMutation = useCreatePageMutation()
  const templatesQuery = usePageTemplatesQuery()
  const pagesQuery = usePagesQuery()
  const { isBypassed: isLeaveAllowed, bypass: allowLeave } = useLeaveBypass()

  const form = useForm<CreateFormValues>({
    resolver: zodResolver(createSchema),
    defaultValues: {
      type: defaults.type,
      title: '',
      h1: '',
      slug: '',
      path: '',
      template: defaults.template,
      parentId: '',
      visibility: defaults.visibility,
    },
  })
  const { register, setValue, control, formState: { errors, dirtyFields, isDirty } } = form

  const type = useWatch({ control, name: 'type' })
  const template = useWatch({ control, name: 'template' })
  const parentId = useWatch({ control, name: 'parentId' })

  const templateOptions = useMemo(() => {
    const codes = (templatesQuery.data ?? [])
      .filter((item) => item.isActive && item.pageType === type)
      .map((item) => ({ code: item.code, label: templateLabels[item.code] ?? item.name }))
    return [...codes, { code: 'default', label: templateLabels.default ?? 'Без шаблона' }]
  }, [templatesQuery.data, type])

  const selectedTemplate = useMemo(
    () => (templatesQuery.data ?? []).find((item) => item.isActive && item.pageType === type && item.code === template),
    [templatesQuery.data, template, type],
  )

  const parentOptions = useMemo(() => buildParentOptions(pagesQuery.data ?? [], null, parentId), [pagesQuery.data, parentId])
  const parentPath = (pagesQuery.data ?? []).find((item) => item.id === parentId)?.path ?? ''

  // Автозаполнение не помечает поля как изменённые пользователем (без shouldDirty),
  // поэтому dirtyFields отражает только ручной ввод.
  const syncDerivedFields = (nextTitle: string, nextSlug: string, nextParentPath: string): void => {
    if (dirtyFields.h1 !== true) {
      setValue('h1', nextTitle)
    }

    if (dirtyFields.slug !== true) {
      setValue('slug', slugify(nextTitle))
      nextSlug = slugify(nextTitle)
    }

    if (dirtyFields.path !== true) {
      setValue('path', pathFromSlug(nextSlug, nextParentPath))
    }
  }

  const titleField = register('title')
  const slugField = register('slug')
  const parentField = register('parentId')
  const typeField = register('type')

  const submit = async (values: CreateFormValues): Promise<void> => {
    try {
      const starterTemplate = values.template !== 'default' && selectedTemplate !== undefined ? values.template : undefined
      const created = await createMutation.mutateAsync({ ...toPagePayload({ ...emptyFormValues(), ...values }), starterTemplate })

      allowLeave()
      push({ title: 'Страница создана', description: 'Добавьте контент и SEO-данные.' })
      void navigate(`/admin/pages/${created.id}`)
    } catch (error) {
      applyServerValidationErrors(error, form.setError)
      push({ title: 'Не удалось создать страницу', description: describeApiError(error, 'Проверьте обязательные поля и права доступа.') })
    }
  }

  return (
    <form onSubmit={form.handleSubmit(submit)} noValidate>
      <LeaveGuard when={isDirty && !createMutation.isPending} isAllowed={isLeaveAllowed} />
      <Card title="Новая страница" description="Страница создаётся черновиком. Контент, SEO и публикация — в редакторе после создания.">
        <div className="grid gap-4 md:grid-cols-2">
          <Field label="Название страницы" error={errors.title?.message}>
            {(id, describedBy) => (
              <Input
                id={id}
                aria-describedby={describedBy}
                {...titleField}
                onChange={(event) => {
                  void titleField.onChange(event)
                  syncDerivedFields(event.target.value, form.getValues('slug'), parentPath)
                }}
              />
            )}
          </Field>
          <Field label="Заголовок H1" error={errors.h1?.message} hint="По умолчанию совпадает с названием.">
            {(id, describedBy) => <Input id={id} aria-describedby={describedBy} {...register('h1')} />}
          </Field>
          <Field label="Тип страницы" error={errors.type?.message}>
            {(id) => (
              <NativeSelect
                id={id}
                {...typeField}
                onChange={(event) => {
                  void typeField.onChange(event)
                  const first = (templatesQuery.data ?? []).find((item) => item.isActive && item.pageType === event.target.value)
                  setValue('template', first?.code ?? 'default')
                }}
              >
                {Object.entries(pageTypeLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
              </NativeSelect>
            )}
          </Field>
          <Field label="Шаблон" error={errors.template?.message} hint="Шаблон создаёт стартовый набор блоков с подсказками, что заполнить.">
            {(id) => (
              <NativeSelect id={id} value={template} onChange={(event) => setValue('template', event.target.value, { shouldDirty: true })}>
                {templateOptions.map((item) => <option key={item.code} value={item.code}>{item.label}</option>)}
              </NativeSelect>
            )}
          </Field>
          <Field label="Slug" error={errors.slug?.message} hint="Формируется из названия, можно изменить вручную.">
            {(id, describedBy) => (
              <Input
                id={id}
                aria-describedby={describedBy}
                {...slugField}
                onChange={(event) => {
                  void slugField.onChange(event)
                  if (dirtyFields.path !== true) {
                    setValue('path', pathFromSlug(event.target.value, parentPath))
                  }
                }}
              />
            )}
          </Field>
          <Field label="Адрес страницы (path)" error={errors.path?.message} hint="Публичный URL, например /uslugi/zabory/.">
            {(id, describedBy) => <Input id={id} aria-describedby={describedBy} {...register('path')} />}
          </Field>
          <Field label="Родительская страница" error={errors.parentId?.message}>
            {(id) => (
              <NativeSelect
                id={id}
                {...parentField}
                onChange={(event) => {
                  void parentField.onChange(event)
                  const nextParentPath = (pagesQuery.data ?? []).find((item) => item.id === event.target.value)?.path ?? ''
                  syncDerivedFields(form.getValues('title'), form.getValues('slug'), nextParentPath)
                }}
              >
                <option value="">— Без родителя —</option>
                {parentOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
              </NativeSelect>
            )}
          </Field>
          <Field label="Видимость" error={errors.visibility?.message}>
            {(id) => (
              <NativeSelect id={id} {...register('visibility')}>
                {Object.entries(visibilityLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
              </NativeSelect>
            )}
          </Field>
        </div>
        {selectedTemplate !== undefined ? <div className="mt-4"><TemplatePreview template={selectedTemplate} /></div> : null}
        <div className="mt-6 flex flex-wrap gap-2">
          <Button type="submit" disabled={createMutation.isPending}>
            {createMutation.isPending ? 'Создание…' : 'Создать страницу'}
          </Button>
          <Button type="button" variant="ghost" onClick={() => void navigate('/admin/pages')}>Отмена</Button>
        </div>
      </Card>
    </form>
  )
}
