import * as DropdownMenu from '@radix-ui/react-dropdown-menu'
import type { ReactNode } from 'react'

interface DropdownItem {
  key: string
  label: string
  onSelect: () => void
}

interface DropdownProps {
  trigger: ReactNode
  items: DropdownItem[]
}

export function Dropdown({ trigger, items }: DropdownProps) {
  return (
    <DropdownMenu.Root>
      <DropdownMenu.Trigger asChild>{trigger}</DropdownMenu.Trigger>
      <DropdownMenu.Portal>
        <DropdownMenu.Content className="z-50 min-w-48 rounded-lg border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
          {items.map((item) => (
            <DropdownMenu.Item
              key={item.key}
              onSelect={item.onSelect}
              className="cursor-pointer rounded px-2 py-1.5 text-sm text-slate-700 outline-none hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-slate-800"
            >
              {item.label}
            </DropdownMenu.Item>
          ))}
        </DropdownMenu.Content>
      </DropdownMenu.Portal>
    </DropdownMenu.Root>
  )
}
