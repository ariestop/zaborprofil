import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { blockSummary } from './block-kinds'
import { FenceConfiguratorPreview } from './BlockPreview'
import { fenceSummary, fenceSummaryLine } from './fence-summary'

const content = {
    title: 'Соберите забор',
    series: [
        { title: 'Largo', hint: 'наклон в одну сторону', montagePerSqm: 3800, pattern: 'jaluzi' },
        { title: 'Doppio', hint: '', montagePerSqm: 2500, pattern: 'jaluzi-double' },
    ],
    grades: [
        {
            series: 'Largo',
            title: 'Стандарт',
            pricePerSqm: 3400,
            finish: 'gloss',
            details: 'гладкий',
        },
        { series: 'Largo', title: 'Премиум', pricePerSqm: 4500, finish: 'matte', details: '' },
        { series: 'Doppio', title: 'Стандарт', pricePerSqm: 5600, finish: 'gloss', details: '' },
    ],
    heights: [{ meters: 1.5 }, { meters: 1.8 }, { meters: 2 }],
    colors: [{ ral: '7024', name: 'Графит', hex: '#373f43' }],
    gate: { enabled: true, label: 'Ворота и калитка' },
    freeItems: [{ title: 'Замер и образцы цвета', note: '' }],
    length: { min: 5, max: 150, default: 30 },
}

describe('fenceSummary', () => {
    it('groups grades by series and adds the montage price per m²', () => {
        const summary = fenceSummary(content)

        expect(
            summary.series.map((group) => [group.title, group.montage, group.grades.length]),
        ).toEqual([
            ['Largo', 3800, 2],
            ['Doppio', 2500, 1],
        ])
        expect(summary.series[0]?.grades[0]).toMatchObject({ price: 3400, total: 7200 })
        expect(summary.series[1]?.grades[0]).toMatchObject({ price: 5600, total: 8100 })
        expect(summary.warnings).toEqual([])
    })

    it('calculates the same example as the site: 30 m × 1,8 m', () => {
        // 54 м² × (3 400 + 3 800) = 388 800
        expect(fenceSummary(content).example).toEqual({
            title: 'Largo Стандарт',
            area: 54,
            total: 388800,
            length: 30,
            height: 1.8,
        })
    })

    it('warns about grades that will not reach the site', () => {
        const summary = fenceSummary({
            ...content,
            grades: [
                ...content.grades,
                { series: 'largo', title: 'Потерянное', pricePerSqm: 100 },
                { series: 'Largo', title: 'Без цены', pricePerSqm: 0 },
            ],
            colors: [...content.colors, { ral: '9999', name: 'плохой', hex: 'red' }],
        })

        expect(summary.warnings).toEqual(
            expect.arrayContaining([
                '«Потерянное»: серия «largo» не найдена — покрытие на сайт не попадёт',
                '«Largo · Без цены»: цена не задана — на сайте «Цена по запросу»',
                'У части цветов HEX задан неверно (нужен вид #2D7F27) — такие цвета на сайт не попадут',
            ]),
        )
        expect(summary.series[0]?.grades.map((grade) => grade.title)).not.toContain('Потерянное')
    })

    it('treats grades without series as one nameless series and handles an empty block', () => {
        const single = fenceSummary({ grades: [{ title: 'Полиэстер', pricePerSqm: 1000 }] })

        expect(single.series).toHaveLength(1)
        expect(single.series[0]?.grades[0]).toMatchObject({
            title: 'Полиэстер',
            price: 1000,
            total: 1000,
        })
        expect(single.warnings.some((warning) => warning.includes('монтаж 0'))).toBe(true)
        expect(fenceSummary({}).warnings[0]).toBe('Нет покрытий — блок на сайт не выводится')
        expect(
            fenceSummary({ grades: [{ series: 'x', title: 'a' }], series: [{ title: 'Largo' }] })
                .warnings[0],
        ).toBe('Ни одно покрытие не подходит ни к одной серии — блок на сайт не выводится')
    })
})

describe('summary line and preview', () => {
    afterEach(cleanup)

    it('describes the block in the structure list', () => {
        expect(fenceSummaryLine(content)).toBe('2 серии · 3 покрытия · от 3 400 ₽/м²')
        expect(fenceSummaryLine({})).toBe('Нет покрытий')
        expect(blockSummary({ type: 'fence-configurator', content })).toContain('2 серии')
    })

    it('shows the price table, example and warnings instead of the title only', () => {
        render(
            <FenceConfiguratorPreview
                content={{
                    ...content,
                    grades: [
                        ...content.grades,
                        { series: 'Нет', title: 'Потерянное', pricePerSqm: 1 },
                    ],
                }}
            />,
        )

        expect(screen.getByText('Largo')).toBeTruthy()
        expect(screen.getByText('Doppio')).toBeTruthy()
        expect(screen.getByText('монтаж 3 800 ₽/м²')).toBeTruthy()
        expect(screen.getAllByText('7 200 ₽').length).toBeGreaterThan(0)
        expect(screen.getByText(/Пример сметы: Largo Стандарт/).textContent).toContain('388 800')
        expect(screen.getByText(/серия «Нет» не найдена/)).toBeTruthy()
        expect(screen.getByText(/по замеру/)).toBeTruthy()
    })

    it('says so when there are no grades', () => {
        render(<FenceConfiguratorPreview content={{}} />)

        expect(screen.getByText('В конфигураторе нет покрытий')).toBeTruthy()
    })
})
