import * as CheckboxPrimitive from '@radix-ui/react-checkbox'
import { cn } from '../lib/cn'

interface CheckboxProps {
  checked: boolean
  onCheckedChange: (checked: boolean) => void
  label?: string
}

export function Checkbox({ checked, onCheckedChange, label }: CheckboxProps) {
  return (
    <label className="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
      <CheckboxPrimitive.Root
        checked={checked}
        onCheckedChange={(value) => onCheckedChange(Boolean(value))}
        className={cn(
          'h-5 w-5 rounded border border-slate-400 bg-white data-[state=checked]:border-emerald-600 data-[state=checked]:bg-emerald-600 dark:border-slate-600 dark:bg-slate-900',
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
