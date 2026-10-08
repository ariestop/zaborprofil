import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { BlockFields, selectOptions } from './BlockFields'
import { fieldSpecsFor, type FieldSpec } from './field-specs'

vi.mock('../../rich-text/RichTextEditor', () => ({ RichTextEditor: () => null }))

const content = {
    title: 'Соберите забор',
    series: [
        { title: 'Largo', hint: '', montagePerSqm: 3800, pattern: 'jaluzi' },
        { title: 'Doppio', hint: '', montagePerSqm: 2500, pattern: 'jaluzi-double' },
    ],
    grades: [
        { series: 'Largo', title: 'Стандарт', pricePerSqm: 3400, finish: 'gloss', details: '' },
        { series: 'largo', title: 'Премиум', pricePerSqm: 4500, finish: 'matte', details: '' },
    ],
    heights: [{ meters: 1.8 }],
    colors: [{ ral: '7024', name: 'Графит', hex: '#373f43' }],
}

const seriesSelect: Extract<FieldSpec, { kind: 'select' }> = {
    kind: 'select',
    key: 'series',
    label: 'Серия',
    options: [{ value: '', label: 'Без серии' }],
    optionsFrom: { key: 'series', valueKey: 'title' },
}

describe('selectOptions', () => {
    it('adds options from another list of the block without duplicates', () => {
        expect(selectOptions(seriesSelect, content, 'Largo')).toEqual({
            options: [
                { value: '', label: 'Без серии' },
                { value: 'Largo', label: 'Largo' },
                { value: 'Doppio', label: 'Doppio' },
            ],
            missing: false,
        })
    })

    it('keeps an unknown value visible and marks it as missing', () => {
        const { options, missing } = selectOptions(seriesSelect, content, 'largo')

        expect(missing).toBe(true)
        expect(options.at(-1)).toEqual({ value: 'largo', label: '«largo» — нет в списке' })
    })
})

describe('BlockFields for the fence configurator', () => {
    afterEach(cleanup)

    function renderFields(onChange = vi.fn()) {
        render(
            <BlockFields
                specs={fieldSpecsFor('fence-configurator', content)}
                value={content}
                errors={{}}
                resetKey="b1"
                onChange={onChange}
            />,
        )

        return onChange
    }

    it('titles cards by their content', () => {
        renderFields()

        expect(screen.getByText('Largo · Стандарт')).toBeTruthy()
        expect(screen.getByText('largo · Премиум')).toBeTruthy()
        expect(screen.getByText('1,8 м')).toBeTruthy()
        expect(screen.getByText('RAL 7024 · Графит')).toBeTruthy()
    })

    it('offers series of the block in the grade card and flags a typo', () => {
        const onChange = renderFields()
        const selects = screen.getAllByLabelText('Серия') as HTMLSelectElement[]

        expect(Array.from(selects[0]!.options).map((option) => option.value)).toEqual([
            '',
            'Largo',
            'Doppio',
        ])
        expect(screen.getByRole('alert').textContent).toContain('нет в списке')

        fireEvent.change(selects[1]!, { target: { value: 'Largo' } })
        const next = onChange.mock.calls.at(-1)?.[0] as typeof content
        expect(next.grades[1]?.series).toBe('Largo')
    })
})
