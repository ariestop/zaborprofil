import { cn } from '../../shared/lib/cn'

export interface FilterSelectOption {
    value: string
    label: string
}

interface FilterSelectProps {
    label: string
    value: string
    options: FilterSelectOption[]
    onChange: (value: string) => void
    /** Подсветить кнопку: фильтр применён. */
    active?: boolean
}

/** Кнопка-фильтр из макета: видимая подпись и шеврон, а выбор делает прозрачный нативный select поверх. */
export function FilterSelect({
    label,
    value,
    options,
    onChange,
    active = false,
}: FilterSelectProps) {
    const current = options.find((option) => option.value === value)

    return (
        <label
            className={cn(
                'relative inline-flex h-8 cursor-pointer items-center gap-1 rounded-lg border px-2.5 text-[13px] font-medium focus-within:ring-2 focus-within:ring-brand-500',
                active
                    ? 'border-brand-200 bg-brand-50 text-brand-800 dark:border-brand-800 dark:bg-brand-900/30 dark:text-brand-200'
                    : 'border-line-strong bg-white text-graphite dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
            )}
        >
            <span className="max-w-44 truncate">{current?.label ?? label}</span>
            <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
            >
                <path d="M6 9.5l6 6 6-6" />
            </svg>
            <select
                aria-label={label}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    )
}
