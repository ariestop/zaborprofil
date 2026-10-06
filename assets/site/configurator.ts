/**
 * Конфигуратор забора (блок fence-configurator): смета, картинка забора и параметры для заявки.
 * Первая смета уже посчитана сервером (templates/public/blocks/fence-configurator.html.twig) по тем же правилам.
 */
import { formatMoney } from './ui'

export interface FenceSeries {
    title: string
    hint: string
    /** Цена монтажа за м²; 0 — строки монтажа в смете нет. */
    montage: number
    pattern: string
}

export interface FenceGrade {
    /** Индекс серии в FenceConfig.series. */
    series: number
    title: string
    /** Цена материала за м²; 0 — «Цена по запросу». */
    price: number
    finish: 'gloss' | 'matte' | 'wood'
    details: string
}

export interface FenceConfig {
    materialLabel: string
    series: FenceSeries[]
    grades: FenceGrade[]
    /** Высоты в метрах. */
    heights: number[]
    colors: Array<{ ral: string; name: string; hex: string }>
    gate: { enabled: boolean; label: string }
    free: Array<{ title: string; note: string }>
}

export interface FenceSelection {
    grade: number
    height: number
    color: number
    length: number
    gate: boolean
}

export interface EstimateRow {
    title: string
    note: string
    kind: 'sum' | 'free' | 'tbd'
    amount: number
}

export interface FenceEstimate {
    rows: EstimateRow[]
    /** Итог с округлением до 100 ₽; 0 — цены нет. */
    total: number
    perMeter: number
    area: number
}

/** Геометрия сцены (templates/public/blocks/_fence-scene.svg.twig): низ забора и пикселей на метр. */
export const SCENE_GROUND = 414
export const SCENE_SCALE = 103.4

const money = (value: number): string => `${formatMoney(Math.round(value))} ₽`

export function formatMeters(value: number): string {
    return `${value.toFixed(1).replace('.', ',')} м`
}

export function formatArea(value: number): string {
    const rounded = Math.round(value * 10) / 10
    const text = Number.isInteger(rounded)
        ? formatMoney(rounded)
        : `${formatMoney(Math.trunc(rounded))},${Math.round((rounded % 1) * 10)}`

    return `${text} м²`
}

/** Верх забора на картинке для высоты в метрах. */
export function fenceTop(meters: number): number {
    return SCENE_GROUND - meters * SCENE_SCALE
}

/** При смене серии оставляем покрытие с тем же названием, иначе — первое покрытие серии. */
export function gradeForSeries(config: FenceConfig, series: number, current: number): number {
    const title = config.grades[current]?.title
    const same = config.grades.findIndex(
        (grade) => grade.series === series && grade.title === title,
    )
    if (same !== -1) {
        return same
    }

    return config.grades.findIndex((grade) => grade.series === series)
}

export function fenceEstimate(config: FenceConfig, selection: FenceSelection): FenceEstimate {
    const grade = config.grades[selection.grade]
    const series = grade !== undefined ? config.series[grade.series] : undefined
    const height = config.heights[selection.height] ?? 0
    const area = Math.round(selection.length * height * 100) / 100
    const rows: EstimateRow[] = []
    if (grade === undefined || grade.price <= 0 || area <= 0) {
        return { rows, total: 0, perMeter: 0, area }
    }

    const name = [series?.title ?? '', grade.title].filter((part) => part !== '').join(' ')
    rows.push({
        title: `${config.materialLabel} ${name}`.trim(),
        note: `${formatArea(area)} × ${money(grade.price)}`,
        kind: 'sum',
        amount: area * grade.price,
    })
    if (series !== undefined && series.montage > 0) {
        rows.push({
            title: 'Монтаж под ключ',
            note: `${formatArea(area)} × ${money(series.montage)}`,
            kind: 'sum',
            amount: area * series.montage,
        })
    }
    for (const item of config.free) {
        rows.push({ title: item.title, note: item.note, kind: 'free', amount: 0 })
    }
    if (selection.gate && config.gate.enabled) {
        rows.push({
            title: config.gate.label,
            note: 'считаем после замера',
            kind: 'tbd',
            amount: 0,
        })
    }

    const total = Math.round(rows.reduce((sum, row) => sum + row.amount, 0) / 100) * 100

    return { rows, total, perMeter: selection.length > 0 ? total / selection.length : 0, area }
}

function colorLabel(color: { ral: string; name: string } | undefined): string {
    if (color === undefined || color.ral === '') {
        return ''
    }

    return color.name !== '' ? `RAL ${color.ral} · ${color.name}` : `RAL ${color.ral}`
}

function gradeName(config: FenceConfig, grade: FenceGrade | undefined): string {
    if (grade === undefined) {
        return ''
    }

    return [config.series[grade.series]?.title ?? '', grade.title]
        .filter((part) => part !== '')
        .join(' ')
}

/** Строка для заявки: «Конфигуратор: Largo Премиум, RAL 7024, высота 1,8 м, длина 30 м, ворота и калитка — по замеру, примерно 448 200 ₽». */
export function fenceLeadPlan(config: FenceConfig, selection: FenceSelection): string {
    const parts = [gradeName(config, config.grades[selection.grade])]
    const color = config.colors[selection.color]
    if (color !== undefined && color.ral !== '') {
        parts.push(`RAL ${color.ral}`)
    }
    const height = config.heights[selection.height]
    if (height !== undefined) {
        parts.push(`высота ${formatMeters(height).replace(' ', ' ')}`)
    }
    parts.push(`длина ${selection.length} м`)
    if (selection.gate && config.gate.enabled) {
        parts.push(`${config.gate.label.toLowerCase()} — по замеру`)
    }
    const { total } = fenceEstimate(config, selection)
    if (total > 0) {
        parts.push(`примерно ${money(total)}`)
    }

    return `Конфигуратор: ${parts.filter((part) => part !== '').join(', ')}`
}

function parseConfig(raw: string | undefined): FenceConfig | null {
    try {
        const data = JSON.parse(raw ?? '') as Partial<FenceConfig>
        if (
            !Array.isArray(data.grades) ||
            data.grades.length === 0 ||
            !Array.isArray(data.series)
        ) {
            return null
        }

        return {
            materialLabel: typeof data.materialLabel === 'string' ? data.materialLabel : 'Материал',
            series: data.series,
            grades: data.grades,
            heights: Array.isArray(data.heights) ? data.heights : [],
            colors: Array.isArray(data.colors) ? data.colors : [],
            gate: data.gate ?? { enabled: false, label: '' },
            free: Array.isArray(data.free) ? data.free : [],
        }
    } catch {
        return null
    }
}

function radioValue(form: HTMLFormElement, name: string): number {
    const field = form.elements.namedItem(name)
    if (field instanceof RadioNodeList || field instanceof HTMLInputElement) {
        return Number(field.value || 0)
    }

    return 0
}

function setRadio(form: HTMLFormElement, name: string, value: number): void {
    const field = form.elements.namedItem(name)
    if (field instanceof RadioNodeList) {
        field.value = String(value)
    }
}

function lengthInput(form: HTMLFormElement): HTMLInputElement | null {
    const field = form.elements.namedItem('length')

    return field instanceof HTMLInputElement ? field : null
}

/** Длина в пределах min…max поля; пустое или неверное значение — минимум. */
export function clampLength(value: number, min: number, max: number): number {
    if (!Number.isFinite(value)) {
        return min
    }

    return Math.min(max, Math.max(min, Math.round(value)))
}

function readSelection(form: HTMLFormElement): FenceSelection {
    const length = lengthInput(form)
    const gate = form.elements.namedItem('gate')
    const lengthValue =
        length === null
            ? 0
            : clampLength(Number(length.value), Number(length.min || 1), Number(length.max || 1000))

    return {
        grade: radioValue(form, 'grade'),
        height: radioValue(form, 'height'),
        color: radioValue(form, 'color'),
        length: lengthValue,
        gate: gate instanceof HTMLInputElement && gate.checked,
    }
}

function renderRows(list: HTMLElement, rows: EstimateRow[]): void {
    list.replaceChildren(
        ...rows.map((row) => {
            const item = document.createElement('li')
            const text = document.createElement('div')
            const title = document.createElement('b')
            title.textContent = row.title
            text.append(title)
            if (row.note !== '') {
                const note = document.createElement('span')
                note.textContent = row.note
                text.append(note)
            }
            const value = document.createElement('em')
            if (row.kind === 'sum') {
                value.className = 'zp-price'
                value.textContent = money(row.amount)
            } else {
                value.className = row.kind === 'free' ? 'is-free' : 'is-tbd'
                value.textContent = row.kind === 'free' ? 'бесплатно' : 'по замеру'
            }
            item.append(text, value)

            return item
        }),
    )
}

function updateScene(scene: SVGSVGElement, config: FenceConfig, selection: FenceSelection): void {
    const grade = config.grades[selection.grade]
    const series = grade !== undefined ? config.series[grade.series] : undefined
    const color = config.colors[selection.color]
    const height = config.heights[selection.height]
    if (series !== undefined) {
        scene.dataset.pattern = series.pattern
        // Забор и лупа: у лупы тот же рисунок с суффиксом -zoom.
        scene.querySelectorAll<SVGElement>('[data-fill-prefix]').forEach((element) => {
            const prefix = element.dataset.fillPrefix ?? ''
            const suffix = element.dataset.fillSuffix ?? ''
            element.setAttribute('fill', `url(#${prefix}-${series.pattern}${suffix})`)
        })
    }
    if (grade !== undefined) {
        scene.dataset.finish = grade.finish
    }
    if (color !== undefined) {
        scene.style.setProperty('--ral', color.hex)
    }
    if (height === undefined) {
        return
    }
    const top = fenceTop(height)
    scene.querySelectorAll<SVGElement>('[data-fence-body]').forEach((element) => {
        element.setAttribute('y', String(top))
        element.setAttribute('height', String(SCENE_GROUND - top))
    })
    scene.querySelectorAll<SVGElement>('[data-fence-top]').forEach((element) => {
        element.setAttribute('transform', `translate(0 ${top})`)
    })
    const tag = scene.querySelector<SVGElement>('[data-cfg-height-tag]')
    if (tag !== null) {
        tag.textContent = formatMeters(height).replace(' ', ' ')
    }
    scene.setAttribute(
        'aria-label',
        `Забор ${gradeName(config, grade)}, высота ${formatMeters(height)}${color !== undefined && color.ral !== '' ? `, ${colorLabel(color)}` : ''}`,
    )
}

export function initFenceConfigurators(root: ParentNode = document): void {
    root.querySelectorAll<HTMLFormElement>('form[data-fence-configurator]').forEach((form) => {
        const config = parseConfig(form.dataset.config)
        if (config === null) {
            return
        }
        const find = <T extends Element>(selector: string): T | null =>
            form.querySelector<T>(selector)
        const scene = find<SVGSVGElement>('[data-cfg-scene]')
        const caption = find<HTMLElement>('[data-cfg-caption]')
        const details = find<HTMLElement>('[data-cfg-details]')
        const colorName = find<HTMLElement>('[data-cfg-color-name]')
        const meta = find<HTMLElement>('[data-cfg-meta]')
        const rows = find<HTMLElement>('[data-cfg-rows]')
        const totalOut = find<HTMLElement>('[data-cfg-total]')
        const totalBar = find<HTMLElement>('[data-cfg-total-bar]')
        const perMeter = find<HTMLElement>('[data-cfg-per-meter]')
        const cta = find<HTMLAnchorElement>('[data-cfg-cta]')
        const length = lengthInput(form)

        const update = (): void => {
            const selection = readSelection(form)
            const grade = config.grades[selection.grade]
            const color = config.colors[selection.color]
            const height = config.heights[selection.height]
            const estimate = fenceEstimate(config, selection)
            const name = gradeName(config, grade)
            const label = colorLabel(color)

            if (scene !== null) {
                updateScene(scene, config, selection)
            }
            if (caption !== null) {
                const texture = grade?.details.split(' · ')[0] ?? ''
                caption.textContent = [name, texture, label]
                    .filter((part) => part !== '')
                    .join(' · ')
            }
            if (details !== null) {
                details.textContent = grade?.details ?? ''
            }
            if (colorName !== null) {
                colorName.textContent = label
            }
            if (meta !== null) {
                meta.textContent = [
                    name,
                    color !== undefined && color.ral !== '' ? `RAL ${color.ral}` : '',
                    height !== undefined ? `${formatMeters(height)} × ${selection.length} м` : '',
                ]
                    .filter((part) => part !== '')
                    .join(' · ')
            }
            if (rows !== null) {
                renderRows(rows, estimate.rows)
            }
            const total = estimate.total > 0 ? `≈ ${money(estimate.total)}` : 'Цена по запросу'
            if (totalOut !== null) {
                totalOut.textContent = total
            }
            if (totalBar !== null) {
                totalBar.textContent = estimate.total > 0 ? total : 'по запросу'
            }
            if (perMeter !== null) {
                perMeter.textContent =
                    estimate.total > 0 ? `≈ ${money(estimate.perMeter)} за погонный метр` : ''
            }
            if (cta !== null) {
                cta.dataset.leadPlan = fenceLeadPlan(config, selection)
            }
        }

        // Серия: показываем её покрытия и переносим выбор на покрытие с тем же названием.
        form.querySelectorAll<HTMLInputElement>('input[name="series"]').forEach((input) => {
            input.addEventListener('change', () => {
                const series = Number(input.value)
                form.querySelectorAll<HTMLElement>('[data-series]').forEach((label) => {
                    label.hidden = Number(label.dataset.series) !== series
                })
                const next = gradeForSeries(config, series, radioValue(form, 'grade'))
                if (next !== -1) {
                    setRadio(form, 'grade', next)
                }
                update()
            })
        })

        form.querySelectorAll<HTMLButtonElement>('[data-cfg-step]').forEach((button) => {
            button.addEventListener('click', () => {
                if (length === null) {
                    return
                }
                const step = Number(button.dataset.cfgStep)
                length.value = String(
                    clampLength(
                        Number(length.value) + step,
                        Number(length.min || 1),
                        Number(length.max || 1000),
                    ),
                )
                update()
            })
        })
        // Неверную длину правим, когда поле теряет фокус, — не мешаем набирать число.
        length?.addEventListener('change', () => {
            length.value = String(readSelection(form).length)
        })

        form.addEventListener('input', update)
        form.addEventListener('change', update)
        form.addEventListener('submit', (event) => event.preventDefault())
        update()
    })
}
