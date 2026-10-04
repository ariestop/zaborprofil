import { ApiError } from '../../shared/api/client'

export const EDIT_CONFLICT_CODE = 'EDIT_CONFLICT'

export interface EditConflict {
  /** Актуальная версия блоков на сервере: подставляется как `baseVersion` при перезаписи. */
  serverVersion: string
  serverUpdatedAt: string | null
}

export function parseEditConflict(error: unknown): EditConflict | null {
  if (!(error instanceof ApiError) || error.status !== 409) {
    return null
  }

  const payload = error.payload
  if (typeof payload !== 'object' || payload === null) {
    return null
  }

  const { code, version, updatedAt } = payload as Record<string, unknown>
  if (code !== EDIT_CONFLICT_CODE || typeof version !== 'string') {
    return null
  }

  return { serverVersion: version, serverUpdatedAt: typeof updatedAt === 'string' ? updatedAt : null }
}

export const EDIT_CONFLICT_MESSAGE = 'Блоки страницы изменил другой пользователь. Выберите, как продолжить.'
