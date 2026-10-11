/**
 * Сигнал «сессия истекла»: API-клиент сообщает о нём, уведомление в оболочке админки показывает его.
 * Сессия пропадает по истечении срока, после смены пароля или выхода в другой вкладке.
 */
export const SESSION_EXPIRED_MESSAGE = 'Сессия истекла. Войдите в админку снова.'

type SessionExpiredListener = () => void

const listeners = new Set<SessionExpiredListener>()

export function onSessionExpired(listener: SessionExpiredListener): () => void {
  listeners.add(listener)

  return () => {
    listeners.delete(listener)
  }
}

export function notifySessionExpired(): void {
  listeners.forEach((listener) => listener())
}
