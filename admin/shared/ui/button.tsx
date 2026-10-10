import { forwardRef } from 'react'
import type { ButtonHTMLAttributes } from 'react'
import { cn } from '../lib/cn'

type ButtonVariant = 'default' | 'outline' | 'ghost' | 'danger'
type ButtonSize = 'sm' | 'md'

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant
    size?: ButtonSize
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
    { className, variant = 'default', size = 'md', ...props },
    ref,
) {
    return (
        <button
            ref={ref}
            className={cn(
                'inline-flex items-center justify-center rounded-lg font-medium transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-60',
                size === 'sm' ? 'h-10 px-3.5 text-sm' : 'h-12 px-5 text-sm',
                variant === 'default' && 'bg-brand-600 text-white hover:bg-brand-700',
                variant === 'outline' &&
                    'border border-line-strong bg-white text-ink hover:bg-surface dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800',
                variant === 'ghost' &&
                    'bg-transparent text-ink hover:bg-surface-strong dark:text-slate-200 dark:hover:bg-slate-800',
                variant === 'danger' && 'bg-red-600 text-white hover:bg-red-700',
                className,
            )}
            {...props}
        />
    )
})
