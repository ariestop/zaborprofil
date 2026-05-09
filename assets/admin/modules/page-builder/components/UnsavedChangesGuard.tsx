import { useEffect } from 'react'

interface UnsavedChangesGuardProps {
  when: boolean
}

export function UnsavedChangesGuard({ when }: UnsavedChangesGuardProps) {
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

  return null
}
