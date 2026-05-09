import * as DialogPrimitive from '@radix-ui/react-dialog'
import type { ReactNode } from 'react'

interface DialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  description?: string
  contentClassName?: string
  closeOnInteractOutside?: boolean
  closeOnEscape?: boolean
  children: ReactNode
}

export function Dialog({
  open,
  onOpenChange,
  title,
  description,
  contentClassName,
  closeOnInteractOutside = true,
  closeOnEscape = true,
  children,
}: DialogProps) {
  return (
    <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-40 bg-slate-950/40" />
        <DialogPrimitive.Content
          className={[
            'fixed left-1/2 top-1/2 z-50 w-full max-w-lg -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-800 dark:bg-slate-900',
            contentClassName ?? '',
          ].join(' ')}
          onPointerDownOutside={(event) => {
            if (!closeOnInteractOutside) {
              event.preventDefault()
            }
          }}
          onInteractOutside={(event) => {
            if (!closeOnInteractOutside) {
              event.preventDefault()
            }
          }}
          onEscapeKeyDown={(event) => {
            if (!closeOnEscape) {
              event.preventDefault()
            }
          }}
        >
          <DialogPrimitive.Title className="text-base font-semibold">{title}</DialogPrimitive.Title>
          {description !== undefined ? (
            <DialogPrimitive.Description className="mt-1 text-sm text-slate-500 dark:text-slate-400">
              {description}
            </DialogPrimitive.Description>
          ) : null}
          <div className="mt-4">{children}</div>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  )
}
