import { z } from 'zod'
import { ApiError } from '../../../shared/api/client'

export const REDIRECT_STATUS_CODES = [301, 302, 307, 308] as const

export const REDIRECT_STATUS_LABELS: Record<number, string> = {
  301: '301 — постоянный',
  302: '302 — временный',
  307: '307 — временный (метод сохраняется)',
  308: '308 — постоянный (метод сохраняется)',
}

const RESERVED_SOURCE_PREFIXES = ['/admin', '/build', '/health', '/uploads', '/_profiler', '/_wdt']

export function normalizeSourceInput(value: string): string {
  const trimmed = value.trim()

  return trimmed.startsWith('/') || trimmed === '' ? trimmed : `/${trimmed}`
}

export function isExternalUrl(value: string): boolean {
  return /^https?:\/\//i.test(value.trim())
}

/** Читаемый вид пути для таблицы: %D0%B7... -> «з...». Невалидные последовательности остаются как есть. */
export function decodeForDisplay(value: string): string {
  try {
    return decodeURI(value)
  } catch {
    return value
  }
}

export const redirectFormSchema = z.object({
  sourcePath: z.string()
    .trim()
    .min(1, 'Укажите исходный URL')
    .refine((value) => !/[?#]/.test(value), 'Без параметров запроса и якоря (? и #): сервер сопоставляет только путь')
    .refine((value) => !isExternalUrl(value), 'Исходный URL — путь без домена, например /old-page/')
    .refine((value) => isExternalUrl(value) || !normalizeSourceInput(value).includes('//'), 'Двойной слэш в пути недопустим')
    .refine(
      (value) => !RESERVED_SOURCE_PREFIXES.some((prefix) => normalizeSourceInput(value).startsWith(prefix)),
      'Служебные разделы (/admin, /build, /health, /uploads) не обрабатываются редиректами',
    ),
  targetPath: z.string()
    .trim()
    .min(1, 'Укажите целевой URL')
    .refine((value) => !value.startsWith('//'), 'Не используйте // в начале: укажите путь от корня или https://...')
    .refine(
      (value) => !/^[a-z][a-z0-9+.-]*:/i.test(value) || isExternalUrl(value),
      'Для внешнего адреса допустимы только http:// и https://',
    )
    .refine((value) => !/^\/admin(\/|$)/.test(value), 'Редирект на админ-панель запрещён'),
  statusCode: z.string().refine((value) => REDIRECT_STATUS_CODES.some((code) => String(code) === value), 'Недопустимый код'),
  isActive: z.boolean(),
}).refine(
  (data) => normalizeSourceInput(data.sourcePath) !== data.targetPath.trim().split(/[?#]/)[0],
  { message: 'Источник и цель совпадают', path: ['targetPath'] },
)

export type RedirectFormValues = z.infer<typeof redirectFormSchema>

/** Человекочитаемое сообщение для toast из ответа API ({error, code, details}). */
export function describeApiError(error: unknown, fallback: string): string {
  if (error instanceof ApiError && typeof error.payload === 'object' && error.payload !== null) {
    const payload = error.payload as { error?: unknown }
    if (typeof payload.error === 'string' && payload.error !== '') {
      return payload.error
    }
  }

  return fallback
}

const CSV_TEMPLATE = 'source,target,status,active\n/old-fences/,/fences/,301,1\n/old-gates/,/gates/,301,1\n'

export function redirectCsvTemplate(): string {
  return CSV_TEMPLATE
}

function csvCell(value: string | number): string {
  const raw = String(value)
  const text = /^[=+\-@\t\r]/.test(raw) ? `'${raw}` : raw

  return /[",\n;]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text
}

export function buildImportErrorsCsv(errors: Array<{ line: number, source: string, message: string }>): string {
  const rows = errors.map((error) => [error.line, error.source, error.message].map(csvCell).join(','))

  return ['line,source,error', ...rows].join('\n') + '\n'
}
