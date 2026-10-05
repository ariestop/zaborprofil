import type { LeadStatus } from '../../types/api'

/** Цвета плашки статуса из макета «Заявки»: фон и текст. */
export const LEAD_PILL_STYLES: Record<LeadStatus, string> = {
    new: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200',
    in_progress: 'bg-indigo-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    done: 'bg-brand-100 text-brand-800 dark:bg-green-900/40 dark:text-green-200',
    spam: 'bg-surface-strong text-graphite dark:bg-slate-800 dark:text-slate-300',
}

const LEGAL_FORMS = new Set(['ооо', 'ип', 'ао', 'зао', 'пао', 'оао', 'тоо'])

/** Инициалы для аватара: «Андрей Смирнов» → «АС», «ООО «СтройДвор»» → «СД», без букв → «?». */
export function leadInitials(name: string): string {
    const words = name
        .split(/[\s«»"'()[\]-]+/u)
        .map((word) => word.replace(/[^\p{L}]/gu, ''))
        .filter((word) => word !== '' && !LEGAL_FORMS.has(word.toLowerCase()))

    const first = words[0]
    if (first === undefined) {
        return '?'
    }

    const second = words[1]
    // «СтройДвор» — одно слово из двух частей: берём заглавные буквы, иначе первые две буквы.
    const capitals = Array.from(first).filter((char) => char !== char.toLowerCase())
    const initials =
        second === undefined
            ? (capitals.length >= 2 ? capitals.slice(0, 2) : Array.from(first).slice(0, 2)).join('')
            : `${Array.from(first)[0]}${Array.from(second)[0]}`

    return initials.toLocaleUpperCase('ru-RU')
}

function startOfDay(date: Date): number {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime()
}

function dayDifference(date: Date, now: Date): number {
    return Math.round((startOfDay(now) - startOfDay(date)) / 86_400_000)
}

function parseDate(value: string): Date | null {
    const date = new Date(value)

    return Number.isNaN(date.getTime()) ? null : date
}

const timeFormat: Intl.DateTimeFormatOptions = { hour: '2-digit', minute: '2-digit' }

/** Время в списке: сегодня — «15:20», вчера — «Вчера», раньше — «2 окт.». */
export function formatLeadListTime(value: string, now: Date = new Date()): string {
    const date = parseDate(value)
    if (date === null) {
        return '—'
    }

    const days = dayDifference(date, now)
    if (days === 0) {
        return date.toLocaleTimeString('ru-RU', timeFormat)
    }
    if (days === 1) {
        return 'Вчера'
    }

    return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })
}

/** Время в карточке: «Сегодня, 15:20», «Вчера, 17:12», «2 октября, 09:15». */
export function formatLeadReceived(value: string, now: Date = new Date()): string {
    const date = parseDate(value)
    if (date === null) {
        return '—'
    }

    const time = date.toLocaleTimeString('ru-RU', timeFormat)
    const days = dayDifference(date, now)
    if (days === 0) {
        return `Сегодня, ${time}`
    }
    if (days === 1) {
        return `Вчера, ${time}`
    }

    return `${date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' })}, ${time}`
}

/** Время события в истории: только часы и минуты, для событий не сегодняшних — ещё и дата. */
export function formatLeadEventTime(value: string, now: Date = new Date()): string {
    const date = parseDate(value)
    if (date === null) {
        return '—'
    }

    const time = date.toLocaleTimeString('ru-RU', timeFormat)

    return dayDifference(date, now) === 0
        ? time
        : `${date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' })}, ${time}`
}

/** Путь страницы, с которой отправлена заявка: полный адрес сокращается до `/путь`. */
export function leadPagePath(pageUrl: string | null): string {
    if (pageUrl === null || pageUrl.trim() === '') {
        return '—'
    }

    try {
        const url = new URL(pageUrl, 'https://placeholder.invalid')

        return url.pathname || '/'
    } catch {
        return pageUrl
    }
}

/** UTM-метки в виде «yandex / cpc / zabor-proflist». */
export function leadUtmText(utm: Record<string, string>): string {
    const parts = ['source', 'medium', 'campaign', 'term', 'content']
        .map((key) => utm[key])
        .filter((value): value is string => typeof value === 'string' && value !== '')

    return parts.length === 0 ? '—' : parts.join(' / ')
}

/** Спам-балл сервера (0–100 и выше) приводится к шкале «из 10». */
export function spamScoreOutOfTen(score: number): number {
    return Math.min(10, Math.max(0, Math.round(score / 10)))
}

export interface SpamVerdict {
    title: string
    text: string
    color: string
    barColor: string
    percent: number
}

export function describeSpam(score: number, reasons: string[]): SpamVerdict {
    const value = spamScoreOutOfTen(score)
    const base = `Спам-балл ${value} из 10`

    if (value >= 7) {
        return {
            title: 'Высокий риск спама',
            text: reasons.length > 0 ? `${base}: ${reasons.join(', ')}` : base,
            color: 'text-danger dark:text-red-400',
            barColor: 'bg-danger',
            percent: Math.max(4, value * 10),
        }
    }

    if (value > 0) {
        return {
            title: 'Низкий риск',
            text: base,
            color: 'text-amber-700 dark:text-amber-400',
            barColor: 'bg-amber-700',
            percent: Math.max(4, value * 10),
        }
    }

    return {
        title: 'Чисто',
        text: base,
        color: 'text-brand-800 dark:text-green-400',
        barColor: 'bg-brand-800',
        percent: 4,
    }
}

/** Дата в формате YYYY-MM-DD в местном часовом поясе. */
export function localIsoDate(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${date.getFullYear()}-${month}-${day}`
}

export type LeadPeriod = 'all' | 'today' | 'yesterday' | '7d' | '30d' | 'custom'

export function periodRange(
    period: Exclude<LeadPeriod, 'all' | 'custom'>,
    now: Date = new Date(),
): { from: string; to: string } {
    const day = (offset: number) =>
        localIsoDate(new Date(now.getFullYear(), now.getMonth(), now.getDate() - offset))

    switch (period) {
        case 'today':
            return { from: day(0), to: day(0) }
        case 'yesterday':
            return { from: day(1), to: day(1) }
        case '7d':
            return { from: day(6), to: day(0) }
        default:
            return { from: day(29), to: day(0) }
    }
}

export function detectPeriod(from: string, to: string, now: Date = new Date()): LeadPeriod {
    if (from === '' && to === '') {
        return 'all'
    }

    for (const period of ['today', 'yesterday', '7d', '30d'] as const) {
        const range = periodRange(period, now)
        if (range.from === from && range.to === to) {
            return period
        }
    }

    return 'custom'
}
