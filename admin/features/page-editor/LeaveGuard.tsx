import { useCallback, useEffect, useRef } from 'react'
import { useBlocker, type Location } from 'react-router-dom'
import { Button, Dialog } from '../../shared/ui'

interface LeaveGuardProps {
  when: boolean
  /** Переходы, для которых предупреждение не нужно (например, между вкладками одной страницы). */
  isAllowed?: (nextLocation: Location) => boolean
  /** Сохраняет изменения; после успеха `when` становится false и переход продолжается. Если не задан, кнопки «Сохранить и уйти» нет. */
  onSaveAndLeave?: () => Promise<unknown>
}

/** Позволяет программно отключить предупреждение перед намеренным переходом (после создания или удаления). */
export function useLeaveBypass(): { isBypassed: () => boolean; bypass: () => void } {
  const bypassedRef = useRef(false)
  const bypass = useCallback(() => {
    bypassedRef.current = true
  }, [])
  const isBypassed = useCallback(() => bypassedRef.current, [])

  return { isBypassed, bypass }
}

export function LeaveGuard({ when, isAllowed, onSaveAndLeave }: LeaveGuardProps) {
  const blocker = useBlocker(({ nextLocation }) => when && isAllowed?.(nextLocation) !== true)

  useEffect(() => {
    if (!when) {
      return
    }

    const handler = (event: BeforeUnloadEvent) => {
      event.preventDefault()
      event.returnValue = ''
    }

    window.addEventListener('beforeunload', handler)
    return () => window.removeEventListener('beforeunload', handler)
  }, [when])

  useEffect(() => {
    if (blocker.state === 'blocked' && !when) {
      blocker.proceed()
    }
  }, [blocker, when])

  return (
    <Dialog
      open={blocker.state === 'blocked'}
      onOpenChange={(open) => {
        if (!open && blocker.state === 'blocked') {
          blocker.reset()
        }
      }}
      title="Есть несохранённые изменения"
      description="Если уйти сейчас, внесённые правки будут потеряны."
    >
      <div className="flex flex-wrap justify-end gap-2">
        <Button type="button" variant="ghost" onClick={() => blocker.state === 'blocked' && blocker.reset()}>
          Остаться
        </Button>
        <Button type="button" variant="outline" onClick={() => blocker.state === 'blocked' && blocker.proceed()}>
          Уйти без сохранения
        </Button>
        {onSaveAndLeave !== undefined ? (
          <Button
            type="button"
            onClick={() => {
              void onSaveAndLeave()
            }}
          >
            Сохранить и уйти
          </Button>
        ) : null}
      </div>
    </Dialog>
  )
}
