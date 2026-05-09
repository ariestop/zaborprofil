import * as SwitchPrimitive from '@radix-ui/react-switch'

interface SwitchProps {
  checked: boolean
  onCheckedChange: (checked: boolean) => void
}

export function Switch({ checked, onCheckedChange }: SwitchProps) {
  return (
    <SwitchPrimitive.Root
      checked={checked}
      onCheckedChange={onCheckedChange}
      className="relative h-6 w-11 rounded-full bg-slate-300 transition data-[state=checked]:bg-emerald-600 dark:bg-slate-700"
    >
      <SwitchPrimitive.Thumb className="block h-5 w-5 translate-x-0.5 rounded-full bg-white transition will-change-transform data-[state=checked]:translate-x-[1.4rem]" />
    </SwitchPrimitive.Root>
  )
}
