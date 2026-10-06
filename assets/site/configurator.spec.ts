import { afterEach, describe, expect, it } from 'vitest'
import {
    clampLength,
    fenceEstimate,
    fenceColor,
    fenceLeadPlan,
    fenceTop,
    formatArea,
    gradeForSeries,
    initFenceConfigurators,
    SCENE_GROUND,
    type FenceConfig,
} from './configurator'

const config: FenceConfig = {
    materialLabel: 'Ламели',
    series: [
        { title: 'Largo', hint: 'наклон в одну сторону', montage: 3800, pattern: 'jaluzi' },
        { title: 'Doppio', hint: 'наклон в обе стороны', montage: 2500, pattern: 'jaluzi-double' },
    ],
    grades: [
        {
            series: 0,
            title: 'Стандарт',
            price: 3400,
            finish: 'gloss',
            details: 'гладкий · металл 0,45 мм',
        },
        {
            series: 0,
            title: 'Премиум',
            price: 4500,
            finish: 'matte',
            details: 'матовый велюр · металл 0,50 мм',
        },
        {
            series: 1,
            title: 'Стандарт',
            price: 5600,
            finish: 'gloss',
            details: 'гладкий · металл 0,45 мм',
        },
        {
            series: 1,
            title: 'Платинум',
            price: 9200,
            finish: 'wood',
            details: 'матовый «Античный дуб»',
            decor: 'Античный дуб',
        },
        { series: 1, title: 'Без цены', price: 0, finish: 'matte', details: '' },
    ],
    heights: [1.5, 1.8, 2],
    colors: [
        { ral: '7024', name: 'Графит', hex: '#373f43' },
        { ral: '8017', name: 'Шоколад', hex: '#45322e' },
    ],
    gate: { enabled: true, label: 'Ворота и калитка' },
    free: [{ title: 'Замер и образцы цвета', note: 'выезд по Саратову' }],
}

const base = { grade: 1, height: 1, color: 0, length: 30, gate: false }

describe('fenceEstimate', () => {
    it('counts material and montage by area and rounds the total to 100 ₽', () => {
        const estimate = fenceEstimate(config, base)

        // 30 × 1,8 = 54 м²: ламели 54 × 4 500 = 243 000, монтаж 54 × 3 800 = 205 200.
        expect(estimate.area).toBe(54)
        expect(estimate.rows.map((row) => [row.title, row.kind, row.amount])).toEqual([
            ['Ламели Largo Премиум', 'sum', 243000],
            ['Монтаж под ключ', 'sum', 205200],
            ['Замер и образцы цвета', 'free', 0],
        ])
        expect(estimate.rows[0]?.note).toBe('54 м² × 4 500 ₽')
        expect(estimate.total).toBe(448200)
        expect(estimate.perMeter).toBe(14940)
    })

    it('adds the gate as «по замеру» without changing the sum', () => {
        const estimate = fenceEstimate(config, { ...base, gate: true })

        expect(estimate.rows.at(-1)).toMatchObject({ title: 'Ворота и калитка', kind: 'tbd' })
        expect(estimate.total).toBe(448200)
        expect(
            fenceEstimate(
                { ...config, gate: { enabled: false, label: '' } },
                { ...base, gate: true },
            ).rows,
        ).toHaveLength(3)
    })

    it('uses the montage price of the grade series and fractional areas', () => {
        // 25 × 1,5 = 37,5 м²: 37,5 × 9 200 = 345 000 + 37,5 × 2 500 = 93 750 → 438 750 → 438 800.
        const estimate = fenceEstimate(config, { ...base, grade: 3, height: 0, length: 25 })

        expect(estimate.area).toBe(37.5)
        expect(estimate.rows[0]?.note).toBe('37,5 м² × 9 200 ₽')
        expect(estimate.total).toBe(438800)
    })

    it('returns no rows and 0 when the grade has no price', () => {
        expect(fenceEstimate(config, { ...base, grade: 4 })).toMatchObject({ rows: [], total: 0 })
        expect(fenceEstimate(config, { ...base, grade: 99 }).total).toBe(0)
    })
})

describe('helpers', () => {
    it('keeps the grade with the same title when the series changes', () => {
        expect(gradeForSeries(config, 1, 0)).toBe(2)
        expect(gradeForSeries(config, 1, 1)).toBe(2)
        expect(gradeForSeries(config, 0, 3)).toBe(0)
        expect(gradeForSeries(config, 5, 0)).toBe(-1)
    })

    it('formats area and clamps length', () => {
        expect(formatArea(54)).toBe('54 м²')
        expect(formatArea(1234.5)).toBe('1 234,5 м²')
        expect(clampLength(Number.NaN, 5, 150)).toBe(5)
        expect(clampLength(400, 5, 150)).toBe(150)
        expect(clampLength(12.4, 5, 150)).toBe(12)
    })

    it('puts the fence top higher for a taller fence', () => {
        expect(fenceTop(0)).toBe(SCENE_GROUND)
        expect(fenceTop(2)).toBeLessThan(fenceTop(1.5))
    })
})

describe('fenceLeadPlan', () => {
    it('lists the chosen parameters, the gate and the approximate sum', () => {
        expect(fenceLeadPlan(config, { ...base, color: 1, gate: true })).toBe(
            'Конфигуратор: Largo Премиум, RAL 8017, высота 1,8 м, длина 30 м, ворота и калитка — по замеру, примерно 448 200 ₽',
        )
    })

    it('names the decor instead of RAL for a wood grade', () => {
        expect(fenceLeadPlan(config, { ...base, grade: 3, height: 0, length: 25 })).toBe(
            'Конфигуратор: Doppio Платинум, Античный дуб, высота 1,5 м, длина 25 м, примерно 438 800 ₽',
        )
        expect(fenceColor(config, { ...base, grade: 3 })).toEqual({
            long: 'Античный дуб',
            short: 'Античный дуб',
            decor: 'Античный дуб',
        })
        expect(fenceColor(config, { ...base, color: 1 })).toEqual({
            long: 'RAL 8017 · Шоколад',
            short: 'RAL 8017',
            decor: '',
        })
    })

    it('omits the sum when the price is not set', () => {
        expect(fenceLeadPlan(config, { ...base, grade: 4, height: 0, length: 25 })).toBe(
            'Конфигуратор: Doppio Без цены, RAL 7024, высота 1,5 м, длина 25 м',
        )
    })
})

describe('initFenceConfigurators', () => {
    afterEach(() => {
        document.body.innerHTML = ''
    })

    function mount(): HTMLFormElement {
        const grades = config.grades
            .map(
                (grade, index) =>
                    `<label data-series="${grade.series}" ${grade.series === 0 ? '' : 'hidden'}><input type="radio" name="grade" value="${index}" ${index === 0 ? 'checked' : ''}></label>`,
            )
            .join('')
        document.body.innerHTML = `
            <form data-fence-configurator data-config='${JSON.stringify(config)}'>
                <svg data-cfg-scene data-pattern="jaluzi" data-finish="gloss" style="--ral: #373f43">
                    <rect data-fence-body data-fill-prefix="u1" y="228" height="186" fill="url(#u1-jaluzi)"/>
                    <rect data-fill-prefix="u1" data-fill-suffix="-zoom" fill="url(#u1-jaluzi-zoom)"/>
                    <rect data-fill-prefix="u1" data-fill-suffix="-shade" fill="url(#u1-jaluzi-shade)"/>
                    <g data-fence-top transform="translate(0 228)"><text data-cfg-height-tag>1,8 м</text></g>
                </svg>
                <p data-cfg-caption></p><p data-cfg-details></p><p data-cfg-color-name></p><p data-cfg-meta></p>
                <input type="radio" name="series" value="0" checked><input type="radio" name="series" value="1">
                ${grades}
                <fieldset data-cfg-colors><input type="radio" name="color" value="0" checked><input type="radio" name="color" value="1"></fieldset>
                <input type="radio" name="height" value="0"><input type="radio" name="height" value="1" checked><input type="radio" name="height" value="2">
                <button type="button" data-cfg-step="-1"></button><input type="number" name="length" min="5" max="150" value="30"><button type="button" data-cfg-step="1"></button>
                <input type="checkbox" name="gate">
                <ul data-cfg-rows></ul>
                <strong data-cfg-total></strong><strong data-cfg-total-bar></strong><p data-cfg-per-meter></p>
                <a href="#lead-form" data-cfg-cta data-lead-plan="">Зафиксировать цену</a>
            </form>`
        initFenceConfigurators(document)

        return document.querySelector('form') as HTMLFormElement
    }

    const pick = (form: HTMLFormElement, name: string, value: string): void => {
        const input = form.querySelector<HTMLInputElement>(
            `input[name="${name}"][value="${value}"]`,
        )!
        input.checked = true
        input.dispatchEvent(new Event('change', { bubbles: true }))
    }

    it('shows the initial estimate, caption and lead plan', () => {
        const form = mount()

        expect(form.querySelector('[data-cfg-total]')?.textContent).toBe('≈ 388 800 ₽')
        expect(form.querySelector('[data-cfg-total-bar]')?.textContent).toBe('≈ 388 800 ₽')
        expect(form.querySelector('[data-cfg-per-meter]')?.textContent).toBe(
            '≈ 12 960 ₽ за погонный метр',
        )
        expect(form.querySelectorAll('[data-cfg-rows] li')).toHaveLength(3)
        expect(form.querySelector('[data-cfg-caption]')?.textContent).toBe(
            'Largo Стандарт · гладкий · RAL 7024 · Графит',
        )
        expect(form.querySelector('[data-cfg-meta]')?.textContent).toBe(
            'Largo Стандарт · RAL 7024 · 1,8 м × 30 м',
        )
        expect(form.querySelector<HTMLAnchorElement>('[data-cfg-cta]')?.dataset.leadPlan).toContain(
            'Largo Стандарт',
        )
    })

    it('switches series, keeps the grade title and redraws the fence', () => {
        const form = mount()
        pick(form, 'series', '1')

        const visible = Array.from(form.querySelectorAll<HTMLElement>('[data-series]')).filter(
            (label) => !label.hidden,
        )
        expect(visible.map((label) => label.dataset.series)).toEqual(['1', '1', '1'])
        expect(form.querySelector<HTMLInputElement>('input[name="grade"]:checked')?.value).toBe('2')
        const scene = form.querySelector<SVGSVGElement>('[data-cfg-scene]')!
        expect(scene.dataset.pattern).toBe('jaluzi-double')
        expect(scene.querySelector('[data-fence-body]')?.getAttribute('fill')).toBe(
            'url(#u1-jaluzi-double)',
        )
        expect(scene.querySelector('[data-fill-suffix]')?.getAttribute('fill')).toBe(
            'url(#u1-jaluzi-double-zoom)',
        )
        // 54 × (5 600 + 2 500) = 437 400
        expect(form.querySelector('[data-cfg-total]')?.textContent).toBe('≈ 437 400 ₽')
    })

    it('recalculates on grade, colour, height, length steps and gate', () => {
        const form = mount()
        pick(form, 'grade', '1')
        pick(form, 'color', '1')
        pick(form, 'height', '2')
        form.querySelector<HTMLButtonElement>('[data-cfg-step="1"]')!.click()
        const gate = form.querySelector<HTMLInputElement>('input[name="gate"]')!
        gate.checked = true
        gate.dispatchEvent(new Event('change', { bubbles: true }))

        // 31 × 2 = 62 м²: 62 × (4 500 + 3 800) = 514 600; ворота — по замеру.
        expect(form.querySelector('[data-cfg-total]')?.textContent).toBe('≈ 514 600 ₽')
        expect(form.querySelector<HTMLInputElement>('input[name="length"]')?.value).toBe('31')
        expect(form.querySelector('[data-cfg-rows] li:last-child em')?.textContent).toBe(
            'по замеру',
        )
        const scene = form.querySelector<SVGSVGElement>('[data-cfg-scene]')!
        expect(scene.dataset.finish).toBe('matte')
        expect(scene.style.getPropertyValue('--ral')).toBe('#45322e')
        expect(scene.querySelector('[data-fence-body]')?.getAttribute('y')).toBe(
            String(fenceTop(2)),
        )
        expect(scene.querySelector('[data-fence-top]')?.getAttribute('transform')).toBe(
            `translate(0 ${fenceTop(2)})`,
        )
        expect(scene.querySelector('[data-cfg-height-tag]')?.textContent).toBe('2,0 м')
        expect(form.querySelector('[data-cfg-color-name]')?.textContent).toBe('RAL 8017 · Шоколад')
    })

    it('locks the RAL colours for the oak decor and unlocks them again', () => {
        const form = mount()
        pick(form, 'series', '1')
        pick(form, 'grade', '3')

        const colors = form.querySelector<HTMLFieldSetElement>('[data-cfg-colors]')!
        const scene = form.querySelector<SVGSVGElement>('[data-cfg-scene]')!
        expect(colors.disabled).toBe(true)
        expect(scene.dataset.finish).toBe('wood')
        expect(scene.querySelector('[data-fill-suffix="-shade"]')?.getAttribute('fill')).toBe(
            'url(#u1-jaluzi-double-shade)',
        )
        expect(form.querySelector('[data-cfg-color-name]')?.textContent).toBe(
            'Декор «Античный дуб» — цвет RAL не выбирается',
        )
        expect(form.querySelector('[data-cfg-caption]')?.textContent).toBe(
            'Doppio Платинум · матовый «Античный дуб»',
        )
        expect(form.querySelector('[data-cfg-meta]')?.textContent).toBe(
            'Doppio Платинум · Античный дуб · 1,8 м × 30 м',
        )
        expect(scene.getAttribute('aria-label')).toContain('Античный дуб')

        pick(form, 'grade', '2')
        expect(colors.disabled).toBe(false)
        expect(form.querySelector('[data-cfg-color-name]')?.textContent).toBe('RAL 7024 · Графит')
    })

    it('clamps a typed length when the field loses focus', () => {
        const form = mount()
        const length = form.querySelector<HTMLInputElement>('input[name="length"]')!
        length.value = '900'
        length.dispatchEvent(new Event('change', { bubbles: true }))

        expect(length.value).toBe('150')
    })

    it('ignores a broken config instead of throwing', () => {
        document.body.innerHTML =
            '<form data-fence-configurator data-config="{oops"><p data-cfg-total>≈ 1 ₽</p></form>'
        expect(() => initFenceConfigurators(document)).not.toThrow()
        expect(document.querySelector('[data-cfg-total]')?.textContent).toBe('≈ 1 ₽')
    })
})
