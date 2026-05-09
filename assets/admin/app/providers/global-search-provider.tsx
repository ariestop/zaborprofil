import { createContext, useContext, useMemo, useState, type ReactNode } from 'react'

interface GlobalSearchContextValue {
  isOpen: boolean
  open: () => void
  close: () => void
}

const GlobalSearchContext = createContext<GlobalSearchContextValue | null>(null)

export function GlobalSearchProvider({ children }: { children: ReactNode }) {
  const [isOpen, setIsOpen] = useState(false)

  const value = useMemo<GlobalSearchContextValue>(() => ({
    isOpen,
    open: () => setIsOpen(true),
    close: () => setIsOpen(false),
  }), [isOpen])

  return (
    <GlobalSearchContext.Provider value={value}>
      {children}
    </GlobalSearchContext.Provider>
  )
}

export function useGlobalSearch(): GlobalSearchContextValue {
  const context = useContext(GlobalSearchContext)
  if (context === null) {
    throw new Error('useGlobalSearch must be used within GlobalSearchProvider')
  }

  return context
}
