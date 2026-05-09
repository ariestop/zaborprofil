import * as ToastPrimitive from '@radix-ui/react-toast'

export interface ToastMessage {
  id: string
  title: string
  description?: string
}

interface ToastProps {
  message: ToastMessage
  onOpenChange: (open: boolean) => void
}

export function Toast({ message, onOpenChange }: ToastProps) {
  return (
    <ToastPrimitive.Root
      open
      onOpenChange={onOpenChange}
      className="rounded-lg border border-slate-200 bg-white p-3 shadow-lg dark:border-slate-700 dark:bg-slate-900"
    >
      <ToastPrimitive.Title className="text-sm font-semibold">{message.title}</ToastPrimitive.Title>
      {message.description !== undefined ? (
        <ToastPrimitive.Description className="mt-1 text-xs text-slate-500 dark:text-slate-400">
          {message.description}
        </ToastPrimitive.Description>
      ) : null}
    </ToastPrimitive.Root>
  )
}
