import type { PageStatus } from '../../entities/page/api'
import type { ContentPageItem, PageTemplateBlock, PageTemplateItem } from '../../types/api'

export type StatusFilter = 'all' | PageStatus
export type PageSort = 'updated' | 'path' | 'title'

interface StatusTab {
  id: StatusFilter
  label: string
  /** Редкие статусы показываются вкладкой, только когда такие страницы есть. */
  always: boolean
}

const STATUS_TABS: StatusTab[] = [
  { id: 'all', label: 'Все', always: true },
  { id: 'draft', label: 'Черновики', always: true },
  { id: 'review', label: 'На проверке', always: true },
  { id: 'approved', label: 'Одобрены', always: true },
  { id: 'scheduled', label: 'Запланированы', always: false },
  { id: 'published', label: 'Опубликованы', always: true },
  { id: 'unpublished', label: 'Сняты', always: true },
  { id: 'archived', label: 'В архиве', always: false },
  { id: 'deleted', label: 'Удалённые', always: false },
]

export const SORT_OPTIONS: Array<{ id: PageSort, label: string }> = [
  { id: 'updated', label: 'Сначала изменённые' },
  { id: 'path', label: 'По адресу' },
  { id: 'title', label: 'По названию' },
]

/** «Все» — без удалённых: удалённые доступны отдельной вкладкой. */
function matchesStatus(page: ContentPageItem, filter: StatusFilter): boolean {
  return filter === 'all' ? page.status !== 'deleted' : page.status === filter
}

export function statusTabs(pages: ContentPageItem[], active: StatusFilter): Array<StatusTab & { count: number }> {
  return STATUS_TABS
    .map((tab) => ({ ...tab, count: pages.filter((page) => matchesStatus(page, tab.id)).length }))
    .filter((tab) => tab.always || tab.count > 0 || tab.id === active)
}

export const STATUS_PILL: Record<PageStatus, string> = {
  draft: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-200',
  review: 'bg-violet-100 text-violet-800 dark:bg-violet-950/60 dark:text-violet-200',
  approved: 'bg-indigo-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-200',
  scheduled: 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-200',
  published: 'bg-brand-100 text-brand-800 dark:bg-brand-950/60 dark:text-brand-200',
  unpublished: 'bg-surface-strong text-graphite dark:bg-slate-800 dark:text-slate-300',
  archived: 'bg-surface-strong text-graphite dark:bg-slate-800 dark:text-slate-300',
  deleted: 'bg-danger-50 text-danger dark:bg-red-950/60 dark:text-red-200',
}

function updatedTime(page: ContentPageItem): number {
  const time = page.updatedAt == null ? Number.NaN : new Date(page.updatedAt).getTime()

  return Number.isNaN(time) ? 0 : time
}

export function visiblePages(
  pages: ContentPageItem[],
  { status, query, type, sort }: { status: StatusFilter, query: string, type: string, sort: PageSort },
): ContentPageItem[] {
  const needle = query.trim().toLowerCase()
  const byPath = (left: ContentPageItem, right: ContentPageItem) => left.path.localeCompare(right.path)
  const comparators: Record<PageSort, (left: ContentPageItem, right: ContentPageItem) => number> = {
    updated: (left, right) => updatedTime(right) - updatedTime(left) || byPath(left, right),
    path: byPath,
    title: (left, right) => left.title.localeCompare(right.title, 'ru') || byPath(left, right),
  }

  return pages
    .filter((page) => matchesStatus(page, status))
    .filter((page) => type === '' || page.type === type)
    .filter((page) => needle === '' || `${page.title} ${page.path}`.toLowerCase().includes(needle))
    .sort(comparators[sort])
}

export function plural(count: number, one: string, few: string, many: string): string {
  const mod10 = count % 10
  const mod100 = count % 100
  if (mod10 === 1 && mod100 !== 11) {
    return one
  }
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
    return few
  }

  return many
}

/** «11 страниц · опубликовано 6 · с неопубликованными правками 1». */
export function pagesSummary(pages: ContentPageItem[]): string {
  const live = pages.filter((page) => page.status !== 'deleted')
  const published = live.filter((page) => page.status === 'published').length
  const changed = live.filter((page) => page.hasUnpublishedChanges === true).length
  const parts = [`${live.length} ${plural(live.length, 'страница', 'страницы', 'страниц')}`, `опубликовано ${published}`]
  if (changed > 0) {
    parts.push(`с неопубликованными правками ${changed}`)
  }

  return parts.join(' · ')
}

function startOfDay(date: Date): number {
  return new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime()
}

/** «сегодня, 14:32», «вчера, 18:40», «2 окт., 10:20», для прошлых лет — «12 мар. 2025 г.». */
export function formatUpdated(value: string | null | undefined, now: Date = new Date()): string {
  const date = value == null ? null : new Date(value)
  if (date === null || Number.isNaN(date.getTime())) {
    return '—'
  }

  const time = date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
  const days = Math.round((startOfDay(now) - startOfDay(date)) / 86_400_000)
  if (days === 0) {
    return `сегодня, ${time}`
  }
  if (days === 1) {
    return `вчера, ${time}`
  }
  if (date.getFullYear() === now.getFullYear()) {
    return `${date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })}, ${time}`
  }

  return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' })
}

export interface TemplateBar {
  height: number
  width: string
  color: string
}

const BARS: Array<{ test: RegExp, bar: TemplateBar }> = [
  { test: /^hero|slider/, bar: { height: 16, width: '100%', color: 'var(--color-graphite)' } },
  { test: /price|pricing|calculator/, bar: { height: 9, width: '100%', color: 'var(--color-badge)' } },
  { test: /form|lead|callback|contacts?$/, bar: { height: 9, width: '70%', color: 'var(--color-graphite)' } },
  { test: /^cta/, bar: { height: 7, width: '100%', color: 'var(--color-brand-700)' } },
  { test: /gallery|portfolio|works|before-after/, bar: { height: 11, width: '100%', color: 'var(--color-line-strong)' } },
  { test: /feature|advantage|benefit|steps|process|fence-types/, bar: { height: 9, width: '100%', color: 'var(--color-brand-200)' } },
  { test: /faq/, bar: { height: 6, width: '100%', color: 'var(--color-line-strong)' } },
]

const TEXT_BAR: TemplateBar = { height: 5, width: '86%', color: 'var(--color-line-strong)' }

function sortedBlocks(template: PageTemplateItem): PageTemplateBlock[] {
  return [...template.blocksSchema].sort((left, right) => left.position - right.position)
}

/** Схематичная миниатюра шаблона: полоса на каждый блок, цвет и высота по виду блока. */
export function templateBars(template: PageTemplateItem): TemplateBar[] {
  return sortedBlocks(template).map((block) => BARS.find((item) => item.test.test(block.type))?.bar ?? TEXT_BAR)
}

export function templateBlockNames(template: PageTemplateItem): string[] {
  return sortedBlocks(template).map((block) => block.name)
}

export function blocksCount(count: number): string {
  return count === 0 ? 'без блоков' : `${count} ${plural(count, 'блок', 'блока', 'блоков')}`
}
