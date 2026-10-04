export const TITLE_MIN_LENGTH = 10
export const TITLE_RECOMMENDED_MAX_LENGTH = 60
export const TITLE_HARD_MAX_LENGTH = 255
export const DESCRIPTION_MIN_LENGTH = 80
export const DESCRIPTION_RECOMMENDED_MAX_LENGTH = 155
export const DESCRIPTION_HARD_MAX_LENGTH = 320

export type LengthStatus = 'empty' | 'short' | 'good' | 'long' | 'over-limit'

export interface LengthLimits {
  min: number
  max: number
  hardMax: number
}

export interface LengthEvaluation {
  length: number
  status: LengthStatus
  hint: string
}

export const titleLimits: LengthLimits = {
  min: TITLE_MIN_LENGTH,
  max: TITLE_RECOMMENDED_MAX_LENGTH,
  hardMax: TITLE_HARD_MAX_LENGTH,
}

export const descriptionLimits: LengthLimits = {
  min: DESCRIPTION_MIN_LENGTH,
  max: DESCRIPTION_RECOMMENDED_MAX_LENGTH,
  hardMax: DESCRIPTION_HARD_MAX_LENGTH,
}

export function evaluateLength(value: string, limits: LengthLimits): LengthEvaluation {
  const length = Array.from(value.trim()).length

  if (length === 0) {
    return { length, status: 'empty', hint: 'Поле пустое.' }
  }

  if (length > limits.hardMax) {
    return {
      length,
      status: 'over-limit',
      hint: `Превышен предел ${limits.hardMax} символов — сохранение будет отклонено.`,
    }
  }

  if (length > limits.max) {
    return {
      length,
      status: 'long',
      hint: `Длиннее ${limits.max} символов: поисковик, скорее всего, обрежет текст многоточием.`,
    }
  }

  if (length < limits.min) {
    return {
      length,
      status: 'short',
      hint: `Короче ${limits.min} символов: добавьте ключевые слова и уточнение.`,
    }
  }

  return { length, status: 'good', hint: 'Длина в норме.' }
}

export interface TitleSources {
  metaTitle: string
  title: string
  h1: string
  template: string
  siteName: string
}

/** Повторяет правила SeoTitleResolver на бэкенде: явный metaTitle главнее шаблона. */
export function resolveSeoTitle({ metaTitle, title, h1, template, siteName }: TitleSources): string {
  const explicit = metaTitle.trim()
  if (explicit !== '') {
    return explicit
  }

  const trimmedTemplate = template.trim()
  if (trimmedTemplate === '') {
    return title
  }

  const resolved = trimmedTemplate
    .split('{title}').join(title)
    .split('{h1}').join(h1)
    .split('{site_name}').join(siteName.trim())
    .trim()

  return resolved === '' ? title : resolved
}

export function truncateForSnippet(value: string, limit: number): string {
  const characters = Array.from(value.trim())
  if (characters.length <= limit) {
    return characters.join('')
  }

  return `${characters.slice(0, limit - 1).join('').trimEnd()}…`
}

export interface SnippetUrlParts {
  host: string
  crumbs: string[]
}

export function buildSnippetUrl(origin: string, path: string, canonicalUrl = ''): SnippetUrlParts {
  const source = canonicalUrl.trim() !== '' ? canonicalUrl.trim() : `${origin}${path}`

  try {
    const url = new URL(source, origin)
    return {
      host: url.host,
      crumbs: url.pathname.split('/').filter((segment) => segment !== ''),
    }
  } catch {
    return { host: origin, crumbs: [] }
  }
}
