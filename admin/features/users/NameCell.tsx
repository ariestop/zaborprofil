import { useState } from 'react'
import { Input } from '../../shared/ui'

interface NameCellProps {
  name: string | null
  email: string
  onSave: (name: string) => Promise<void>
}

/** Имя пользователя, которое правится прямо в таблице: сохраняется по Enter или при уходе из поля. */
export function NameCell({ name, email, onSave }: NameCellProps) {
  const saved = name ?? ''
  const [draft, setDraft] = useState(saved)
  const [prevSaved, setPrevSaved] = useState(saved)
  const [saving, setSaving] = useState(false)

  // Сервер вернул другое имя (например, после обновления списка) — показываем его.
  if (prevSaved !== saved) {
    setPrevSaved(saved)
    setDraft(saved)
  }

  const commit = async () => {
    const next = draft.trim()
    if (next === saved || saving) {
      setDraft(next)

      return
    }
    setSaving(true)
    try {
      await onSave(next)
    } catch {
      setDraft(saved)
    } finally {
      setSaving(false)
    }
  }

  return (
    <Input
      value={draft}
      maxLength={120}
      disabled={saving}
      placeholder="Не указано"
      aria-label={`Имя пользователя ${email}`}
      onChange={(event) => setDraft(event.target.value)}
      onBlur={() => void commit()}
      onKeyDown={(event) => {
        if (event.key === 'Enter') {
          event.preventDefault()
          event.currentTarget.blur()
        }
      }}
    />
  )
}
