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

/**
 * Телефон в виде «+7 (900) 123-45-67»: ведущие 8 и 7 приводятся к «+7», лишние цифры отбрасываются.
 * Пока человек печатает, возвращается частично заполненная маска.
 */
export function formatPhone(value: string): string {
  let digits = value.replace(/\D+/g, '')
  if (digits === '') {
    return ''
  }
  if (digits[0] === '8' || digits[0] === '7') {
    digits = digits.slice(1)
  }
  digits = digits.slice(0, 10)

  const parts = [digits.slice(0, 3), digits.slice(3, 6), digits.slice(6, 8), digits.slice(8, 10)]
  let result = '+7'
  if (parts[0] !== '') {
    result += ` (${parts[0]}`
  }
  if (parts[0].length === 3) {
    result += ')'
  }
  if (parts[1] !== '') {
    result += ` ${parts[1]}`
  }
  if (parts[2] !== '') {
    result += `-${parts[2]}`
  }
  if (parts[3] !== '') {
    result += `-${parts[3]}`
  }

  return result
}

export interface LeadFormErrors {
  name?: string
  phone?: string
  consent?: string
}

/** Проверка на стороне браузера до отправки: понятные подсказки вместо общей ошибки сервера. */
export function validateLeadFields(values: { name: string; phone: string; consent: boolean }): LeadFormErrors {
  const errors: LeadFormErrors = {}
  if (values.name.trim().length < 2) {
    errors.name = 'Укажите имя'
  }
  if (values.phone.replace(/\D+/g, '').length < 10) {
    errors.phone = 'Введите телефон полностью, например +7 900 123-45-67'
  }
  if (!values.consent) {
    errors.consent = 'Нужно согласие на обработку данных'
  }

  return errors
}

/** Подставляет в сообщение выбранный вариант («Largo Премиум»), не затирая то, что человек уже написал. */
export function applyLeadPlan(message: string, plan: string): string {
  const prefix = 'Интересует: '
  const line = `${prefix}${plan}`
  const rest = message.split('\n').filter((row) => !row.startsWith(prefix)).join('\n').trim()

  return rest === '' ? line : `${line}\n${rest}`
}
