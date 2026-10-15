import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import { SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { useMemo, useState, type ReactNode } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useToast } from '../app/providers/toast-provider'
import {
  useCreatePortfolioCategoryMutation,
  useDeletePortfolioCategoryMutation,
  useDeletePortfolioProjectMutation,
  usePortfolioQuery,
  useReorderPortfolioCategoriesMutation,
  useReorderPortfolioProjectsMutation,
  useSetPortfolioPublishedMutation,
  useUpdatePortfolioCategoryMutation,
  type PortfolioCategory,
  type PortfolioCategoryPayload,
  type PortfolioProjectSummary,
} from '../entities/portfolio/api'
import { PortfolioCategoryDialog, type CategoryDraft } from '../features/portfolio/PortfolioCategoryDialog'
import { mediaCountLabel, moveMedia, projectsCountLabel, projectsOf, sortedCategories } from '../features/portfolio/portfolio-utils'
import { describeApiError } from '../features/seo/redirects/redirect-rules'
import { NavIcon } from '../layouts/nav-icons'
import { cn } from '../shared/lib/cn'
import { Badge, Button, ConfirmDialog, ErrorState, PageLoadingState, Switch } from '../shared/ui'
import { useCan } from '../stores/auth'

type CategoryDialogState = { mode: 'create'; draft: CategoryDraft } | { mode: 'edit'; category: PortfolioCategory; draft: CategoryDraft }

type DeleteTarget = { type: 'category'; category: PortfolioCategory } | { type: 'project'; project: PortfolioProjectSummary }

const iconButton =
  'flex size-10 shrink-0 items-center justify-center rounded-lg text-graphite transition hover:bg-surface-strong hover:text-ink focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 disabled:pointer-events-none disabled:opacity-30 dark:text-slate-400 dark:hover:bg-slate-800'

function Icon({ children, size = 18 }: { children: ReactNode; size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.9} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {children}
    </svg>
  )
}

const GripIcon = () => (
  <Icon>
    <circle cx="9" cy="6" r="1.1" fill="currentColor" />
    <circle cx="15" cy="6" r="1.1" fill="currentColor" />
    <circle cx="9" cy="12" r="1.1" fill="currentColor" />
    <circle cx="15" cy="12" r="1.1" fill="currentColor" />
    <circle cx="9" cy="18" r="1.1" fill="currentColor" />
    <circle cx="15" cy="18" r="1.1" fill="currentColor" />
  </Icon>
)

function useDndSensors() {
  return useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )
}

function SortableRow({ id, disabled, label, children, className }: { id: string; disabled: boolean; label: string; children: ReactNode; className?: string }) {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({ id, disabled })

  return (
    <li ref={setNodeRef} style={{ transform: CSS.Transform.toString(transform), transition }} className={cn('relative', isDragging && 'z-10', className)}>
      <div className={cn('flex min-w-0 items-center gap-1', isDragging && 'rounded-[14px] shadow-xl')}>
        <button
          ref={setActivatorNodeRef}
          type="button"
          className={cn(iconButton, 'cursor-grab touch-none active:cursor-grabbing', disabled && 'invisible')}
          aria-label={`Перетащить: ${label}`}
          {...attributes}
          {...listeners}
        >
          <GripIcon />
        </button>
        <div className="min-w-0 flex-1">{children}</div>
      </div>
    </li>
  )
}

/** Портфолио: разделы (второй уровень на сайте) и работы раздела, порядок — перетаскиванием. */
export default function PortfolioPage() {
  const query = usePortfolioQuery()
  const canManage = useCan('portfolio.manage')
  const { push } = useToast()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const [categoryDialog, setCategoryDialog] = useState<CategoryDialogState | null>(null)
  const [dialogError, setDialogError] = useState<string | null>(null)
  const [toDelete, setToDelete] = useState<DeleteTarget | null>(null)
  const createCategory = useCreatePortfolioCategoryMutation()
  const updateCategory = useUpdatePortfolioCategoryMutation()
  const deleteCategory = useDeletePortfolioCategoryMutation()
  const deleteProject = useDeletePortfolioProjectMutation()
  const reorderCategories = useReorderPortfolioCategoriesMutation()
  const reorderProjects = useReorderPortfolioProjectsMutation()
  const setPublished = useSetPortfolioPublishedMutation()
  const categorySensors = useDndSensors()
  const projectSensors = useDndSensors()

  const categories = useMemo(() => sortedCategories(query.data?.categories ?? []), [query.data])
  const allProjects = useMemo(() => query.data?.projects ?? [], [query.data])
  const selectedId = searchParams.get('category') ?? categories[0]?.id ?? null
  const selected = categories.find((category) => category.id === selectedId) ?? categories[0] ?? null
  const projects = useMemo(() => (selected === null ? [] : projectsOf(allProjects, selected.id)), [allProjects, selected])
  const published = allProjects.filter((project) => project.status === 'published').length

  const fail = (title: string) => (error: unknown) => push({ title, description: describeApiError(error, 'Обновите страницу и повторите.') })

  const selectCategory = (id: string): void => {
    setSearchParams(id === categories[0]?.id ? {} : { category: id }, { replace: true })
  }

  const submitCategory = (payload: PortfolioCategoryPayload): void => {
    if (categoryDialog === null) {
      return
    }
    const onError = (error: unknown) => setDialogError(describeApiError(error, 'Проверьте поля и повторите.'))
    if (categoryDialog.mode === 'create') {
      createCategory.mutate(payload, {
        onSuccess: (category) => {
          setCategoryDialog(null)
          selectCategory(category.id)
          push({ title: 'Раздел создан', description: `«${category.title}»` })
        },
        onError,
      })
    } else {
      updateCategory.mutate(
        { id: categoryDialog.category.id, payload },
        {
          onSuccess: (category) => {
            setCategoryDialog(null)
            push({ title: 'Раздел сохранён', description: `«${category.title}»` })
          },
          onError,
        },
      )
    }
  }

  const onCategoryDragEnd = ({ active, over }: DragEndEvent): void => {
    if (over === null || active.id === over.id) {
      return
    }
    const ids = categories.map((category) => category.id)
    const next = moveMedia(ids, ids.indexOf(String(active.id)), ids.indexOf(String(over.id)))
    reorderCategories.mutate(next, { onError: fail('Не удалось изменить порядок разделов') })
  }

  const onProjectDragEnd = ({ active, over }: DragEndEvent): void => {
    if (over === null || active.id === over.id || selected === null) {
      return
    }
    const ids = projects.map((project) => project.id)
    const next = moveMedia(ids, ids.indexOf(String(active.id)), ids.indexOf(String(over.id)))
    reorderProjects.mutate({ categoryId: selected.id, ids: next }, { onError: fail('Не удалось изменить порядок работ') })
  }

  const moveProject = (index: number, to: number): void => {
    if (selected === null) {
      return
    }
    const ids = moveMedia(projects.map((project) => project.id), index, to)
    reorderProjects.mutate({ categoryId: selected.id, ids }, { onError: fail('Не удалось изменить порядок работ') })
  }

  const togglePublished = (project: PortfolioProjectSummary): void => {
    const next = project.status !== 'published'
    setPublished.mutate(
      { id: project.id, published: next },
      {
        onSuccess: () => push({ title: next ? 'Работа опубликована' : 'Работа снята с сайта', description: `«${project.title}»` }),
        onError: fail(next ? 'Не удалось опубликовать' : 'Не удалось снять с публикации'),
      },
    )
  }

  const confirmDelete = (): void => {
    if (toDelete === null) {
      return
    }
    if (toDelete.type === 'category') {
      const { category } = toDelete
      deleteCategory.mutate(category.id, {
        onSuccess: () => {
          setSearchParams({}, { replace: true })
          push({ title: 'Раздел удалён', description: `«${category.title}»` })
        },
        onError: fail('Не удалось удалить раздел'),
      })
    } else {
      const { project } = toDelete
      deleteProject.mutate(project.id, { onSuccess: () => push({ title: 'Работа удалена', description: `«${project.title}»` }), onError: fail('Не удалось удалить работу') })
    }
    setToDelete(null)
  }

  const busy = reorderProjects.isPending || reorderCategories.isPending

  return (
    <div className="mx-auto flex w-full max-w-[1200px] flex-col gap-5">
      <div className="flex flex-col items-start gap-3 sm:flex-row">
        <div className="min-w-0 flex-1">
          <h1 className="text-[26px] font-bold tracking-[-0.01em]">Портфолио</h1>
          <p className="mt-1 max-w-2xl text-graphite dark:text-slate-400">
            Работы с фото и видео по разделам. На сайте: Главная → Портфолио → раздел → работа. Порядок меняется перетаскиванием.
          </p>
        </div>
        {canManage ? (
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" onClick={() => { setDialogError(null); setCategoryDialog({ mode: 'create', draft: { title: '', slug: '', shortTitle: '', description: '' } }) }}>
              Новый раздел
            </Button>
            <Button type="button" className="gap-1.5" disabled={categories.length === 0} onClick={() => navigate(`/admin/portfolio/new${selected !== null ? `?category=${selected.id}` : ''}`)}>
              <NavIcon name="plus" size={18} />
              Новая работа
            </Button>
          </div>
        ) : null}
      </div>

      {query.isPending ? <PageLoadingState /> : null}
      {query.isError ? <ErrorState title="Не удалось загрузить портфолио" description="Обновите страницу или попробуйте позже." /> : null}

      {query.isSuccess && categories.length === 0 ? (
        <div className="rounded-[14px] border border-dashed border-line-strong bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
          <p className="text-lg font-semibold">Портфолио пока пустое</p>
          <p className="mx-auto mt-1 max-w-lg text-graphite dark:text-slate-400">
            Начните с раздела — например, «Заборы из профнастила» или «Ворота и калитки». Потом добавьте в него работы с фото и видео.
          </p>
          {canManage ? (
            <Button type="button" className="mt-4" onClick={() => { setDialogError(null); setCategoryDialog({ mode: 'create', draft: { title: '', slug: '', shortTitle: '', description: '' } }) }}>
              Создать первый раздел
            </Button>
          ) : null}
        </div>
      ) : null}

      {query.isSuccess && categories.length > 0 ? (
        <div className="grid grid-cols-1 items-start gap-5 lg:grid-cols-[300px_minmax(0,1fr)]">
          <section aria-label="Разделы портфолио" className="grid min-w-0 grid-cols-1 gap-2 lg:sticky lg:top-4">
            <h2 className="flex items-baseline justify-between px-1 text-sm font-semibold tracking-wide text-graphite uppercase dark:text-slate-400">
              Разделы
              <span className="font-normal normal-case tabular-nums">{published} на сайте</span>
            </h2>
            <DndContext sensors={categorySensors} collisionDetection={closestCenter} onDragEnd={onCategoryDragEnd}>
              <SortableContext items={categories.map((category) => category.id)} strategy={verticalListSortingStrategy}>
                <ul className="grid grid-cols-1 gap-1.5">
                  {categories.map((category) => {
                    const count = projectsOf(allProjects, category.id).length
                    const active = category.id === selected?.id
                    return (
                      <SortableRow key={category.id} id={category.id} disabled={!canManage || busy} label={category.title}>
                        <div className={cn('flex min-w-0 items-center rounded-xl border transition', active ? 'border-brand-600 bg-brand-50 dark:bg-brand-950/30' : 'border-line bg-white hover:border-line-strong dark:border-slate-700 dark:bg-slate-900')}>
                          <button
                            type="button"
                            onClick={() => selectCategory(category.id)}
                            aria-current={active ? 'true' : undefined}
                            className="flex min-h-14 min-w-0 flex-1 flex-col items-start justify-center rounded-xl px-3 py-2 text-left focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500"
                          >
                            <span className={cn('w-full truncate font-semibold', active ? 'text-brand-800 dark:text-brand-300' : 'text-ink dark:text-slate-100')}>{category.title}</span>
                            <span className="text-sm text-graphite dark:text-slate-400">{projectsCountLabel(count)}</span>
                          </button>
                          {canManage ? (
                            <button
                              type="button"
                              className={iconButton}
                              aria-label={`Изменить раздел «${category.title}»`}
                              onClick={() => {
                                setDialogError(null)
                                setCategoryDialog({ mode: 'edit', category, draft: { title: category.title, slug: category.slug, shortTitle: category.shortTitle ?? '', description: category.description ?? '' } })
                              }}
                            >
                              <Icon><path d="M4.5 19.5h4l10-10-4-4-10 10z M13.5 6.5l4 4" /></Icon>
                            </button>
                          ) : null}
                        </div>
                      </SortableRow>
                    )
                  })}
                </ul>
              </SortableContext>
            </DndContext>
          </section>

          {selected !== null ? (
            <section aria-label={`Работы раздела «${selected.title}»`} className="grid min-w-0 grid-cols-1 gap-3">
              <div className="flex flex-wrap items-center justify-between gap-2 rounded-[14px] bg-surface px-4 py-3 dark:bg-slate-800/60">
                <div className="min-w-0">
                  <h2 className="text-lg font-bold">{selected.title}</h2>
                  <a href={selected.path} target="_blank" rel="noreferrer" className="font-mono text-sm text-brand-700 hover:underline dark:text-brand-300">
                    {selected.path}
                  </a>
                </div>
                {canManage ? (
                  <Button type="button" variant="ghost" size="sm" className="text-danger" onClick={() => setToDelete({ type: 'category', category: selected })}>
                    Удалить раздел
                  </Button>
                ) : null}
              </div>

              {projects.length === 0 ? (
                <div className="rounded-[14px] border border-dashed border-line-strong bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
                  <p className="font-semibold">В разделе пока нет работ</p>
                  <p className="mt-1 text-sm text-graphite dark:text-slate-400">Добавьте работу: фото, видео и описание объекта.</p>
                  {canManage ? (
                    <Button type="button" className="mt-4" onClick={() => navigate(`/admin/portfolio/new?category=${selected.id}`)}>
                      Добавить работу
                    </Button>
                  ) : null}
                </div>
              ) : (
                <DndContext sensors={projectSensors} collisionDetection={closestCenter} onDragEnd={onProjectDragEnd}>
                  <SortableContext items={projects.map((project) => project.id)} strategy={verticalListSortingStrategy}>
                    <ul className="grid grid-cols-1 gap-2" data-testid="portfolio-projects">
                      {projects.map((project, index) => {
                        const isPublished = project.status === 'published'
                        return (
                          <SortableRow key={project.id} id={project.id} disabled={!canManage || busy} label={project.title}>
                            <div className="flex min-w-0 flex-col gap-3 rounded-[14px] border border-line bg-white p-2.5 sm:flex-row sm:items-center dark:border-slate-700 dark:bg-slate-900">
                              <Link to={`/admin/portfolio/${project.id}`} className="flex min-w-0 flex-1 items-center gap-3 rounded-lg focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500">
                                <span className="aspect-4/3 w-24 shrink-0 overflow-hidden rounded-lg bg-surface-strong dark:bg-slate-800">
                                  {project.cover !== null ? <img src={project.cover} alt="" loading="lazy" className="size-full object-cover" /> : null}
                                </span>
                                <span className="min-w-0">
                                  <span className="block truncate font-semibold text-ink dark:text-slate-100">{project.title}</span>
                                  <span className="block truncate text-sm text-graphite dark:text-slate-400">
                                    {[project.place, project.year].filter((part) => part !== null && part !== '').join(' · ') || 'Место и год не указаны'}
                                  </span>
                                  <span className="mt-1 flex flex-wrap items-center gap-2 text-sm text-graphite dark:text-slate-400">
                                    <Badge tone={isPublished ? 'success' : 'neutral'}>{isPublished ? 'На сайте' : 'Черновик'}</Badge>
                                    {mediaCountLabel(project.photoCount, project.videoCount)}
                                  </span>
                                </span>
                              </Link>
                              <div className="flex shrink-0 items-center justify-end gap-1">
                                {canManage ? (
                                  <>
                                    <label className="mr-1 flex min-h-10 items-center gap-2 text-sm text-graphite dark:text-slate-400">
                                      <Switch checked={isPublished} aria-label={isPublished ? `Снять с сайта «${project.title}»` : `Опубликовать «${project.title}»`} onCheckedChange={() => togglePublished(project)} />
                                      <span className="sm:sr-only">На сайте</span>
                                    </label>
                                    <button type="button" className={iconButton} aria-label={`Выше: ${project.title}`} disabled={busy || index === 0} onClick={() => moveProject(index, index - 1)}>
                                      <Icon size={16}><path d="M6 14.5l6-6 6 6" /></Icon>
                                    </button>
                                    <button type="button" className={iconButton} aria-label={`Ниже: ${project.title}`} disabled={busy || index === projects.length - 1} onClick={() => moveProject(index, index + 1)}>
                                      <Icon size={16}><path d="M6 9.5l6 6 6-6" /></Icon>
                                    </button>
                                  </>
                                ) : null}
                                {isPublished ? (
                                  <a href={project.path} target="_blank" rel="noreferrer" className={iconButton} aria-label={`Открыть на сайте: ${project.title}`} title="Открыть на сайте">
                                    <NavIcon name="external" size={18} />
                                  </a>
                                ) : null}
                                {canManage ? (
                                  <button type="button" className={cn(iconButton, 'hover:text-danger')} aria-label={`Удалить работу «${project.title}»`} onClick={() => setToDelete({ type: 'project', project })}>
                                    <Icon><path d="M5 7h14M10 11v6M14 11v6M6.5 7l1 12.5h9l1-12.5M9.5 7V4.5h5V7" /></Icon>
                                  </button>
                                ) : null}
                              </div>
                            </div>
                          </SortableRow>
                        )
                      })}
                    </ul>
                  </SortableContext>
                </DndContext>
              )}
            </section>
          ) : null}
        </div>
      ) : null}

      {categoryDialog !== null ? (
        <PortfolioCategoryDialog
          mode={categoryDialog.mode}
          initial={categoryDialog.draft}
          pending={createCategory.isPending || updateCategory.isPending}
          serverError={dialogError}
          onCancel={() => setCategoryDialog(null)}
          onSubmit={submitCategory}
        />
      ) : null}

      <ConfirmDialog
        open={toDelete !== null}
        title={toDelete?.type === 'category' ? `Удалить раздел «${toDelete.category.title}»?` : `Удалить работу «${toDelete?.type === 'project' ? toDelete.project.title : ''}»?`}
        description={
          toDelete?.type === 'category'
            ? 'Удалить можно только пустой раздел: сначала перенесите или удалите его работы.'
            : 'Работа пропадёт с сайта. Фото и видео останутся в медиатеке.'
        }
        confirmLabel="Удалить"
        onCancel={() => setToDelete(null)}
        onConfirm={confirmDelete}
      />
    </div>
  )
}
