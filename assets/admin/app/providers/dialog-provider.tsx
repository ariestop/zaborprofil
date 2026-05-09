import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react'
import { ConfirmDialog } from '../../shared/ui/confirm-dialog'

interface ConfirmOptions {
  title: string
  description?: string
  confirmLabel?: string
  cancelLabel?: string
}

interface DialogContextValue {
  confirm: (options: ConfirmOptions) => Promise<boolean>
}

const DialogContext = createContext<DialogContextValue | null>(null)

interface PendingDialog extends ConfirmOptions {
  resolver: (result: boolean) => void
}

export function DialogProvider({ children }: { children: ReactNode }) {
  const [pending, setPending] = useState<PendingDialog | null>(null)

  const confirm = useCallback((options: ConfirmOptions) => {
    return new Promise<boolean>((resolve) => {
      setPending({ ...options, resolver: resolve })
    })
  }, [])

  const close = useCallback((value: boolean) => {
    setPending((current) => {
      if (current !== null) {
        current.resolver(value)
      }
      return null
    })
  }, [])

  const value = useMemo<DialogContextValue>(() => ({ confirm }), [confirm])

  return (
    <DialogContext.Provider value={value}>
      {children}
      <ConfirmDialog
        open={pending !== null}
        title={pending?.title ?? ''}
        description={pending?.description}
        confirmLabel={pending?.confirmLabel}
        cancelLabel={pending?.cancelLabel}
        onConfirm={() => close(true)}
        onCancel={() => close(false)}
      />
    </DialogContext.Provider>
  )
}

export function useDialog(): DialogContextValue {
  const context = useContext(DialogContext)
  if (context === null) {
    throw new Error('useDialog must be used within DialogProvider')
  }

  return context
}
