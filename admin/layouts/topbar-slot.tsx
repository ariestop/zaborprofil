import { createContext, useContext, type ReactNode } from 'react'
import { createPortal } from 'react-dom'

/** Место в шапке, куда страница выводит свои действия («Экспорт CSV», «Новая заявка» и т. п.). */
export const TopbarSlotContext = createContext<HTMLElement | null>(null)

export function TopbarActions({ children }: { children: ReactNode }) {
  const slot = useContext(TopbarSlotContext)

  return slot === null ? null : createPortal(children, slot)
}
