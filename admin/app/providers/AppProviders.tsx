import { QueryClientProvider } from '@tanstack/react-query'
import { useEffect, type ReactNode } from 'react'
import { DialogProvider } from './dialog-provider'
import { ThemeProvider } from './theme-provider'
import { ToastProvider } from './toast-provider'
import { CommandPaletteProvider, useCommandPalette } from './command-palette-provider'
import { queryClient } from './query-client'

function KeyboardShortcuts() {
  const { open: openCommandPalette } = useCommandPalette()

  useEffect(() => {
    const listener = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        openCommandPalette()
      }
    }

    window.addEventListener('keydown', listener)
    return () => window.removeEventListener('keydown', listener)
  }, [openCommandPalette])

  return null
}

export function AppProviders({ children }: { children: ReactNode }) {
  return (
    <ThemeProvider>
      <QueryClientProvider client={queryClient}>
        <ToastProvider>
          <DialogProvider>
            <CommandPaletteProvider>
              <KeyboardShortcuts />
              {children}
            </CommandPaletteProvider>
          </DialogProvider>
        </ToastProvider>
      </QueryClientProvider>
    </ThemeProvider>
  )
}
