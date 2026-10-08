/**
 * Сводка блока «Конфигуратор забора» для редактора: что увидит посетитель и что не попадёт на сайт.
 * Правила те же, что в templates/public/blocks/fence-configurator.html.twig и assets/site/configurator.ts:
 * материал и монтаж считаются за м², итог округляется до 100 ₽. Модуль самостоятельный (без импорта block-kinds),
 * чтобы block-kinds мог использовать его для подписи в структуре страницы.
 */

type Content = Record<string, unknown>

export interface FenceGradeRow {
    title: string
    price: number
    /** Материал + монтаж серии, ₽ за м²; 0 — цена не задана. */
    total: number
    details: string
}

export interface FenceSeriesGroup {
    title: string
    hint: string
    montage: number
    grades: FenceGradeRow[]
}

export interface FenceSummary {
    series: FenceSeriesGroup[]
    heights: number[]
    colors: Array<{ label: string; hex: string }>
    length: { min: number; max: number; default: number }
    gateEnabled: boolean
    freeItems: string[]
    /** Пример сметы: первое покрытие с ценой, 1,8 м (или ближайшая высота), длина по умолчанию. */
    example: { title: string; area: number; total: number; length: number; height: number } | null
    /** Что не попадёт на сайт или будет выглядеть неверно. */
    warnings: string[]
}

const isRecord = (value: unknown): value is Content =>
    typeof value === 'object' && value !== null && !Array.isArray(value)
const str = (value: unknown): string => (typeof value === 'string' ? value.trim() : '')
const num = (value: unknown): number =>
    typeof value === 'number' && Number.isFinite(value) ? value : 0
const list = (value: unknown): Content[] => (Array.isArray(value) ? value.filter(isRecord) : [])

export function formatRub(value: number): string {
    return `${Math.round(value).toLocaleString('ru-RU')} ₽`
}

export function formatMeters(value: number): string {
    return `${value.toFixed(1).replace('.', ',')} м`
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

export function fenceSummary(content: Content): FenceSummary {
    const warnings: string[] = []
    const rawSeries = list(content.series).filter((item) => str(item.title) !== '')
    // Без серий покрытия относятся к одной безымянной серии (как на сайте).
    const series: FenceSeriesGroup[] =
        rawSeries.length > 0
            ? rawSeries.map((item) => ({
                  title: str(item.title),
                  hint: str(item.hint),
                  montage: Math.max(0, num(item.montagePerSqm)),
                  grades: [],
              }))
            : [{ title: '', hint: '', montage: 0, grades: [] }]

    let hidden = 0
    for (const item of list(content.grades)) {
        const title = str(item.title)
        if (title === '') {
            continue
        }
        const group =
            rawSeries.length === 0
                ? series[0]
                : series.find((candidate) => candidate.title === str(item.series))
        if (group === undefined) {
            hidden += 1
            warnings.push(
                `«${title}»: серия «${str(item.series) || 'не указана'}» не найдена — покрытие на сайт не попадёт`,
            )
            continue
        }
        const price = Math.max(0, num(item.pricePerSqm))
        group.grades.push({
            title,
            price,
            total: price > 0 ? price + group.montage : 0,
            details: str(item.details),
        })
    }

    const gradesCount = series.reduce((sum, group) => sum + group.grades.length, 0)
    if (gradesCount === 0) {
        warnings.unshift(
            hidden > 0
                ? 'Ни одно покрытие не подходит ни к одной серии — блок на сайт не выводится'
                : 'Нет покрытий — блок на сайт не выводится',
        )
    }
    for (const group of series) {
        for (const grade of group.grades) {
            if (grade.price === 0) {
                warnings.push(
                    `«${[group.title, grade.title].filter(Boolean).join(' · ')}»: цена не задана — на сайте «Цена по запросу»`,
                )
            }
        }
        if (group.montage === 0 && group.grades.some((grade) => grade.price > 0)) {
            warnings.push(
                `${group.title === '' ? 'Монтаж' : `Серия «${group.title}»`}: монтаж 0 ₽ — в смете не будет строки «Монтаж под ключ»`,
            )
        }
    }

    const heights = list(content.heights)
        .map((item) => num(item.meters))
        .filter((meters) => meters > 0 && meters <= 5)
    const colors = list(content.colors)
        .filter((item) => /^#[0-9A-Fa-f]{6}$/.test(str(item.hex)))
        .map((item) => ({
            label: ['RAL ' + str(item.ral), str(item.name)]
                .filter((part) => part !== 'RAL ' && part !== '')
                .join(' · '),
            hex: str(item.hex),
        }))
    if (list(content.colors).length > colors.length) {
        warnings.push(
            'У части цветов HEX задан неверно (нужен вид #2D7F27) — такие цвета на сайт не попадут',
        )
    }

    const lengthRaw = isRecord(content.length) ? content.length : {}
    const min = Math.max(1, num(lengthRaw.min) || 10)
    const max = Math.max(min, num(lengthRaw.max) || 200)
    const length = { min, max, default: Math.min(max, Math.max(min, num(lengthRaw.default) || 40)) }

    const firstPriced = series
        .flatMap((group) => group.grades.map((grade) => ({ group, grade })))
        .find(({ grade }) => grade.price > 0)
    let example: FenceSummary['example'] = null
    if (firstPriced !== undefined) {
        const height = heights.includes(1.8) ? 1.8 : (heights[0] ?? 1.8)
        const area = Math.round(length.default * height * 100) / 100
        const sum = area * firstPriced.grade.price + area * firstPriced.group.montage
        example = {
            title: [firstPriced.group.title, firstPriced.grade.title].filter(Boolean).join(' '),
            area,
            total: Math.round(sum / 100) * 100,
            length: length.default,
            height,
        }
    }

    return {
        series,
        heights,
        colors,
        length,
        gateEnabled: isRecord(content.gate) && content.gate.enabled === true,
        freeItems: list(content.freeItems)
            .map((item) => str(item.title))
            .filter((title) => title !== ''),
        example,
        warnings,
    }
}

/** Подпись блока в списке «Структура»: «2 серии · 8 покрытий · от 3 400 ₽/м²». */
export function fenceSummaryLine(content: Content): string {
    const summary = fenceSummary(content)
    const grades = summary.series.flatMap((group) => group.grades)
    if (grades.length === 0) {
        return 'Нет покрытий'
    }
    const named = summary.series.filter((group) => group.title !== '').length
    const prices = grades.map((grade) => grade.price).filter((price) => price > 0)
    const parts = [
        named > 0 ? `${named} ${plural(named, 'серия', 'серии', 'серий')}` : '',
        `${grades.length} ${plural(grades.length, 'покрытие', 'покрытия', 'покрытий')}`,
        prices.length > 0 ? `от ${Math.min(...prices).toLocaleString('ru-RU')} ₽/м²` : 'без цен',
    ]

    return parts.filter((part) => part !== '').join(' · ')
}
