import { lazy, Suspense, useId, useMemo, useState, type ReactNode } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useToast } from '../app/providers/toast-provider'
import {
  useCreatePortfolioProjectMutation,
  usePortfolioProjectQuery,
  usePortfolioQuery,
  useSetPortfolioPublishedMutation,
  useUpdatePortfolioProjectMutation,
  type PortfolioProject,
} from '../entities/portfolio/api'
import { LeaveGuard, useLeaveBypass } from '../features/page-editor/LeaveGuard'
import { ProjectMediaEditor } from '../features/portfolio/ProjectMediaEditor'
import {
  coverIndex,
  draftErrors,
  draftToPayload,
  emptyProjectDraft,
  PORTFOLIO_LIMITS,
  projectToDraft,
  slugify,
  sortedCategories,
  type ProjectDraft,
} from '../features/portfolio/portfolio-utils'
import { describeApiError } from '../features/seo/redirects/redirect-rules'
import { cn } from '../shared/lib/cn'
import { Badge, Button, ErrorState, Input, PageLoadingState, Skeleton, Textarea } from '../shared/ui'
import { useCan } from '../stores/auth'

const TiptapRichTextEditor = lazy(() => import('../components/TiptapRichTextEditor'))

const selectClass =
  'h-12 w-full rounded-lg border border-line-strong bg-white px-3 text-sm text-ink outline-hidden ring-brand-500 focus:ring-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100'

const cardClass = 'grid min-w-0 grid-cols-1 gap-4 rounded-[14px] border border-line bg-white p-5 dark:border-slate-800 dark:bg-slate-900'

function Field({ id, label, hint, counter, error, children }: { id: string; label: ReactNode; hint?: ReactNode; counter?: string; error?: string; children: ReactNode }) {
  return (
    <div className="min-w-0">
      <label htmlFor={id} className="mb-1.5 flex items-baseline justify-between gap-3 text-sm font-semibold">
        <span>{label}</span>
        {counter !== undefined ? <span className="font-normal tabular-nums text-graphite">{counter}</span> : null}
      </label>
      {children}
      {error !== undefined ? (
        <p id={`${id}-error`} className="mt-1.5 text-sm text-danger" role="alert">{error}</p>
      ) : hint !== undefined ? (
        <p className="mt-1.5 text-sm text-graphite dark:text-slate-400">{hint}</p>
      ) : null}
    </div>
  )
}

const optional = <span className="font-normal text-graphite">— необязательно</span>

function SpecsEditor({ specs, disabled, error, onChange }: { specs: ProjectDraft['specs']; disabled: boolean; error?: string; onChange: (specs: ProjectDraft['specs']) => void }) {
  const set = (index: number, key: 'label' | 'value', value: string): void => onChange(specs.map((spec, position) => (position === index ? { ...spec, [key]: value } : spec)))

  return (
    <div className="grid gap-2">
      {specs.length === 0 ? <p className="text-sm text-graphite dark:text-slate-400">Например: «Длина — 42 м», «Высота — 2 м», «Срок — 3 дня».</p> : null}
      {specs.map((spec, index) => (
        <div key={index} className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] gap-2">
          <Input aria-label={`Характеристика ${index + 1}`} placeholder="Длина" value={spec.label} maxLength={PORTFOLIO_LIMITS.specText} disabled={disabled} onChange={(event) => set(index, 'label', event.target.value)} />
          <Input aria-label={`Значение ${index + 1}`} placeholder="42 м" value={spec.value} maxLength={PORTFOLIO_LIMITS.specText} disabled={disabled} onChange={(event) => set(index, 'value', event.target.value)} />
          <button
            type="button"
            className="flex size-12 items-center justify-center rounded-lg text-graphite hover:bg-surface-strong hover:text-danger focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 disabled:opacity-30 dark:hover:bg-slate-800"
            aria-label={`Убрать характеристику ${index + 1}`}
            disabled={disabled}
            onClick={() => onChange(specs.filter((_, position) => position !== index))}
          >
            <svg width={18} height={18} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.9} strokeLinecap="round" aria-hidden="true"><path d="M6.5 6.5l11 11M17.5 6.5l-11 11" /></svg>
          </button>
        </div>
      ))}
      {error !== undefined ? <p className="text-sm text-danger" role="alert">{error}</p> : null}
      <Button type="button" variant="outline" size="sm" className="justify-self-start" disabled={disabled || specs.length >= PORTFOLIO_LIMITS.specs} onClick={() => onChange([...specs, { label: '', value: '' }])}>
        Добавить характеристику
      </Button>
    </div>
  )
}

function ProjectForm({ project, initialCategoryId }: { project: PortfolioProject | null; initialCategoryId: string }) {
  const overview = usePortfolioQuery()
  const canManage = useCan('portfolio.manage')
  const navigate = useNavigate()
  const { push } = useToast()
  const { isBypassed, bypass } = useLeaveBypass()
  const createMutation = useCreatePortfolioProjectMutation()
  const updateMutation = useUpdatePortfolioProjectMutation()
  const publishMutation = useSetPortfolioPublishedMutation()
  const initial = useMemo(() => (project === null ? emptyProjectDraft(initialCategoryId) : projectToDraft(project)), [project, initialCategoryId])
  const [draft, setDraft] = useState<ProjectDraft>(initial)
  const [saved, setSaved] = useState<ProjectDraft>(initial)
  const [slugEdited, setSlugEdited] = useState(project !== null)
  const [touched, setTouched] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)
  const ids = { title: useId(), slug: useId(), category: useId(), place: useId(), year: useId(), summary: useId(), seoTitle: useId(), seoDescription: useId() }

  const categories = sortedCategories(overview.data?.categories ?? [])
  const category = categories.find((candidate) => candidate.id === draft.categoryId) ?? null
  const slug = slugEdited ? draft.slug : slugify(draft.title)
  const errors = touched ? draftErrors({ ...draft, slug }) : {}
  const dirty = JSON.stringify({ ...draft, slug }) !== JSON.stringify({ ...saved, slug: slugEdited || project !== null ? saved.slug : slugify(saved.title) })
  const isPublished = project?.status === 'published'
  const pending = createMutation.isPending || updateMutation.isPending || publishMutation.isPending
  const hasPhoto = coverIndex(draft.media) !== -1
  const disabled = !canManage || pending

  const set = <K extends keyof ProjectDraft>(key: K, value: ProjectDraft[K]): void => setDraft((current) => ({ ...current, [key]: value }))

  const save = async (publish: boolean | null): Promise<PortfolioProject | null> => {
    setTouched(true)
    const next = { ...draft, slug }
    if (Object.keys(draftErrors(next)).length > 0) {
      push({ title: 'Проверьте поля', description: 'Ошибки отмечены красным.' })
      return null
    }
    setServerError(null)
    const payload = draftToPayload(next)
    try {
      let result = project === null ? await createMutation.mutateAsync(payload) : dirty ? await updateMutation.mutateAsync({ id: project.id, payload }) : project
      if (publish !== null && (result.status === 'published') !== publish) {
        result = await publishMutation.mutateAsync({ id: result.id, published: publish })
      }
      const savedDraft = projectToDraft(result)
      setSaved(savedDraft)
      setDraft(savedDraft)
      setSlugEdited(true)
      push({
        title: publish === true ? 'Работа опубликована' : publish === false ? 'Работа снята с сайта' : 'Работа сохранена',
        description: `«${result.title}»`,
      })
      if (project === null) {
        bypass()
        navigate(`/admin/portfolio/${result.id}`, { replace: true })
      }
      return result
    } catch (error) {
      setServerError(describeApiError(error, 'Не удалось сохранить работу. Проверьте поля и повторите.'))
      return null
    }
  }

  return (
    <div className="mx-auto flex w-full max-w-[1200px] flex-col gap-5">
      <LeaveGuard when={dirty && canManage && !isBypassed()} onSaveAndLeave={() => save(null)} />

      <div className="flex flex-col gap-3">
        <Link to={category !== null ? `/admin/portfolio?category=${category.id}` : '/admin/portfolio'} className="inline-flex min-h-10 items-center gap-1 self-start rounded-lg text-sm font-semibold text-brand-700 hover:underline focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 dark:text-brand-300">
          ← Портфолио{category !== null ? ` · ${category.title}` : ''}
        </Link>
        <div className="flex flex-col items-start gap-3 lg:flex-row lg:items-center">
          <div className="min-w-0 flex-1">
            <h1 className="text-[26px] font-bold tracking-[-0.01em] break-words">{project === null ? 'Новая работа' : draft.title || project.title}</h1>
            <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-graphite dark:text-slate-400">
              <Badge tone={isPublished ? 'success' : 'neutral'}>{isPublished ? 'На сайте' : 'Черновик'}</Badge>
              {dirty ? <span className="font-semibold text-ink dark:text-slate-200">Есть несохранённые изменения</span> : project !== null ? <span>Все изменения сохранены</span> : null}
            </p>
          </div>
          {canManage ? (
            <div className="flex flex-wrap gap-2">
              {isPublished ? (
                <>
                  <a href={project.path} target="_blank" rel="noreferrer" className="inline-flex h-12 items-center rounded-lg px-4 text-sm font-medium text-brand-700 hover:bg-brand-50 dark:text-brand-300">Открыть на сайте</a>
                  <Button type="button" variant="outline" disabled={pending} onClick={() => void save(false)}>Снять с сайта</Button>
                  <Button type="button" disabled={pending || !dirty} onClick={() => void save(null)}>Сохранить</Button>
                </>
              ) : (
                <>
                  <Button type="button" variant="outline" disabled={pending || (project !== null && !dirty)} onClick={() => void save(null)}>Сохранить черновик</Button>
                  <Button type="button" disabled={pending || !hasPhoto} title={hasPhoto ? undefined : 'Добавьте хотя бы одно фото'} onClick={() => void save(true)}>
                    Опубликовать
                  </Button>
                </>
              )}
            </div>
          ) : null}
        </div>
        {serverError !== null ? <p role="alert" className="rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger">{serverError}</p> : null}
        {!hasPhoto && canManage ? <p className="text-sm text-graphite dark:text-slate-400">Чтобы опубликовать работу, добавьте хотя бы одно фото — оно станет главным.</p> : null}
      </div>

      <div className="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div className="grid min-w-0 grid-cols-1 gap-5">
          <section className={cardClass} aria-label="Основное">
            <Field id={ids.title} label="Название работы" counter={`${draft.title.trim().length}/${PORTFOLIO_LIMITS.title}`} error={errors.title}>
              <Input id={ids.title} value={draft.title} maxLength={PORTFOLIO_LIMITS.title} disabled={disabled} placeholder="Например: Забор из профнастила в СНТ «Ромашка»" aria-invalid={errors.title !== undefined} onChange={(event) => set('title', event.target.value)} />
            </Field>
            <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_140px]">
              <Field id={ids.place} label={<>Где {optional}</>}>
                <Input id={ids.place} value={draft.place} maxLength={PORTFOLIO_LIMITS.place} disabled={disabled} placeholder="Саратов, Заводской район" onChange={(event) => set('place', event.target.value)} />
              </Field>
              <Field id={ids.year} label="Год" error={errors.year}>
                <Input id={ids.year} value={draft.year} inputMode="numeric" maxLength={4} disabled={disabled} aria-invalid={errors.year !== undefined} onChange={(event) => set('year', event.target.value.replace(/\D/g, ''))} />
              </Field>
            </div>
            <Field id={ids.summary} label={<>Коротко о работе {optional}</>} counter={`${draft.summary.trim().length}/${PORTFOLIO_LIMITS.summary}`} hint="Одна-две строки для карточки в списке работ и для поисковиков.">
              <Textarea id={ids.summary} rows={2} value={draft.summary} maxLength={PORTFOLIO_LIMITS.summary} disabled={disabled} placeholder="Профнастил С8 графит, 42 м, столбы 60×60, монтаж за 2 дня" onChange={(event) => set('summary', event.target.value)} />
            </Field>
            <div className="min-w-0">
              <p className="mb-1.5 text-sm font-semibold">Описание</p>
              <p className="mb-2 text-sm text-graphite dark:text-slate-400">Расскажите о задаче, материалах и результате. Кнопка 🙂 на панели вставляет эмодзи.</p>
              <div className={cn('relative overflow-hidden rounded-[10px] border border-line-strong dark:border-slate-700 [&_.tiptap-toolbar]:static! [&_.tiptap-toolbar]:border-t-0! [&_.tiptap-toolbar]:border-b!', disabled && 'pointer-events-none opacity-70')}>
                <Suspense fallback={<Skeleton className="h-48 w-full" />}>
                  <TiptapRichTextEditor modelValue={draft.description} onChange={(value) => set('description', value)} />
                </Suspense>
              </div>
            </div>
          </section>

          <section className={cardClass} aria-labelledby="portfolio-media-heading">
            <div>
              <h2 id="portfolio-media-heading" className="text-lg font-bold">Фото и видео</h2>
              <p className="mt-1 text-sm text-graphite dark:text-slate-400">Порядок здесь — порядок в галерее на сайте.</p>
            </div>
            <ProjectMediaEditor media={draft.media} disabled={disabled} onChange={(media) => set('media', media)} />
          </section>
        </div>

        <aside className="grid min-w-0 grid-cols-1 gap-5 lg:sticky lg:top-4">
          <section className={cardClass} aria-label="Раздел и адрес">
            <Field id={ids.category} label="Раздел портфолио" error={errors.categoryId}>
              <select id={ids.category} className={selectClass} value={draft.categoryId} disabled={disabled} onChange={(event) => set('categoryId', event.target.value)}>
                {categories.length === 0 ? <option value="">Сначала создайте раздел</option> : null}
                {categories.map((candidate) => (
                  <option key={candidate.id} value={candidate.id}>{candidate.title}</option>
                ))}
              </select>
            </Field>
            <Field id={ids.slug} label="Адрес на сайте" error={errors.slug} hint="Заполняется из названия, можно поменять.">
              <div className="rounded-lg border border-line-strong bg-surface px-3 py-1.5 font-mono text-sm text-graphite focus-within:ring-2 focus-within:ring-brand-500 dark:border-slate-700 dark:bg-slate-800">
                <span className="block truncate">{category?.path ?? '/portfolio/…/'}</span>
                <input
                  id={ids.slug}
                  value={slug}
                  maxLength={120}
                  disabled={disabled}
                  aria-invalid={errors.slug !== undefined}
                  onChange={(event) => {
                    setSlugEdited(true)
                    set('slug', event.target.value.toLowerCase())
                  }}
                  className="h-9 w-full bg-transparent text-ink outline-hidden dark:text-slate-100"
                />
              </div>
            </Field>
          </section>

          <section className={cardClass} aria-labelledby="portfolio-specs-heading">
            <div>
              <h2 id="portfolio-specs-heading" className="text-lg font-bold">Характеристики</h2>
              <p className="mt-1 text-sm text-graphite dark:text-slate-400">До {PORTFOLIO_LIMITS.specs} строк в плашке «О проекте».</p>
            </div>
            <SpecsEditor specs={draft.specs} disabled={disabled} error={errors.specs} onChange={(specs) => set('specs', specs)} />
          </section>

          <section className={cardClass} aria-labelledby="portfolio-seo-heading">
            <h2 id="portfolio-seo-heading" className="text-lg font-bold">Для поисковиков</h2>
            <Field id={ids.seoTitle} label={<>Title {optional}</>} counter={`${draft.seoTitle.trim().length}/${PORTFOLIO_LIMITS.seoTitle}`} hint="Пусто — возьмём название работы.">
              <Input id={ids.seoTitle} value={draft.seoTitle} maxLength={PORTFOLIO_LIMITS.seoTitle} disabled={disabled} onChange={(event) => set('seoTitle', event.target.value)} />
            </Field>
            <Field id={ids.seoDescription} label={<>Description {optional}</>} counter={`${draft.seoDescription.trim().length}/${PORTFOLIO_LIMITS.seoDescription}`} hint="Пусто — возьмём «Коротко о работе».">
              <Textarea id={ids.seoDescription} rows={3} value={draft.seoDescription} maxLength={PORTFOLIO_LIMITS.seoDescription} disabled={disabled} onChange={(event) => set('seoDescription', event.target.value)} />
            </Field>
          </section>
        </aside>
      </div>
    </div>
  )
}

/** Карточка работы портфолио: /admin/portfolio/new и /admin/portfolio/:id. */
export default function PortfolioProjectPage() {
  const { id } = useParams<{ id: string }>()
  const [searchParams] = useSearchParams()
  const projectId = id === undefined || id === 'new' ? null : id
  const projectQuery = usePortfolioProjectQuery(projectId)
  const overview = usePortfolioQuery()

  if (overview.isPending || (projectId !== null && projectQuery.isPending)) {
    return <PageLoadingState />
  }

  if (projectId === null) {
    const categories = sortedCategories(overview.data?.categories ?? [])
    const requested = searchParams.get('category')
    const categoryId = categories.find((category) => category.id === requested)?.id ?? categories[0]?.id ?? ''

    return <ProjectForm project={null} initialCategoryId={categoryId} />
  }

  if (projectQuery.isError || projectQuery.data === undefined) {
    return (
      <ErrorState
        title="Работа не найдена"
        description="Возможно, её удалили. Вернитесь к списку портфолио."
      />
    )
  }

  return <ProjectForm key={projectQuery.data.id} project={projectQuery.data} initialCategoryId={projectQuery.data.categoryId} />
}
