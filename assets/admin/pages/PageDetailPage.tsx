import { lazy, Suspense, useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { PageHeader, Card, Button, Dialog, ErrorState, Input, Select, PageLoadingState, Switch } from '../shared/ui'
import { usePageDetailQuery, useUpdatePageMutation } from '../entities/page/api'
import { useToast } from '../app/providers/toast-provider'
import { applyServerValidationErrors } from '../shared/api/validation'
import { preloadBuilderOnIntent } from '../routes/prefetch'

const RichTextEditor = lazy(async () => import('../features/rich-text/RichTextEditor').then((module) => ({ default: module.RichTextEditor })))

const pageSchema = z.object({
  type: z.string().min(1, 'Укажите тип страницы'),
  title: z.string().min(2, 'Название слишком короткое'),
  slug: z.string().min(1, 'Укажите slug'),
  path: z.string().min(1, 'Укажите URL path'),
  h1: z.string().min(1, 'Укажите H1'),
  template: z.string().min(1, 'Укажите template'),
  sortOrder: z.number(),
  isIndexable: z.boolean(),
  parentId: z.string().optional(),
  visibility: z.enum(['public', 'hidden', 'unlisted']),
})

type PageFormData = z.infer<typeof pageSchema>

export default function PageDetailPage() {
  const { id = 'unknown' } = useParams()
  const { push } = useToast()
  const [isBuilderOpen, setBuilderOpen] = useState(false)
  const pageQuery = usePageDetailQuery(id)
  const updateMutation = useUpdatePageMutation(id)
  const form = useForm<PageFormData>({
    resolver: zodResolver(pageSchema),
    defaultValues: {
      type: 'landing',
      title: '',
      slug: '',
      path: '',
      h1: '',
      template: 'default',
      sortOrder: 0,
      isIndexable: true,
      parentId: '',
      visibility: 'public',
    },
  })

  useEffect(() => {
    if (!pageQuery.data) {
      return
    }

    form.reset({
      type: pageQuery.data.type,
      title: pageQuery.data.title,
      slug: pageQuery.data.slug,
      path: pageQuery.data.path,
      h1: pageQuery.data.h1,
      template: pageQuery.data.template,
      sortOrder: pageQuery.data.sortOrder,
      isIndexable: pageQuery.data.isIndexable,
      parentId: '',
      visibility: pageQuery.data.visibility,
    })
  }, [form, pageQuery.data])

  const submit = form.handleSubmit(async (values: PageFormData) => {
    try {
      await updateMutation.mutateAsync({
        ...values,
        parentId: values.parentId === '' ? null : values.parentId ?? null,
      })

      push({
        title: 'Страница сохранена',
        description: 'Основные поля страницы обновлены.',
      })
      await pageQuery.refetch()
    } catch (error) {
      applyServerValidationErrors(error, form.setError)
      push({
        title: 'Ошибка сохранения',
        description: 'Проверьте обязательные поля и права доступа.',
      })
    }
  })

  if (pageQuery.isPending) {
    return <PageLoadingState />
  }

  if (pageQuery.isError || pageQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить страницу"
        description="Проверьте endpoint /admin/api/content/pages/{id} и доступы."
      />
    )
  }

  const builderPath = `/admin/pages/${id}/builder`

  return (
    <div>
      <PageHeader
        title={`Page: ${id}`}
        description="Foundation-экран редактирования страницы. Layout и блоки будут редактироваться отдельно в Builder."
        actions={(
          <button
            type="button"
            className="inline-flex h-10 items-center rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white hover:bg-emerald-700"
            onMouseEnter={preloadBuilderOnIntent}
            onFocus={preloadBuilderOnIntent}
            onClick={() => setBuilderOpen(true)}
          >
            Открыть Builder
          </button>
        )}
      />
      <Dialog
        open={isBuilderOpen}
        onOpenChange={setBuilderOpen}
        title={`Builder: ${id}`}
        description="Builder открывается в pop-up и закрывается только кнопкой."
        contentClassName="max-w-[96vw]"
        closeOnInteractOutside={false}
        closeOnEscape={false}
      >
        <div className="space-y-3">
          <div className="h-[78vh] overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
            <iframe
              title={`page-builder-${id}`}
              src={builderPath}
              className="h-full w-full bg-white"
            />
          </div>
          <div className="flex items-center justify-end gap-2">
            <a
              href={builderPath}
              target="_blank"
              rel="noreferrer"
              className="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
            >
              Открыть в новой вкладке
            </a>
            <Button type="button" onClick={() => setBuilderOpen(false)}>
              Закрыть
            </Button>
          </div>
        </div>
      </Dialog>
      <Card title="Основные поля страницы">
        <form className="grid gap-3 md:grid-cols-2" onSubmit={submit}>
          <Input placeholder="type" {...form.register('type')} />
          <Input placeholder="title" {...form.register('title')} />
          <Input placeholder="slug" {...form.register('slug')} />
          <Input placeholder="path" {...form.register('path')} />
          <Input placeholder="h1" {...form.register('h1')} />
          <Input placeholder="template" {...form.register('template')} />
          <Input
            type="number"
            placeholder="sortOrder"
            {...form.register('sortOrder', {
              setValueAs: (value) => Number(value),
            })}
          />
          <Input placeholder="parentId" {...form.register('parentId')} />
          <div className="flex items-center gap-2">
            <Switch checked={form.watch('isIndexable')} onCheckedChange={(value) => form.setValue('isIndexable', value)} />
            <span className="text-sm text-slate-600 dark:text-slate-300">Indexable</span>
          </div>
          <div className="md:col-span-2">
            <Select
              value={form.watch('visibility')}
              onValueChange={(value) => form.setValue('visibility', value as PageFormData['visibility'])}
              options={[
                { value: 'public', label: 'public' },
                { value: 'hidden', label: 'hidden' },
                { value: 'unlisted', label: 'unlisted' },
              ]}
            />
          </div>
          <div className="md:col-span-2">
            <Button type="submit" disabled={updateMutation.isPending}>
              {updateMutation.isPending ? 'Сохранение...' : 'Сохранить страницу'}
            </Button>
          </div>
        </form>
      </Card>
      <Card className="mt-4" title="Rich Text / Tiptap bridge">
        <Suspense fallback={<PageLoadingState />}>
          <RichTextEditor initialValue="<p>Редактирование rich-text контента</p>" />
        </Suspense>
      </Card>
    </div>
  )
}
