import * as TabsPrimitive from '@radix-ui/react-tabs'
import type { ReactNode } from 'react'

interface TabItem {
  value: string
  label: string
  content: ReactNode
}

interface TabsProps {
  value: string
  onValueChange: (value: string) => void
  items: TabItem[]
}

export function Tabs({ value, onValueChange, items }: TabsProps) {
  return (
    <TabsPrimitive.Root value={value} onValueChange={onValueChange}>
      <TabsPrimitive.List className="mb-3 inline-flex rounded-lg bg-slate-100 p-1 dark:bg-slate-800">
        {items.map((item) => (
          <TabsPrimitive.Trigger
            key={item.value}
            value={item.value}
            className="rounded-md px-3 py-1.5 text-sm text-slate-600 data-[state=active]:bg-white data-[state=active]:text-slate-900 dark:text-slate-200 dark:data-[state=active]:bg-slate-900"
          >
            {item.label}
          </TabsPrimitive.Trigger>
        ))}
      </TabsPrimitive.List>
      {items.map((item) => (
        <TabsPrimitive.Content key={item.value} value={item.value}>
          {item.content}
        </TabsPrimitive.Content>
      ))}
    </TabsPrimitive.Root>
  )
}
