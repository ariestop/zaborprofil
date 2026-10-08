import type { MouseEvent } from 'react'
import { Button } from '../../shared/ui'
import type { MediaAssetItem } from '../../types/api'

export function FocalPointPicker({
    asset,
    value,
    onChange,
}: {
    asset: MediaAssetItem
    value: { x: number; y: number } | null
    onChange: (value: { x: number; y: number } | null) => void
}) {
    const pick = (event: MouseEvent<HTMLButtonElement>): void => {
        const rect = event.currentTarget.getBoundingClientRect()
        if (rect.width === 0 || rect.height === 0) {
            return
        }
        const clamp = (number: number): number => Math.min(100, Math.max(0, Math.round(number)))
        onChange({
            x: clamp(((event.clientX - rect.left) / rect.width) * 100),
            y: clamp(((event.clientY - rect.top) / rect.height) * 100),
        })
    }

    return (
        <section
            aria-label="Фокальная точка"
            className="space-y-1.5"
            data-testid="asset-focal-point"
        >
            <h3 className="text-xs font-semibold text-ink dark:text-slate-200">Фокальная точка</h3>
            <p className="text-xs text-graphite dark:text-slate-500">
                Кликните по главному объекту: при обрезке на сайте он останется в кадре.
            </p>
            <button
                type="button"
                aria-label="Выбрать фокальную точку"
                onClick={pick}
                className="relative block w-full cursor-crosshair overflow-hidden rounded-lg bg-surface-strong dark:bg-slate-800"
            >
                <img
                    src={asset.publicPath}
                    alt=""
                    className="max-h-48 w-full object-contain"
                    draggable={false}
                />
                {value !== null ? (
                    <span
                        data-testid="focal-marker"
                        className="pointer-events-none absolute h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-red-600 shadow"
                        style={{ left: `${value.x}%`, top: `${value.y}%` }}
                    />
                ) : null}
            </button>
            <div className="flex items-center justify-between text-xs text-graphite dark:text-slate-300">
                <span>
                    {value === null ? 'По центру (по умолчанию)' : `X ${value.x}% · Y ${value.y}%`}
                </span>
                {value !== null ? (
                    <Button type="button" variant="ghost" onClick={() => onChange(null)}>
                        Сбросить
                    </Button>
                ) : null}
            </div>
        </section>
    )
}
