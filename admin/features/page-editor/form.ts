import { z } from 'zod'
import type { PageSeoUpdatePayload, PageUpdatePayload } from '../../entities/page/api'
import type { ContentPageItem } from '../../types/api'

export const PAGE_SETTINGS_FIELDS = ['type', 'title', 'h1', 'slug', 'path', 'template', 'sortOrder', 'parentId', 'visibility', 'isIndexable'] as const
export const PAGE_SEO_FIELDS = ['metaTitle', 'metaDescription', 'canonicalUrl', 'ogTitle', 'ogDescription', 'ogImage', 'jsonLd'] as const

export type PageSettingsField = (typeof PAGE_SETTINGS_FIELDS)[number]
export type PageSeoField = (typeof PAGE_SEO_FIELDS)[number]

export function parseJsonLd(text: string): Record<string, unknown>[] | null {
  if (text.trim() === '') {
    return null
  }

  const decoded = JSON.parse(text) as unknown
  if (!Array.isArray(decoded) || decoded.some((item) => item === null || typeof item !== 'object' || Array.isArray(item))) {
    throw new Error('JSON-LD должен быть массивом объектов.')
  }

  return decoded as Record<string, unknown>[]
}

function isValidJsonLd(text: string): boolean {
  try {
    parseJsonLd(text)
    return true
  } catch {
    return false
  }
}

export const pageEditorSchema = z.object({
  type: z.string().min(1, 'Выберите тип страницы'),
  title: z.string().trim().min(2, 'Название слишком короткое'),
  h1: z.string().trim().min(1, 'Укажите заголовок H1'),
  slug: z.string().trim().min(1, 'Укажите slug').refine((value) => !value.includes('/'), 'Slug не должен содержать «/»'),
  path: z.string().trim().min(1, 'Укажите адрес страницы').refine((value) => value.startsWith('/'), 'Адрес должен начинаться с «/»'),
  template: z.string().min(1, 'Выберите шаблон'),
  sortOrder: z.number('Укажите число').int('Укажите целое число'),
  parentId: z.string(),
  visibility: z.enum(['public', 'hidden', 'unlisted']),
  isIndexable: z.boolean(),
  metaTitle: z.string().max(255, 'Не больше 255 символов'),
  metaDescription: z.string().max(320, 'Не больше 320 символов'),
  canonicalUrl: z.string(),
  ogTitle: z.string(),
  ogDescription: z.string(),
  ogImage: z.string(),
  jsonLd: z.string().refine(isValidJsonLd, 'JSON-LD должен быть валидным JSON-массивом объектов'),
})

export type PageEditorFormValues = z.infer<typeof pageEditorSchema>

export function pageToFormValues(page: ContentPageItem): PageEditorFormValues {
  return {
    type: page.type,
    title: page.title,
    h1: page.h1,
    slug: page.slug,
    path: page.path,
    template: page.template,
    sortOrder: page.sortOrder,
    parentId: page.parentId ?? '',
    visibility: page.visibility,
    isIndexable: page.isIndexable,
    metaTitle: page.seo.metaTitle ?? '',
    metaDescription: page.seo.metaDescription ?? '',
    canonicalUrl: page.seo.canonicalUrl ?? '',
    ogTitle: page.seo.ogTitle ?? '',
    ogDescription: page.seo.ogDescription ?? '',
    ogImage: page.seo.ogImage ?? '',
    jsonLd: page.seo.jsonLd === null || page.seo.jsonLd.length === 0 ? '' : JSON.stringify(page.seo.jsonLd, null, 2),
  }
}

export function hasFieldChanges(
  current: PageEditorFormValues,
  saved: PageEditorFormValues,
  fields: readonly (keyof PageEditorFormValues)[],
): boolean {
  return fields.some((field) => current[field] !== saved[field])
}

export function pickFields(
  source: PageEditorFormValues,
  target: PageEditorFormValues,
  fields: readonly (keyof PageEditorFormValues)[],
): PageEditorFormValues {
  const next = { ...target } as Record<keyof PageEditorFormValues, unknown>
  for (const field of fields) {
    next[field] = source[field]
  }

  return next as PageEditorFormValues
}

export type EditorTab = 'content' | 'seo' | 'settings' | 'revisions'

export function tabForField(field: string): EditorTab {
  return (PAGE_SEO_FIELDS as readonly string[]).includes(field) ? 'seo' : 'settings'
}

export function emptyFormValues(): PageEditorFormValues {
  return {
    type: 'landing',
    title: '',
    h1: '',
    slug: '',
    path: '',
    template: 'default',
    sortOrder: 0,
    parentId: '',
    visibility: 'public',
    isIndexable: true,
    metaTitle: '',
    metaDescription: '',
    canonicalUrl: '',
    ogTitle: '',
    ogDescription: '',
    ogImage: '',
    jsonLd: '',
  }
}

export function toPagePayload(values: PageEditorFormValues): PageUpdatePayload {
  return {
    type: values.type,
    title: values.title.trim(),
    slug: values.slug.trim(),
    path: values.path.trim(),
    h1: values.h1.trim(),
    template: values.template,
    sortOrder: values.sortOrder,
    isIndexable: values.isIndexable,
    parentId: values.parentId === '' ? null : values.parentId,
    visibility: values.visibility,
  }
}

function nullIfBlank(value: string): string | null {
  return value.trim() === '' ? null : value.trim()
}

export function toSeoPayload(values: PageEditorFormValues): PageSeoUpdatePayload {
  return {
    metaTitle: nullIfBlank(values.metaTitle),
    metaDescription: nullIfBlank(values.metaDescription),
    canonicalUrl: nullIfBlank(values.canonicalUrl),
    ogTitle: nullIfBlank(values.ogTitle),
    ogDescription: nullIfBlank(values.ogDescription),
    ogImage: nullIfBlank(values.ogImage),
    ogType: 'website',
    jsonLd: parseJsonLd(values.jsonLd),
  }
}

const TRANSLIT: Record<string, string> = {
  а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k', л: 'l', м: 'm',
  н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch',
  ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
}

export function slugify(input: string): string {
  const transliterated = Array.from(input.toLowerCase())
    .map((char) => TRANSLIT[char] ?? char)
    .join('')

  return transliterated
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

export function pathFromSlug(slug: string, parentPath = ''): string {
  if (slug === '') {
    return ''
  }

  const base = parentPath.replace(/\/+$/, '')
  return `${base}/${slug}/`
}
