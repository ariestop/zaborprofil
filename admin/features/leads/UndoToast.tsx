import { useEffect } from 'react'

export interface UndoToastState {
  id: number
  text: string
}

interface UndoToastProps {
  toast: UndoToastState | null
  onUndo: () => void
  onDismiss: () => void
  durationMs?: number
}

/** Тёмная плашка внизу экрана после смены статуса: действие можно отменить в течение нескольких секунд. */
export function UndoToast({ toast, onUndo, onDismiss, durationMs = 8000 }: UndoToastProps) {
  const toastId = toast?.id

  useEffect(() => {
    if (toastId === undefined) {
      return undefined
    }
    const timer = window.setTimeout(onDismiss, durationMs)

    return () => window.clearTimeout(timer)
  }, [toastId, durationMs, onDismiss])

  if (toast === null) {
    return null
  }

  return (
    <div
      role="status"
      className="fixed bottom-6 left-1/2 z-[70] flex max-w-[calc(100%-2rem)] -translate-x-1/2 items-center gap-4 rounded-xl bg-ink py-3 pl-4 pr-3 text-sm text-white shadow-lg"
    >
      <span className="min-w-0 truncate">{toast.text}</span>
      <button
        type="button"
        onClick={onUndo}
        className="h-[34px] shrink-0 rounded-lg bg-graphite px-3 text-[13px] font-semibold text-white hover:bg-graphite focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-400"
      >
        Отменить
      </button>
    </div>
  )
}
