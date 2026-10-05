import * as DropdownMenu from '@radix-ui/react-dropdown-menu'
import type { ReactNode } from 'react'

export interface DropdownItem {
  key: string
  label: string
  onSelect: () => void
  /** Опасное действие: красный текст. */
  danger?: boolean
  disabled?: boolean
}

interface DropdownProps {
  trigger: ReactNode
  items: DropdownItem[]
  align?: 'start' | 'end'
}

export function Dropdown({ trigger, items, align = 'start' }: DropdownProps) {
  return (
    <DropdownMenu.Root>
      <DropdownMenu.Trigger asChild>{trigger}</DropdownMenu.Trigger>
      <DropdownMenu.Portal>
        <DropdownMenu.Content align={align} sideOffset={6} className="z-50 min-w-48 rounded-lg border border-slate-200 bg-white p-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
          {items.map((item) => (
            <DropdownMenu.Item
              key={item.key}
              onSelect={item.onSelect}
              disabled={item.disabled}
              className={[
                'cursor-pointer rounded-sm px-2 py-1.5 text-sm outline-hidden hover:bg-slate-100 data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50 data-[highlighted]:bg-slate-100 dark:hover:bg-slate-800 dark:data-[highlighted]:bg-slate-800',
                item.danger === true ? 'text-red-700 dark:text-red-400' : 'text-slate-700 dark:text-slate-100',
              ].join(' ')}
            >
              {item.label}
            </DropdownMenu.Item>
          ))}
        </DropdownMenu.Content>
      </DropdownMenu.Portal>
    </DropdownMenu.Root>
  )
}
