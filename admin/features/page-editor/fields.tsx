import { forwardRef, useId, type ReactNode, type SelectHTMLAttributes } from 'react'
import { cn } from '../../shared/lib/cn'

interface FieldProps {
    label: string
    error?: string
    hint?: ReactNode
    className?: string
    children: (id: string, describedBy: string | undefined) => ReactNode
}

export function Field({ label, error, hint, className, children }: FieldProps) {
    const id = useId()
    const hintId = `${id}-hint`
    const errorId = `${id}-error`
    const describedBy =
        [hint !== undefined ? hintId : null, error !== undefined ? errorId : null]
            .filter(Boolean)
            .join(' ') || undefined

    return (
        <div className={className}>
            <label htmlFor={id} className="block text-sm font-medium text-ink dark:text-slate-200">
                {label}
            </label>
            <div className="mt-1">{children(id, describedBy)}</div>
            {hint !== undefined ? (
                <p id={hintId} className="mt-1 text-xs text-graphite dark:text-slate-400">
                    {hint}
                </p>
            ) : null}
            {error !== undefined ? (
                <p
                    id={errorId}
                    role="alert"
                    className="mt-1 text-xs text-red-700 dark:text-red-400"
                >
                    {error}
                </p>
            ) : null}
        </div>
    )
}

export const NativeSelect = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(
    function NativeSelect({ className, ...props }, ref) {
        return (
            <select
                ref={ref}
                className={cn(
                    'h-12 w-full rounded-lg border border-line-strong bg-white px-3 text-sm text-ink outline-hidden ring-brand-500 transition focus:ring-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100',
                    className,
                )}
                {...props}
            />
        )
    },
)
