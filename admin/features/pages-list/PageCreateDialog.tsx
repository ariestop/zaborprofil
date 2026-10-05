import { zodResolver } from '@hookform/resolvers/zod'
import * as DialogPrimitive from '@radix-ui/react-dialog'
import { useMemo, useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { useToast } from '../../app/providers/toast-provider'
import { useCreatePageMutation, usePageTemplatesQuery, usePagesQuery } from '../../entities/page/api'
import { NavIcon } from '../../layouts/nav-icons'
import { applyServerValidationErrors } from '../../shared/api/validation'
import { cn } from '../../shared/lib/cn'
import type { PageTemplateItem } from '../../types/api'
import { Field, NativeSelect } from '../page-editor/fields'
import { emptyFormValues, pageEditorSchema, pathFromSlug, slugify, toPagePayload } from '../page-editor/form'
import { pageTypeLabels } from '../page-editor/page-status'
import { buildParentOptions } from '../page-editor/parent-options'
import { describeApiError } from '../seo/redirects/redirect-rules'
import { blocksCount, templateBars, templateBlockNames } from './list-model'

const EMPTY = 'empty'

const createSchema = pageEditorSchema.pick({ type: true, title: true, slug: true, path: true, parentId: true })

interface CreateValues {
  type: string
  title: string
  slug: string
  path: string
  parentId: string
}

const inputClass = 'h-[42px] w-full rounded-[10px] border border-[#D0D5DD] bg-white px-3 text-[15px] text-[#101828] outline-hidden focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/30 aria-[invalid=true]:border-red-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100'

function TemplateThumb({ template }: { template: PageTemplateItem | null }) {
  const bars = template === null ? [] : templateBars(template)

  return (
    <span aria-hidden="true" className="flex h-[60px] w-[52px] shrink-0 flex-col gap-[2px] overflow-hidden rounded-lg bg-[#F2F4F7] p-[5px] [--bar-scale:0.42] sm:h-[92px] sm:w-full sm:gap-[3px] sm:p-2 sm:[--bar-scale:1] dark:bg-slate-800">
      {bars.map((bar, index) => (
        // Полосы схемы не переставляются и не имеют идентичности: индекс — их ключ.
        <span key={index} className="block shrink-0 rounded-[3px]" style={{ height: `max(3px, calc(${bar.height}px * var(--bar-scale)))`, width: bar.width, background: bar.color }} />
      ))}
      {bars.length === 0 ? <span className="m-auto text-[#98A2B3]"><NavIcon name="plus" size={16} /></span> : null}
    </span>
  )
}

function TemplateCard({ template, selected, onSelect }: { template: PageTemplateItem | null, selected: boolean, onSelect: () => void }) {
  const name = template?.name ?? 'Пустая страница'
  const description = template === null ? 'Без блоков: соберёте сами из каталога' : (template.description ?? '')

  return (
    <button
      type="button"
      role="radio"
      aria-checked={selected}
      onClick={onSelect}
      className={cn(
        'flex items-center gap-3 rounded-xl text-left transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500 sm:flex-col sm:items-stretch sm:gap-2',
        selected
          ? 'border-2 border-[#047857] bg-[#F6FEF9] p-[9px] dark:bg-emerald-950/30'
          : 'border border-[#E4E7EC] bg-white p-[10px] hover:border-[#A6D8C4] dark:border-slate-700 dark:bg-slate-900',
      )}
    >
      <TemplateThumb template={template} />
      <span className="flex min-w-0 flex-1 flex-col gap-0.5 sm:px-0.5 sm:pb-0.5">
        <span className="font-semibold">{name}</span>
        {description !== '' ? <span className="hidden text-xs text-[#5D6679] sm:block dark:text-slate-400">{description}</span> : null}
        <span className="text-xs font-semibold text-[#047857] dark:text-emerald-400">{blocksCount(template?.blocksSchema.length ?? 0)}</span>
      </span>
    </button>
  )
}

/** Создание страницы поверх списка: название, адрес, раздел и шаблон карточками. */
export function PageCreateDialog({ onClose }: { onClose: () => void }) {
  const navigate = useNavigate()
  const { push } = useToast()
  const createMutation = useCreatePageMutation()
  const templatesQuery = usePageTemplatesQuery()
  const pagesQuery = usePagesQuery()
  const [editAddress, setEditAddress] = useState(false)
  const [picked, setPicked] = useState<string | null>(null)

  const form = useForm<CreateValues>({
    resolver: zodResolver(createSchema),
    defaultValues: { type: emptyFormValues().type, title: '', slug: '', path: '', parentId: '' },
  })
  const { register, setValue, control, formState: { errors, dirtyFields } } = form
  const parentId = useWatch({ control, name: 'parentId' })
  const path = useWatch({ control, name: 'path' })

  const templates = useMemo(() => (templatesQuery.data ?? []).filter((item) => item.isActive && item.kind === 'page'), [templatesQuery.data])
  const templateCode = picked ?? templates[0]?.code ?? EMPTY
  const template = templates.find((item) => item.code === templateCode) ?? null
  const pages = useMemo(() => pagesQuery.data ?? [], [pagesQuery.data])
  const parentOptions = useMemo(() => buildParentOptions(pages, null, parentId), [pages, parentId])
  const parentPath = (id: string) => pages.find((item) => item.id === id)?.path ?? ''

  // Адрес следует за названием и разделом, пока его не поменяли вручную.
  const syncAddress = (title: string, parent: string): void => {
    const slug = dirtyFields.slug === true ? form.getValues('slug') : slugify(title)
    if (dirtyFields.slug !== true) {
      setValue('slug', slug)
    }
    if (dirtyFields.path !== true) {
      setValue('path', pathFromSlug(slug, parentPath(parent)))
    }
  }

  const titleField = register('title')
  const slugField = register('slug')
  const parentField = register('parentId')

  const openAddressOnError = (): void => {
    if (form.getFieldState('slug').invalid || form.getFieldState('path').invalid) {
      setEditAddress(true)
    }
  }

  const submit = async (values: CreateValues): Promise<void> => {
    try {
      const created = await createMutation.mutateAsync({
        ...toPagePayload({
          ...emptyFormValues(),
          ...values,
          h1: values.title,
          type: template?.pageType ?? values.type,
          template: template?.code ?? 'default',
        }),
        starterTemplate: template?.code,
      })
      push({ title: 'Страница создана', description: template === null ? 'Добавьте блоки и заполните SEO.' : 'Замените тексты-заготовки на свои.' })
      void navigate(`/admin/pages/${created.id}`)
    } catch (error) {
      applyServerValidationErrors(error, form.setError)
      openAddressOnError()
      push({ title: 'Не удалось создать страницу', description: describeApiError(error, 'Проверьте поля и права доступа.') })
    }
  }

  const blockNames = template === null ? [] : templateBlockNames(template)

  return (
    <DialogPrimitive.Root open onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-40 bg-[#101828]/45" />
        <DialogPrimitive.Content
          onOpenAutoFocus={(event) => {
            event.preventDefault()
            form.setFocus('title')
          }}
          className="fixed inset-x-0 bottom-0 z-50 flex max-h-[92dvh] flex-col rounded-t-[20px] bg-white text-[#101828] shadow-xl sm:inset-x-auto sm:bottom-auto sm:left-1/2 sm:top-[5vh] sm:max-h-[90vh] sm:w-[calc(100vw-32px)] sm:max-w-[920px] sm:-translate-x-1/2 sm:rounded-2xl dark:bg-slate-900 dark:text-slate-100"
        >
          <form onSubmit={form.handleSubmit(submit, openAddressOnError)} noValidate className="flex min-h-0 flex-1 flex-col">
            <span aria-hidden="true" className="mx-auto mt-2 h-1 w-10 rounded-full bg-[#D0D5DD] sm:hidden" />
            <div className="flex items-start gap-3 px-4 pb-1 pt-3 sm:px-6 sm:pt-5">
              <div className="min-w-0 flex-1">
                <DialogPrimitive.Title className="text-lg font-bold sm:text-xl">Новая страница</DialogPrimitive.Title>
                <DialogPrimitive.Description className="mt-0.5 text-sm text-[#5D6679] dark:text-slate-400">
                  Создаётся черновиком: на сайте не появится, пока вы её не опубликуете
                </DialogPrimitive.Description>
              </div>
              <DialogPrimitive.Close asChild>
                <button type="button" aria-label="Закрыть" className="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] border border-[#E4E7EC] text-[#344054] hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                  <NavIcon name="close" size={18} />
                </button>
              </DialogPrimitive.Close>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto px-4 pb-4 sm:px-6">
              <div className="grid gap-3.5 pt-3 sm:grid-cols-2">
                <Field
                  label="Название страницы"
                  error={errors.title?.message}
                  hint={editAddress ? undefined : (
                    <>
                      Адрес: {path === '' ? 'появится после названия' : <span className="font-mono text-[#344054] dark:text-slate-200">{path}</span>}
                      {' · '}
                      <button type="button" onClick={() => setEditAddress(true)} className="font-semibold text-[#047857] hover:underline dark:text-emerald-400">изменить</button>
                    </>
                  )}
                >
                  {(id, describedBy) => (
                    <input
                      id={id}
                      aria-describedby={describedBy}
                      aria-invalid={errors.title !== undefined}
                      autoComplete="off"
                      className={inputClass}
                      {...titleField}
                      onChange={(event) => {
                        void titleField.onChange(event)
                        syncAddress(event.target.value, form.getValues('parentId'))
                      }}
                    />
                  )}
                </Field>
                <Field label="Раздел сайта" hint="Влияет на адрес и хлебные крошки">
                  {(id, describedBy) => (
                    <NativeSelect
                      id={id}
                      aria-describedby={describedBy}
                      className="h-[42px] rounded-[10px] text-[15px]"
                      {...parentField}
                      onChange={(event) => {
                        void parentField.onChange(event)
                        syncAddress(form.getValues('title'), event.target.value)
                      }}
                    >
                      <option value="">Без раздела — верхний уровень</option>
                      {parentOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </NativeSelect>
                  )}
                </Field>
              </div>

              {editAddress ? (
                <div className="mt-3 grid gap-3.5 rounded-xl bg-[#F9FAFB] p-3 sm:grid-cols-2 dark:bg-slate-950">
                  <Field label="Slug" error={errors.slug?.message} hint="Последняя часть адреса латиницей">
                    {(id, describedBy) => (
                      <input
                        id={id}
                        aria-describedby={describedBy}
                        aria-invalid={errors.slug !== undefined}
                        className={inputClass}
                        {...slugField}
                        onChange={(event) => {
                          void slugField.onChange(event)
                          if (dirtyFields.path !== true) {
                            setValue('path', pathFromSlug(event.target.value, parentPath(form.getValues('parentId'))))
                          }
                        }}
                      />
                    )}
                  </Field>
                  <Field label="Адрес страницы" error={errors.path?.message} hint="Например, /zabory/profnastil/">
                    {(id, describedBy) => <input id={id} aria-describedby={describedBy} aria-invalid={errors.path !== undefined} className={cn(inputClass, 'font-mono text-sm')} {...register('path')} />}
                  </Field>
                </div>
              ) : null}

              <div className="mt-5">
                <p id="new-page-template" className="mb-2.5 text-sm font-semibold text-[#344054] dark:text-slate-200">Шаблон</p>
                {templatesQuery.isPending ? <p className="text-sm text-[#5D6679]">Загружаем шаблоны…</p> : null}
                <div role="radiogroup" aria-labelledby="new-page-template" className="grid gap-1.5 sm:grid-cols-[repeat(auto-fill,minmax(200px,1fr))] sm:gap-2.5">
                  {templates.map((item) => (
                    <TemplateCard key={item.code} template={item} selected={item.code === templateCode} onSelect={() => setPicked(item.code)} />
                  ))}
                  <TemplateCard template={null} selected={templateCode === EMPTY} onSelect={() => setPicked(EMPTY)} />
                </div>
                {template === null ? (
                  <div className="mt-3 max-w-sm">
                    <Field label="Тип страницы" error={errors.type?.message} hint="Определяет оформление и разметку для поиска">
                      {(id, describedBy) => (
                        <NativeSelect id={id} aria-describedby={describedBy} className="h-[42px] rounded-[10px] text-[15px]" {...register('type')}>
                          {Object.entries(pageTypeLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </NativeSelect>
                      )}
                    </Field>
                  </div>
                ) : null}
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-3 border-t border-[#F0F2F5] bg-[#F9FAFB] px-4 py-3 sm:rounded-b-2xl sm:px-6 sm:py-3.5 dark:border-slate-800 dark:bg-slate-950">
              <p className="hidden min-w-0 flex-[1_1_320px] text-[13px] text-[#475467] sm:block dark:text-slate-400">
                {template === null
                  ? <><b className="text-[#101828] dark:text-slate-100">Пустая страница:</b> блоки добавите сами</>
                  : <><b className="text-[#101828] dark:text-slate-100">{template.name}:</b> {blockNames.join(' · ')}</>}
              </p>
              <DialogPrimitive.Close asChild>
                <button type="button" className="hidden h-[42px] rounded-[10px] border border-[#D0D5DD] bg-white px-3.5 text-sm font-semibold text-[#344054] hover:bg-slate-50 sm:block dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                  Отмена
                </button>
              </DialogPrimitive.Close>
              <button
                type="submit"
                disabled={createMutation.isPending}
                className="h-12 flex-1 rounded-xl bg-[#047857] px-4 text-[15px] font-semibold text-white hover:bg-[#065F46] disabled:opacity-60 sm:h-[42px] sm:flex-none sm:rounded-[10px] sm:text-sm"
              >
                {createMutation.isPending ? 'Создаём…' : 'Создать и открыть'}
              </button>
            </div>
          </form>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  )
}
