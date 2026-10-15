import { slugify } from '../page-editor/form'
import type {
  PortfolioCategory,
  PortfolioMedia,
  PortfolioProject,
  PortfolioProjectPayload,
  PortfolioProjectSummary,
  PortfolioSpec,
} from '../../entities/portfolio/api'

export { slugify }

/** Лимиты — те же, что проверяет сервер (PortfolioProject, PortfolioMediaDraft). */
export const PORTFOLIO_LIMITS = {
  title: 200,
  place: 160,
  categoryTitle: 180,
  categoryShortTitle: 60,
  summary: 300,
  caption: 255,
  specs: 8,
  specText: 40,
  media: 60,
  seoTitle: 255,
  seoDescription: 320,
  categoryDescription: 500,
} as const

export interface ProjectDraft {
  categoryId: string
  title: string
  slug: string
  place: string
  year: string
  summary: string
  description: string
  specs: PortfolioSpec[]
  media: PortfolioMedia[]
  seoTitle: string
  seoDescription: string
}

export function emptyProjectDraft(categoryId = ''): ProjectDraft {
  return {
    categoryId,
    title: '',
    slug: '',
    place: '',
    year: String(new Date().getFullYear()),
    summary: '',
    description: '',
    specs: [],
    media: [],
    seoTitle: '',
    seoDescription: '',
  }
}

export function projectToDraft(project: PortfolioProject): ProjectDraft {
  return {
    categoryId: project.categoryId,
    title: project.title,
    slug: project.slug,
    place: project.place ?? '',
    year: project.year === null ? '' : String(project.year),
    summary: project.summary ?? '',
    description: project.description,
    specs: project.specs.map((spec) => ({ ...spec })),
    media: project.media.map((item) => ({ ...item })),
    seoTitle: project.seoTitle ?? '',
    seoDescription: project.seoDescription ?? '',
  }
}

const nullable = (value: string): string | null => (value.trim() === '' ? null : value.trim())

export function draftToPayload(draft: ProjectDraft): PortfolioProjectPayload {
  const year = draft.year.trim() === '' ? null : Number.parseInt(draft.year, 10)

  return {
    categoryId: draft.categoryId,
    title: draft.title.trim(),
    slug: draft.slug.trim() === '' ? slugify(draft.title) : draft.slug.trim(),
    place: nullable(draft.place),
    year: year === null || Number.isNaN(year) ? null : year,
    summary: nullable(draft.summary),
    description: isEmptyHtml(draft.description) ? '' : draft.description,
    specs: draft.specs
      .map((spec) => ({ label: spec.label.trim(), value: spec.value.trim() }))
      .filter((spec) => spec.label !== '' || spec.value !== ''),
    media: draft.media.map((item) => ({
      kind: item.kind,
      src: item.src,
      poster: item.kind === 'photo' ? null : item.poster,
      caption: item.caption === null ? null : nullable(item.caption),
      isCover: item.kind === 'photo' && item.isCover,
    })),
    seoTitle: nullable(draft.seoTitle),
    seoDescription: nullable(draft.seoDescription),
  }
}

export function isEmptyHtml(html: string): boolean {
  return html.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim() === ''
}

/** Ошибки формы до отправки; сервер проверяет то же самое и отвечает текстом ошибки. */
export function draftErrors(draft: ProjectDraft): Partial<Record<'categoryId' | 'title' | 'slug' | 'year' | 'specs', string>> {
  const errors: Partial<Record<'categoryId' | 'title' | 'slug' | 'year' | 'specs', string>> = {}
  if (draft.categoryId === '') {
    errors.categoryId = 'Выберите раздел портфолио'
  }
  if (draft.title.trim() === '') {
    errors.title = 'Укажите название работы'
  }
  const slug = draft.slug.trim() === '' ? slugify(draft.title) : draft.slug.trim()
  if (draft.title.trim() !== '' && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) {
    errors.slug = 'Только латиница, цифры и дефисы, например zabor-v-zavodskom'
  }
  if (draft.year.trim() !== '') {
    const year = Number(draft.year)
    if (!Number.isInteger(year) || year < 1990 || year > 2100) {
      errors.year = 'Год — число от 1990 до 2100'
    }
  }
  if (draft.specs.some((spec) => spec.label.trim().length > PORTFOLIO_LIMITS.specText || spec.value.trim().length > PORTFOLIO_LIMITS.specText)) {
    errors.specs = `Название и значение характеристики — не длиннее ${PORTFOLIO_LIMITS.specText} символов`
  }

  return errors
}

/** Главное фото: отмеченное звёздочкой, иначе первое фото в галерее (так же решает сервер). */
export function coverIndex(media: PortfolioMedia[]): number {
  const marked = media.findIndex((item) => item.kind === 'photo' && item.isCover)

  return marked !== -1 ? marked : media.findIndex((item) => item.kind === 'photo')
}

export function setCover(media: PortfolioMedia[], index: number): PortfolioMedia[] {
  return media.map((item, position) => ({ ...item, isCover: item.kind === 'photo' && position === index }))
}

let mediaKeyCounter = 0

/** Новый элемент галереи с ключом для списка (у сохранённых ключ — id с сервера). */
export function newMedia(kind: PortfolioMedia['kind'], src: string): PortfolioMedia {
  mediaKeyCounter += 1

  return { kind, src, poster: null, caption: null, isCover: false, clientKey: `new-${mediaKeyCounter}` }
}

export function mediaKey(item: PortfolioMedia, index: number): string {
  return item.id ?? item.clientKey ?? `index-${index}`
}

export function moveMedia<T>(items: T[], from: number, to: number): T[] {
  if (from === to || from < 0 || to < 0 || from >= items.length || to >= items.length) {
    return items
  }
  const next = [...items]
  const [moved] = next.splice(from, 1)
  next.splice(to, 0, moved)

  return next
}

export interface EmbedPreview {
  provider: 'rutube' | 'vk'
  embedUrl: string
}

/** Та же нормализация ссылки, что на сервере (VideoEmbedUrl): обычная ссылка со страницы видео → адрес плеера. */
export function parseVideoLink(input: string): EmbedPreview | null {
  const raw = input.trim()
  if (raw === '') {
    return null
  }
  let url: URL
  try {
    url = new URL(/^https?:\/\//i.test(raw) ? raw : `https://${raw}`)
  } catch {
    return null
  }
  const host = url.hostname.toLowerCase().replace(/^(www\.|m\.)/, '')
  const path = url.pathname

  if (host === 'rutube.ru') {
    const match = /^\/(?:play\/embed|video(?:\/private)?|shorts)\/([0-9a-f]{32})\/?$/i.exec(path)
    if (match !== null) {
      const key = url.searchParams.get('p')
      const suffix = key !== null && /^[\w-]+$/.test(key) ? `?p=${key}` : ''
      return { provider: 'rutube', embedUrl: `https://rutube.ru/play/embed/${match[1].toLowerCase()}/${suffix}` }
    }
  }

  if (host === 'vk.com' || host === 'vkvideo.ru' || host === 'vk.ru') {
    const hash = url.searchParams.get('hash')
    const vk = (owner: string, id: string): EmbedPreview => ({
      provider: 'vk',
      embedUrl: `https://vkvideo.ru/video_ext.php?oid=${owner}&id=${id}${hash !== null && /^[0-9a-f]+$/i.test(hash) ? `&hash=${hash}` : ''}&hd=2`,
    })
    const owner = url.searchParams.get('oid')
    const id = url.searchParams.get('id')
    if (path === '/video_ext.php' && owner !== null && id !== null && /^-?\d+$/.test(owner) && /^\d+$/.test(id)) {
      return vk(owner, id)
    }
    const z = url.searchParams.get('z')
    const target = z !== null ? `/${z.split('/')[0]}` : path
    const match = /^\/(?:video|clip)(-?\d+)_(\d+)/.exec(target)
    if (match !== null) {
      return vk(match[1], match[2])
    }
  }

  return null
}

export function providerLabel(src: string): string {
  return src.includes('rutube.ru') ? 'Rutube' : 'VK Видео'
}

/** Работы раздела в порядке вывода на сайте. */
export function projectsOf(projects: PortfolioProjectSummary[], categoryId: string): PortfolioProjectSummary[] {
  return projects.filter((project) => project.categoryId === categoryId).sort((left, right) => left.sortOrder - right.sortOrder)
}

export function sortedCategories(categories: PortfolioCategory[]): PortfolioCategory[] {
  return [...categories].sort((left, right) => left.sortOrder - right.sortOrder)
}

function plural(count: number, one: string, few: string, many: string): string {
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

export function mediaCountLabel(photos: number, videos: number): string {
  const parts = [`${photos} ${plural(photos, 'фото', 'фото', 'фото')}`]
  if (videos > 0) {
    parts.push(`${videos} ${plural(videos, 'видео', 'видео', 'видео')}`)
  }

  return parts.join(' · ')
}

export function projectsCountLabel(count: number): string {
  return `${count} ${plural(count, 'работа', 'работы', 'работ')}`
}
