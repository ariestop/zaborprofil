/**
 * Конфигуратор забора (блок fence-configurator): пересчёт суммы, картинка забора и параметры для заявки.
 * Первая сумма уже посчитана сервером (templates/public/blocks/fence-configurator.html.twig) по тем же правилам.
 */
import { formatMoney } from './ui'

export interface FenceConfig {
    materials: Array<{ title: string; price: number; pattern: string }>
    heights: Array<{ label: string; factor: number }>
    colors: Array<{ ral: string; name: string; hex: string }>
    /** Цена ворот; 0 — пункта нет. */
    gate: number
}

export interface FenceSelection {
    material: number
    height: number
    color: number
    length: number
    gate: boolean
}

/** Сумма = длина × цена за метр × коэффициент высоты + ворота, с округлением до 100 ₽; 0 — цена не задана. */
export function fenceTotal(config: FenceConfig, selection: FenceSelection): number {
    const material = config.materials[selection.material]
    if (material === undefined || material.price <= 0) {
        return 0
    }
    const factor = config.heights[selection.height]?.factor ?? 1
    const gate = selection.gate && config.gate > 0 ? config.gate : 0

    return Math.round((selection.length * material.price * factor) / 100) * 100 + gate
}

/** Высота забора на картинке в процентах сцены — те же значения, что в шаблоне. */
export function fenceHeightPercent(index: number, count: number): number {
    return count > 1 ? 36 + (18 * index) / (count - 1) : 46
}

function colorLabel(color: { ral: string; name: string } | undefined): string {
    if (color === undefined) {
        return ''
    }

    return color.name !== '' ? `RAL ${color.ral} · ${color.name}` : `RAL ${color.ral}`
}

/** Строка для заявки: «Конфигуратор: Профнастил С8, RAL 6005, высота 1,8 м, длина 40 м, ворота, примерно 51 600 ₽». */
export function fenceLeadPlan(config: FenceConfig, selection: FenceSelection): string {
    const parts = [config.materials[selection.material]?.title ?? '']
    const color = config.colors[selection.color]
    if (color !== undefined) {
        parts.push(`RAL ${color.ral}`)
    }
    const height = config.heights[selection.height]
    if (height !== undefined) {
        parts.push(`высота ${height.label}`)
    }
    parts.push(`длина ${selection.length} м`)
    if (selection.gate && config.gate > 0) {
        parts.push('ворота')
    }
    const total = fenceTotal(config, selection)
    if (total > 0) {
        parts.push(`примерно ${formatMoney(total)}\u00a0₽`)
    }

    return `Конфигуратор: ${parts.filter((part) => part !== '').join(', ')}`
}

function parseConfig(raw: string | undefined): FenceConfig | null {
    try {
        const data = JSON.parse(raw ?? '') as Partial<FenceConfig>
        if (!Array.isArray(data.materials) || data.materials.length === 0) {
            return null
        }

        return {
            materials: data.materials,
            heights: Array.isArray(data.heights) ? data.heights : [],
            colors: Array.isArray(data.colors) ? data.colors : [],
            gate: typeof data.gate === 'number' ? data.gate : 0,
        }
    } catch {
        return null
    }
}

function radioValue(form: HTMLFormElement, name: string): number {
    const field = form.elements.namedItem(name)
    if (field instanceof RadioNodeList) {
        return Number(field.value || 0)
    }
    if (field instanceof HTMLInputElement) {
        return Number(field.value || 0)
    }

    return 0
}

function readSelection(form: HTMLFormElement): FenceSelection {
    const length = form.elements.namedItem('length')
    const gate = form.elements.namedItem('gate')

    return {
        material: radioValue(form, 'material'),
        height: radioValue(form, 'height'),
        color: radioValue(form, 'color'),
        length: length instanceof HTMLInputElement ? Number(length.value) : 0,
        gate: gate instanceof HTMLInputElement && gate.checked,
    }
}

export function initFenceConfigurators(root: ParentNode = document): void {
    root.querySelectorAll<HTMLFormElement>('form[data-fence-configurator]').forEach((form) => {
        const config = parseConfig(form.dataset.config)
        if (config === null) {
            return
        }
        const scene = form.querySelector<HTMLElement>('[data-cfg-scene]')
        const caption = form.querySelector<HTMLElement>('[data-cfg-caption]')
        const colorName = form.querySelector<HTMLElement>('[data-cfg-color-name]')
        const lengthOut = form.querySelector<HTMLElement>('[data-cfg-length-out]')
        const totalOut = form.querySelector<HTMLElement>('[data-cfg-total]')
        const cta = form.querySelector<HTMLAnchorElement>('[data-cfg-cta]')

        const update = (): void => {
            const selection = readSelection(form)
            const material = config.materials[selection.material]
            const color = config.colors[selection.color]
            const height = config.heights[selection.height]
            const total = fenceTotal(config, selection)

            if (scene !== null) {
                scene.dataset.pattern = material?.pattern ?? 'profnastil'
                if (color !== undefined) {
                    scene.style.setProperty('--ral', color.hex)
                }
                scene.style.setProperty(
                    '--fh',
                    `${fenceHeightPercent(selection.height, config.heights.length)}%`,
                )
            }
            if (caption !== null) {
                caption.textContent = [colorLabel(color), height?.label ?? '']
                    .filter((part) => part !== '')
                    .join(' · ')
            }
            if (colorName !== null) {
                colorName.textContent = colorLabel(color)
            }
            if (lengthOut !== null) {
                lengthOut.textContent = `${selection.length} м`
            }
            if (totalOut !== null) {
                totalOut.textContent =
                    total > 0 ? `≈ ${formatMoney(total)}\u00a0₽` : 'Цена по запросу'
            }
            if (cta !== null) {
                cta.dataset.leadPlan = fenceLeadPlan(config, selection)
            }
        }

        form.addEventListener('input', update)
        form.addEventListener('change', update)
        form.addEventListener('submit', (event) => event.preventDefault())
        update()
    })
}
