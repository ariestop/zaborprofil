import { createContext, useContext, useMemo, useState, type ReactNode } from 'react'

interface CommandPaletteContextValue {
  isOpen: boolean
  open: () => void
  close: () => void
}

const CommandPaletteContext = createContext<CommandPaletteContextValue | null>(null)

export function CommandPaletteProvider({ children }: { children: ReactNode }) {
  const [isOpen, setIsOpen] = useState(false)

  const value = useMemo<CommandPaletteContextValue>(() => ({
    isOpen,
    open: () => setIsOpen(true),
    close: () => setIsOpen(false),
  }), [isOpen])

  return (
    <CommandPaletteContext.Provider value={value}>
      {children}
    </CommandPaletteContext.Provider>
  )
}

export function useCommandPalette(): CommandPaletteContextValue {
  const context = useContext(CommandPaletteContext)
  if (context === null) {
    throw new Error('useCommandPalette must be used within CommandPaletteProvider')
  }

  return context
}
