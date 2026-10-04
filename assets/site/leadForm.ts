/**
 * Страница отдаётся из HTTP-кэша, поэтому серверный HTML не содержит ни времени загрузки формы,
 * ни адреса запроса: антиспам-проверка «слишком быстрая отправка» и источник заявки заполняются в браузере.
 */
export function initLeadFormContext(form: HTMLFormElement, now: Date = new Date(), pageUrl: string = window.location.href): void {
  const loadedAt = form.elements.namedItem('formLoadedAt')
  const page = form.elements.namedItem('pageUrl')

  if (loadedAt instanceof HTMLInputElement) {
    loadedAt.value = now.toISOString()
  }

  if (page instanceof HTMLInputElement) {
    page.value = pageUrl
  }
}

const UTM_KEYS = ['source', 'medium', 'campaign', 'term', 'content'] as const
const UTM_STORAGE_KEY = 'zp.utm'

function sessionStorageOrNull(): Storage | null {
  try {
    return window.sessionStorage
  } catch {
    return null
  }
}

/**
 * UTM-метки заявки: берутся из адреса и запоминаются на время визита, чтобы заявка с внутренней страницы
 * не теряла рекламный источник, с которого человек пришёл на сайт.
 */
export function collectUtm(search: string = window.location.search, storage: Storage | null = sessionStorageOrNull()): Record<string, string> {
  const query = new URLSearchParams(search)
  const fresh: Record<string, string> = {}
  for (const key of UTM_KEYS) {
    const value = query.get(`utm_${key}`)?.trim()
    if (value) {
      fresh[key] = value.slice(0, 120)
    }
  }

  try {
    if (Object.keys(fresh).length > 0) {
      storage?.setItem(UTM_STORAGE_KEY, JSON.stringify(fresh))

      return fresh
    }

    const stored = storage?.getItem(UTM_STORAGE_KEY)
    const parsed: unknown = stored ? JSON.parse(stored) : null

    return parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed) ? (parsed as Record<string, string>) : {}
  } catch {
    return fresh
  }
}
