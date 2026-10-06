import { forwardRef } from 'react'
import type { InputHTMLAttributes } from 'react'
import { cn } from '../lib/cn'

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(
    function Input({ className, ...props }, ref) {
        return (
            <input
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
