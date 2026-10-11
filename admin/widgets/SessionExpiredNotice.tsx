import { useEffect, useState } from 'react'
import { onSessionExpired } from '../shared/api/session'
import { Button } from '../shared/ui/button'

export const ADMIN_LOGIN_URL = '/admin/login'

/**
 * Постоянное уведомление об истёкшей сессии: тост пропал бы через пару секунд, а без входа
 * не выполнится ни одно действие. Правки редактора страницы при уходе на вход остаются в черновике браузера.
 */
export function SessionExpiredNotice() {
  const [visible, setVisible] = useState(false)

  useEffect(() => onSessionExpired(() => setVisible(true)), [])

  if (!visible) {
    return null
  }

  return (
    <div
      role="alert"
      className="fixed left-1/2 top-4 z-70 w-[min(36rem,calc(100vw-2rem))] -translate-x-1/2 rounded-xl border border-amber-300 bg-amber-50 p-4 text-ink shadow-lg dark:border-amber-800 dark:bg-amber-950 dark:text-amber-50"
    >
      <p className="font-semibold">Сессия истекла</p>
      <p className="mt-1 text-sm">
        Последнее действие не выполнено. Войдите снова и повторите его. Несохранённые правки в редакторе страницы останутся в черновике этого браузера.
      </p>
      <div className="mt-3 flex flex-wrap gap-2">
        <a
          href={ADMIN_LOGIN_URL}
          className="inline-flex h-10 items-center justify-center rounded-lg bg-brand-600 px-3.5 text-sm font-medium text-white transition hover:bg-brand-700 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500"
        >
          Войти снова
        </a>
        <Button type="button" size="sm" variant="ghost" onClick={() => setVisible(false)}>
          Закрыть
        </Button>
      </div>
    </div>
  )
}
