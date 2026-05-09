import * as ToastPrimitive from '@radix-ui/react-toast'
import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react'
import { Toast, type ToastMessage } from '../../shared/ui/toast'

interface ToastContextValue {
  push: (payload: Omit<ToastMessage, 'id'>) => void
}

const ToastContext = createContext<ToastContextValue | null>(null)

export function ToastProvider({ children }: { children: ReactNode }) {
  const [messages, setMessages] = useState<ToastMessage[]>([])

  const push = useCallback((payload: Omit<ToastMessage, 'id'>) => {
    const id = crypto.randomUUID()
    setMessages((current) => [...current, { id, ...payload }])
  }, [])

  const remove = useCallback((id: string) => {
    setMessages((current) => current.filter((item) => item.id !== id))
  }, [])

  const value = useMemo<ToastContextValue>(() => ({ push }), [push])

  return (
    <ToastContext.Provider value={value}>
      <ToastPrimitive.Provider swipeDirection="right" duration={2500}>
        {children}
        {messages.map((message) => (
          <Toast
            key={message.id}
            message={message}
            onOpenChange={(open) => {
              if (!open) {
                remove(message.id)
              }
            }}
          />
        ))}
        <ToastPrimitive.Viewport className="fixed bottom-5 right-5 z-[60] flex w-96 max-w-[calc(100vw-1rem)] flex-col gap-2 outline-none" />
      </ToastPrimitive.Provider>
    </ToastContext.Provider>
  )
}

export function useToast(): ToastContextValue {
  const context = useContext(ToastContext)
  if (context === null) {
    throw new Error('useToast must be used within ToastProvider')
  }

  return context
}
