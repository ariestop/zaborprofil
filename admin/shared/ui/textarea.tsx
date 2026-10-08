import { forwardRef } from 'react'
import type { TextareaHTMLAttributes } from 'react'
import { cn } from '../lib/cn'

export const Textarea = forwardRef<
    HTMLTextAreaElement,
    TextareaHTMLAttributes<HTMLTextAreaElement>
>(function Textarea({ className, ...props }, ref) {
    return (
        <textarea
            ref={ref}
            className={cn(
                'min-h-24 w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm text-ink outline-hidden ring-brand-500 transition focus:ring-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100',
                className,
            )}
            {...props}
        />
    )
})
