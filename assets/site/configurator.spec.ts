import { afterEach, describe, expect, it } from 'vitest'
import {
    fenceHeightPercent,
    fenceLeadPlan,
    fenceTotal,
    initFenceConfigurators,
    type FenceConfig,
} from './configurator'

const config: FenceConfig = {
    materials: [
        { title: 'Профнастил С8', price: 1290, pattern: 'profnastil' },
        { title: 'Евроштакетник', price: 2050, pattern: 'shtaketnik' },
        { title: 'Без цены', price: 0, pattern: 'jaluzi' },
    ],
    heights: [
        { label: '1,5 м', factor: 0.88 },
        { label: '1,8 м', factor: 1 },
        { label: '2,0 м', factor: 1.12 },
    ],
    colors: [
        { ral: '6005', name: 'зелёный мох', hex: '#0F4336' },
        { ral: '8017', name: 'шоколад', hex: '#45322E' },
    ],
    gate: 38000,
}

describe('fenceTotal', () => {
    it('multiplies length, price per meter and height factor, rounds to 100 ₽', () => {
        expect(
            fenceTotal(config, { material: 0, height: 1, color: 0, length: 40, gate: false }),
        ).toBe(51600)
        // 40 × 2050 × 1.12 = 91 840 → 91 800
        expect(
            fenceTotal(config, { material: 1, height: 2, color: 0, length: 40, gate: false }),
        ).toBe(91800)
    })

    it('adds the gate only when it is chosen and priced', () => {
        expect(
            fenceTotal(config, { material: 0, height: 1, color: 0, length: 40, gate: true }),
        ).toBe(89600)
        expect(
            fenceTotal(
                { ...config, gate: 0 },
                { material: 0, height: 1, color: 0, length: 40, gate: true },
            ),
        ).toBe(51600)
    })

    it('returns 0 when the material has no price', () => {
        expect(
            fenceTotal(config, { material: 2, height: 1, color: 0, length: 40, gate: true }),
        ).toBe(0)
    })

    it('treats a missing height as factor 1', () => {
        expect(
            fenceTotal(
                { ...config, heights: [] },
                { material: 0, height: 0, color: 0, length: 10, gate: false },
            ),
        ).toBe(12900)
    })
})

describe('fenceHeightPercent', () => {
    it('spreads heights between 36% and 54% of the scene', () => {
        expect(fenceHeightPercent(0, 3)).toBe(36)
        expect(fenceHeightPercent(1, 3)).toBe(45)
        expect(fenceHeightPercent(2, 3)).toBe(54)
        expect(fenceHeightPercent(0, 1)).toBe(46)
    })
})

describe('fenceLeadPlan', () => {
    it('lists the chosen parameters and the approximate sum', () => {
        expect(
            fenceLeadPlan(config, { material: 0, height: 1, color: 1, length: 40, gate: true }),
        ).toBe(
            'Конфигуратор: Профнастил С8, RAL 8017, высота 1,8 м, длина 40 м, ворота, примерно 89\u00a0600\u00a0₽',
        )
    })

    it('omits the sum when the price is not set', () => {
        expect(
            fenceLeadPlan(config, { material: 2, height: 0, color: 0, length: 25, gate: false }),
        ).toBe('Конфигуратор: Без цены, RAL 6005, высота 1,5 м, длина 25 м')
    })
})

describe('initFenceConfigurators', () => {
    afterEach(() => {
        document.body.innerHTML = ''
    })

    function mount(): HTMLFormElement {
        document.body.innerHTML = `
            <form data-fence-configurator data-config='${JSON.stringify(config)}'>
                <div data-cfg-scene data-pattern="profnastil" style="--ral: #0F4336; --fh: 45%"></div>
                <p data-cfg-caption></p>
                <span data-cfg-color-name></span>
                <input type="radio" name="material" value="0" checked><input type="radio" name="material" value="1">
                <input type="radio" name="color" value="0" checked><input type="radio" name="color" value="1">
                <input type="radio" name="height" value="0"><input type="radio" name="height" value="1" checked><input type="radio" name="height" value="2">
                <input type="range" name="length" min="10" max="200" value="40"><output data-cfg-length-out></output>
                <input type="checkbox" name="gate">
                <p data-cfg-total></p>
                <a href="#lead-form" data-cfg-cta data-lead-plan="">Зафиксировать цену</a>
            </form>`
        initFenceConfigurators(document)

        return document.querySelector('form') as HTMLFormElement
    }

    it('shows the initial sum, caption and lead plan', () => {
        const form = mount()
        expect(form.querySelector('[data-cfg-total]')?.textContent).toBe('≈ 51\u00a0600\u00a0₽')
        expect(form.querySelector('[data-cfg-caption]')?.textContent).toBe(
            'RAL 6005 · зелёный мох · 1,8 м',
        )
        expect(form.querySelector<HTMLAnchorElement>('[data-cfg-cta]')?.dataset.leadPlan).toContain(
            'Профнастил С8',
        )
    })

    it('recalculates when the visitor changes material, colour, height, length and gate', () => {
        const form = mount()
        const pick = (name: string, value: string): void => {
            const input = form.querySelector<HTMLInputElement>(
                `input[name="${name}"][value="${value}"]`,
            )!
            input.checked = true
            input.dispatchEvent(new Event('change', { bubbles: true }))
        }
        pick('material', '1')
        pick('color', '1')
        pick('height', '2')
        const length = form.querySelector<HTMLInputElement>('input[name="length"]')!
        length.value = '50'
        length.dispatchEvent(new Event('input', { bubbles: true }))
        const gate = form.querySelector<HTMLInputElement>('input[name="gate"]')!
        gate.checked = true
        gate.dispatchEvent(new Event('change', { bubbles: true }))

        // 50 × 2050 × 1.12 = 114 800 + 38 000
        expect(form.querySelector('[data-cfg-total]')?.textContent).toBe('≈ 152\u00a0800\u00a0₽')
        expect(form.querySelector('[data-cfg-length-out]')?.textContent).toBe('50 м')
        const scene = form.querySelector<HTMLElement>('[data-cfg-scene]')!
        expect(scene.dataset.pattern).toBe('shtaketnik')
        expect(scene.style.getPropertyValue('--ral')).toBe('#45322E')
        expect(scene.style.getPropertyValue('--fh')).toBe('54%')
        expect(form.querySelector('[data-cfg-color-name]')?.textContent).toBe('RAL 8017 · шоколад')
    })

    it('ignores a broken config instead of throwing', () => {
        document.body.innerHTML =
            '<form data-fence-configurator data-config="{oops"><p data-cfg-total>≈ 1 ₽</p></form>'
        expect(() => initFenceConfigurators(document)).not.toThrow()
        expect(document.querySelector('[data-cfg-total]')?.textContent).toBe('≈ 1 ₽')
    })
})
