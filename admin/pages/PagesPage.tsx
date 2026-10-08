import { useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useToast } from '../app/providers/toast-provider'
import {
    fetchPagePreviewLink,
    useBulkPagesMutation,
    useDuplicatePageMutation,
    usePagesQuery,
    type PageStatus,
} from '../entities/page/api'
import { NavIcon } from '../layouts/nav-icons'
import { pageStatusLabels, pageTypeLabels } from '../features/page-editor/page-status'
import { PagesBulkBar } from '../features/page-editor/PagesBulkBar'
import { PageCreateDialog } from '../features/pages-list/PageCreateDialog'
import { PageRowMenu, type PageRowActions } from '../features/pages-list/PageRowMenu'
import {
    formatUpdated,
    pagesSummary,
    SORT_OPTIONS,
    STATUS_PILL,
    statusTabs,
    visiblePages,
    type PageSort,
    type StatusFilter,
} from '../features/pages-list/list-model'
import { describeApiError } from '../features/seo/redirects/redirect-rules'
import { preloadPageEditorOnIntent } from '../routes/prefetch'
import { cn } from '../shared/lib/cn'
import { Checkbox, ErrorState, PageLoadingState } from '../shared/ui'
import { useCan } from '../stores/auth'
import type { ContentPageItem } from '../types/api'

const selectClass =
    'h-12 rounded-[10px] border border-line-strong bg-white px-2.5 text-sm text-graphite outline-hidden focus:ring-2 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'

function StatusPill({ status }: { status: PageStatus }) {
    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2 py-[3px] text-xs font-semibold',
                STATUS_PILL[status],
            )}
        >
            {pageStatusLabels[status]}
        </span>
    )
}

function ChangesMarker({ short = false }: { short?: boolean }) {
    return (
        <span className="inline-flex items-center gap-1.5 text-xs text-orange-800 dark:text-orange-300">
            <span aria-hidden="true" className="h-[7px] w-[7px] rounded-full bg-orange-600" />
            {short ? 'есть правки' : 'есть неопубликованные правки'}
        </span>
    )
}

function NoindexMarker() {
    return (
        <span
            title="Закрыта от индексации"
            className="inline-flex rounded-md bg-surface-strong px-1.5 py-px font-mono text-[11px] text-graphite dark:bg-slate-800 dark:text-slate-300"
        >
            noindex
        </span>
    )
}

/** Список страниц: вкладки статусов, поиск, действия с выбранными; на телефоне — карточки. */
export default function PagesPage({ creating = false }: { creating?: boolean }) {
    const pagesQuery = usePagesQuery()
    const navigate = useNavigate()
    const { push } = useToast()
    const duplicateMutation = useDuplicatePageMutation()
    const statusMutation = useBulkPagesMutation()
    const canCreate = useCan('pages.create')
    const canEditPages = useCan('pages.edit')
    const canEditSeo = useCan('seo.edit')
    const canUnpublish = useCan('pages.unpublish')
    const canArchive = useCan('pages.archive')
    const canBulk = canEditPages || canEditSeo
    const [status, setStatus] = useState<StatusFilter>('all')
    const [query, setQuery] = useState('')
    const [type, setType] = useState('')
    const [sort, setSort] = useState<PageSort>('updated')
    const [selected, setSelected] = useState<ReadonlySet<string>>(new Set())

    const allPages = useMemo(() => pagesQuery.data ?? [], [pagesQuery.data])
    const pages = useMemo(
        () => visiblePages(allPages, { status, query, type, sort }),
        [allPages, status, query, type, sort],
    )
    const tabs = statusTabs(allPages, status)
    const types = useMemo(
        () =>
            [...new Set(allPages.map((page) => page.type))].sort((left, right) =>
                (pageTypeLabels[left] ?? left).localeCompare(pageTypeLabels[right] ?? right, 'ru'),
            ),
        [allPages],
    )

    const visibleIds = pages.map((page) => page.id)
    const selectedIds = visibleIds.filter((id) => selected.has(id))
    const allSelected = visibleIds.length > 0 && selectedIds.length === visibleIds.length

    const toggle = (id: string, checked: boolean): void => {
        setSelected((current) => {
            const next = new Set(current)
            if (checked) {
                next.add(id)
            } else {
                next.delete(id)
            }
            return next
        })
    }

    const pickStatus = (next: StatusFilter): void => {
        setStatus(next)
        setSelected(new Set())
    }

    const changeStatus = (page: ContentPageItem, next: PageStatus, done: string): void => {
        statusMutation.mutate(
            { ids: [page.id], action: 'status', status: next },
            {
                onSuccess: (response) => {
                    const failure = response.results.find((result) => !result.ok)
                    push(
                        failure === undefined
                            ? { title: done, description: `«${page.title}»` }
                            : {
                                  title: 'Не удалось сменить статус',
                                  description: failure.error ?? undefined,
                              },
                    )
                },
                onError: (error) =>
                    push({
                        title: 'Не удалось сменить статус',
                        description: describeApiError(
                            error,
                            'Проверьте права доступа и повторите попытку.',
                        ),
                    }),
            },
        )
    }

    const actions: PageRowActions = {
        edit: (page) => void navigate(`/admin/pages/${page.id}`),
        openOnSite: (page) => {
            window.open(page.path, '_blank', 'noopener')
        },
        preview: (page) => {
            // Окно открывается синхронно по клику, иначе браузер заблокирует его после запроса ссылки.
            const previewWindow = window.open('about:blank', '_blank')
            fetchPagePreviewLink(page.id)
                .then(({ previewUrl }) => {
                    if (previewWindow === null) {
                        window.open(previewUrl, '_blank', 'noopener')
                        return
                    }
                    previewWindow.opener = null
                    previewWindow.location.href = previewUrl
                })
                .catch((error: unknown) => {
                    previewWindow?.close()
                    push({
                        title: 'Не удалось открыть предпросмотр',
                        description: describeApiError(
                            error,
                            'Повторите попытку из редактора страницы.',
                        ),
                    })
                })
        },
        duplicate: canCreate
            ? (page) =>
                  duplicateMutation.mutate(
                      { pageId: page.id },
                      {
                          onSuccess: (copy) => {
                              push({
                                  title: 'Копия создана',
                                  description: `Черновик ${copy.path}. Измените адрес и содержимое.`,
                              })
                              void navigate(`/admin/pages/${copy.id}`)
                          },
                          onError: (error) =>
                              push({
                                  title: 'Не удалось создать копию',
                                  description: describeApiError(
                                      error,
                                      'Проверьте права доступа и повторите попытку.',
                                  ),
                              }),
                      },
                  )
            : undefined,
        unpublish: canUnpublish
            ? (page) => changeStatus(page, 'unpublished', 'Страница снята с публикации')
            : undefined,
        archive: canArchive
            ? (page) => changeStatus(page, 'archived', 'Страница перенесена в архив')
            : undefined,
    }

    return (
        <div className="mx-auto flex w-full max-w-[1440px] flex-col gap-4 px-4 py-5 sm:px-6 lg:px-7 lg:py-7">
            <div className="flex flex-wrap items-center gap-3">
                <div className="min-w-0 flex-1">
                    <h1 className="text-[26px] font-bold tracking-[-0.01em]">Страницы</h1>
                    {pagesQuery.isSuccess ? (
                        <p className="mt-1 text-graphite dark:text-slate-400">
                            {pagesSummary(allPages)}
                        </p>
                    ) : null}
                </div>
                {canCreate ? (
                    <Link
                        to="/admin/pages/new"
                        className="inline-flex h-12 w-12 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-brand-700 font-semibold text-white hover:bg-brand-800 sm:h-12 sm:w-auto sm:rounded-[10px] sm:px-3.5 sm:text-sm"
                    >
                        <NavIcon name="plus" size={18} />
                        <span className="sr-only sm:not-sr-only">Создать страницу</span>
                    </Link>
                ) : null}
            </div>

            <div className="flex flex-wrap items-center gap-2.5">
                <div
                    role="tablist"
                    aria-label="Статус страниц"
                    className="order-2 -mx-4 flex w-[calc(100%+2rem)] gap-1.5 overflow-x-auto px-4 sm:order-1 sm:mx-0 sm:w-auto sm:flex-wrap sm:gap-0.5 sm:overflow-visible sm:rounded-[11px] sm:bg-line sm:p-[3px] dark:sm:bg-slate-800"
                >
                    {tabs.map((tab) => {
                        const active = tab.id === status
                        return (
                            <button
                                key={tab.id}
                                type="button"
                                role="tab"
                                aria-selected={active}
                                aria-controls="pages-results"
                                onClick={() => pickStatus(tab.id)}
                                className={cn(
                                    'flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-3 text-sm font-semibold sm:rounded-[9px] sm:border-0',
                                    active
                                        ? 'border-ink bg-ink text-white sm:bg-white sm:text-ink sm:shadow-[0_1px_2px_rgba(16,24,40,0.08)] dark:sm:bg-slate-950 dark:sm:text-slate-100'
                                        : 'border-line-strong bg-white text-graphite sm:bg-transparent sm:text-graphite dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:sm:bg-transparent',
                                )}
                            >
                                {tab.label}{' '}
                                <span
                                    className={cn(
                                        'text-xs font-bold',
                                        active
                                            ? 'opacity-75 sm:text-graphite sm:opacity-100'
                                            : 'text-graphite dark:text-slate-400',
                                    )}
                                >
                                    {tab.count}
                                </span>
                            </button>
                        )
                    })}
                </div>
                <div className="order-1 flex w-full items-center gap-2.5 sm:order-2 sm:w-auto sm:flex-[1_1_440px]">
                    <label className="flex h-12 min-w-0 flex-1 items-center gap-2 rounded-xl border border-line-strong bg-white px-3 text-graphite focus-within:ring-2 focus-within:ring-brand-500 sm:h-12 sm:min-w-[220px] sm:max-w-[360px] sm:rounded-[10px] dark:border-slate-700 dark:bg-slate-900">
                        <NavIcon name="search" size={16} />
                        <input
                            type="search"
                            aria-label="Поиск по страницам"
                            placeholder="Название или адрес"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            className="min-w-0 flex-1 border-0 bg-transparent text-base text-ink outline-hidden sm:text-sm dark:text-slate-100"
                        />
                    </label>
                    <select
                        aria-label="Тип страницы"
                        value={type}
                        onChange={(event) => setType(event.target.value)}
                        className={cn(selectClass, 'hidden shrink-0 sm:block')}
                    >
                        <option value="">Все типы</option>
                        {types.map((value) => (
                            <option key={value} value={value}>
                                {pageTypeLabels[value] ?? value}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Сортировка"
                        value={sort}
                        onChange={(event) => setSort(event.target.value as PageSort)}
                        className={cn(selectClass, 'hidden shrink-0 sm:block')}
                    >
                        {SORT_OPTIONS.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {canBulk && selectedIds.length > 0 ? (
                <PagesBulkBar
                    pages={allPages}
                    selectedIds={selectedIds}
                    onClear={() => setSelected(new Set())}
                />
            ) : null}

            <div id="pages-results" role="tabpanel" aria-label="Список страниц">
                {pagesQuery.isPending ? <PageLoadingState /> : null}
                {pagesQuery.isError ? (
                    <ErrorState
                        title="Не удалось загрузить страницы"
                        description="Обновите страницу или попробуйте позже."
                    />
                ) : null}
                {pagesQuery.isSuccess && pages.length === 0 ? (
                    <p className="rounded-[14px] border border-line bg-white p-7 text-center text-graphite dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                        {allPages.length === 0
                            ? 'Страниц пока нет. Создайте первую — шаблон подскажет, что заполнить.'
                            : 'Ничего не нашлось. Измените фильтр или поиск.'}
                    </p>
                ) : null}

                {pagesQuery.isSuccess && pages.length > 0 ? (
                    <>
                        <div className="hidden overflow-x-auto rounded-[14px] border border-line bg-white md:block dark:border-slate-800 dark:bg-slate-900">
                            <table className="w-full min-w-[820px] text-sm">
                                <thead>
                                    <tr className="border-b border-line text-left text-xs font-semibold text-graphite dark:border-slate-800 dark:text-slate-400">
                                        <th scope="col" className="w-11 py-3 pl-4">
                                            {canBulk ? (
                                                <Checkbox
                                                    checked={allSelected}
                                                    onCheckedChange={(checked) =>
                                                        setSelected(
                                                            checked
                                                                ? new Set(visibleIds)
                                                                : new Set(),
                                                        )
                                                    }
                                                    ariaLabel="Выбрать все страницы"
                                                />
                                            ) : null}
                                        </th>
                                        <th scope="col" className="w-[38%] p-3">
                                            Страница
                                        </th>
                                        <th scope="col" className="w-[16%] p-3">
                                            Тип
                                        </th>
                                        <th scope="col" className="w-[24%] p-3">
                                            Статус
                                        </th>
                                        <th scope="col" className="w-[16%] p-3">
                                            Изменена
                                        </th>
                                        <th scope="col" className="w-14 py-3 pr-4">
                                            <span className="sr-only">Действия</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {pages.map((page) => {
                                        const isSelected = selected.has(page.id)
                                        return (
                                            <tr
                                                key={page.id}
                                                data-testid="page-row"
                                                className={cn(
                                                    'border-b border-surface-strong last:border-b-0 dark:border-slate-800',
                                                    isSelected
                                                        ? 'bg-brand-50 dark:bg-brand-950/20'
                                                        : '',
                                                )}
                                            >
                                                <td className="py-3 pl-4">
                                                    {canBulk ? (
                                                        <Checkbox
                                                            checked={isSelected}
                                                            onCheckedChange={(checked) =>
                                                                toggle(page.id, checked)
                                                            }
                                                            ariaLabel={`Выбрать «${page.title}»`}
                                                        />
                                                    ) : null}
                                                </td>
                                                <td className="max-w-0 p-3">
                                                    <Link
                                                        to={`/admin/pages/${page.id}`}
                                                        onMouseEnter={preloadPageEditorOnIntent}
                                                        onFocus={preloadPageEditorOnIntent}
                                                        className="block truncate font-semibold text-ink hover:text-brand-700 hover:underline dark:text-slate-100 dark:hover:text-brand-300"
                                                    >
                                                        {page.title}
                                                    </Link>
                                                    <span className="mt-0.5 block truncate font-mono text-xs text-graphite dark:text-slate-400">
                                                        {page.path}
                                                    </span>
                                                </td>
                                                <td className="p-3 text-graphite dark:text-slate-300">
                                                    {pageTypeLabels[page.type] ?? page.type}
                                                </td>
                                                <td className="p-3">
                                                    <span className="flex flex-col items-start gap-1">
                                                        <span className="flex flex-wrap items-center gap-1.5">
                                                            <StatusPill status={page.status} />
                                                            {page.isIndexable ? null : (
                                                                <NoindexMarker />
                                                            )}
                                                        </span>
                                                        {page.hasUnpublishedChanges === true ? (
                                                            <ChangesMarker />
                                                        ) : null}
                                                    </span>
                                                </td>
                                                <td className="max-w-0 p-3 text-graphite dark:text-slate-300">
                                                    <span className="block whitespace-nowrap">
                                                        {formatUpdated(page.updatedAt)}
                                                    </span>
                                                    {page.updatedByName != null ? (
                                                        <span
                                                            className="block truncate text-xs text-graphite dark:text-slate-400"
                                                            title={`Изменил(а): ${page.updatedByName}`}
                                                        >
                                                            {page.updatedByName}
                                                        </span>
                                                    ) : null}
                                                </td>
                                                <td className="py-3 pr-4">
                                                    <PageRowMenu page={page} actions={actions} />
                                                </td>
                                            </tr>
                                        )
                                    })}
                                </tbody>
                            </table>
                        </div>

                        <ul aria-label="Страницы" className="flex flex-col gap-2 md:hidden">
                            {pages.map((page) => (
                                <li key={page.id} data-testid="page-card">
                                    <Link
                                        to={`/admin/pages/${page.id}`}
                                        onFocus={preloadPageEditorOnIntent}
                                        className="flex items-center gap-2.5 rounded-2xl border border-line bg-white p-3.5 dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <span className="flex min-w-0 flex-1 flex-col gap-1">
                                            <span className="truncate font-bold">{page.title}</span>
                                            <span className="truncate font-mono text-xs text-graphite dark:text-slate-400">
                                                {page.path}
                                            </span>
                                            <span className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1.5 text-xs text-graphite dark:text-slate-400">
                                                <StatusPill status={page.status} />
                                                <span>
                                                    {formatUpdated(page.updatedAt)}
                                                    {page.updatedByName != null
                                                        ? ` · ${page.updatedByName}`
                                                        : ''}
                                                </span>
                                                {page.hasUnpublishedChanges === true ? (
                                                    <ChangesMarker short />
                                                ) : null}
                                                {page.isIndexable ? null : <NoindexMarker />}
                                            </span>
                                        </span>
                                        <span
                                            aria-hidden="true"
                                            className="-rotate-90 text-graphite/60"
                                        >
                                            <NavIcon name="chevron" size={18} />
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </>
                ) : null}
            </div>

            {creating ? <PageCreateDialog onClose={() => void navigate('/admin/pages')} /> : null}
        </div>
    )
}
