import * as CheckboxPrimitive from '@radix-ui/react-checkbox'
import { cn } from '../lib/cn'

interface CheckboxProps {
    checked: boolean
    onCheckedChange: (checked: boolean) => void
    label?: string
    ariaLabel?: string
}

export function Checkbox({ checked, onCheckedChange, label, ariaLabel }: CheckboxProps) {
    return (
        <label className="inline-flex items-center gap-2 text-sm text-ink dark:text-slate-200">
            <CheckboxPrimitive.Root
                checked={checked}
                aria-label={ariaLabel}
                onCheckedChange={(value) => onCheckedChange(Boolean(value))}
                className={cn(
                    'h-5 w-5 rounded-sm border border-line-strong bg-white data-[state=checked]:border-brand-600 data-[state=checked]:bg-brand-600 dark:border-slate-600 dark:bg-slate-900',
                )}
            >
                <CheckboxPrimitive.Indicator className="grid place-items-center text-xs text-white">
                    ✓
                </CheckboxPrimitive.Indicator>
            </CheckboxPrimitive.Root>
            {label !== undefined ? <span>{label}</span> : null}
        </label>
    )
}
