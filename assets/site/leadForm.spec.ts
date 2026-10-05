import { describe, expect, it } from 'vitest'
import { applyLeadPlan, collectUtm, formatPhone, initLeadFormContext, validateLeadFields } from './leadForm'

function buildForm(): HTMLFormElement {
  const form = document.createElement('form')
  form.innerHTML = '<input type="hidden" name="formLoadedAt" value=""><input type="hidden" name="pageUrl" value="">'

  return form
}

describe('initLeadFormContext', () => {
  it('fills load time and page url in the browser', () => {
    const form = buildForm()

    initLeadFormContext(form, new Date('2026-10-04T12:00:00.000Z'), 'https://zaborprofil.ru/zabory/?utm=1')

    expect(new FormData(form).get('formLoadedAt')).toBe('2026-10-04T12:00:00.000Z')
    expect(new FormData(form).get('pageUrl')).toBe('https://zaborprofil.ru/zabory/?utm=1')
  })

  it('ignores forms without context fields', () => {
    expect(() => initLeadFormContext(document.createElement('form'))).not.toThrow()
  })
})

function memoryStorage(): Storage {
  const data = new Map<string, string>()

  return {
    get length() { return data.size },
    clear: () => data.clear(),
    getItem: (key: string) => data.get(key) ?? null,
    key: (index: number) => Array.from(data.keys())[index] ?? null,
    removeItem: (key: string) => { data.delete(key) },
    setItem: (key: string, value: string) => { data.set(key, value) },
  }
}

describe('collectUtm', () => {
  it('reads utm tags from the url and remembers them for the visit', () => {
    const storage = memoryStorage()

    expect(collectUtm('?utm_source=yandex&utm_medium=cpc&utm_campaign=zabor&foo=1', storage)).toEqual({
      source: 'yandex',
      medium: 'cpc',
      campaign: 'zabor',
    })
    expect(collectUtm('', storage)).toEqual({ source: 'yandex', medium: 'cpc', campaign: 'zabor' })
  })

  it('returns an empty object without tags', () => {
    expect(collectUtm('', memoryStorage())).toEqual({})
  })
})

describe('formatPhone', () => {
  it('приводит российские номера к маске +7', () => {
    expect(formatPhone('89001234567')).toBe('+7 (900) 123-45-67')
    expect(formatPhone('+7 900 123 45 67')).toBe('+7 (900) 123-45-67')
    expect(formatPhone('9001234567')).toBe('+7 (900) 123-45-67')
  })

  it('держит частично введённую маску и не добавляет лишних цифр', () => {
    expect(formatPhone('')).toBe('')
    expect(formatPhone('8')).toBe('+7')
    expect(formatPhone('89')).toBe('+7 (9')
    expect(formatPhone('8900123')).toBe('+7 (900) 123')
    expect(formatPhone('890012345678999')).toBe('+7 (900) 123-45-67')
  })
})

describe('validateLeadFields', () => {
  it('пропускает корректные данные', () => {
    expect(validateLeadFields({ name: 'Иван', phone: '+7 (900) 123-45-67', consent: true })).toEqual({})
  })

  it('возвращает понятные подсказки по каждому полю', () => {
    const errors = validateLeadFields({ name: ' ', phone: '+7 (900)', consent: false })

    expect(errors.name).toBeDefined()
    expect(errors.phone).toBeDefined()
    expect(errors.consent).toBeDefined()
  })
})

describe('applyLeadPlan', () => {
  it('добавляет выбранный вариант и не затирает текст клиента', () => {
    expect(applyLeadPlan('', 'Largo Премиум')).toBe('Интересует: Largo Премиум')
    expect(applyLeadPlan('Нужно 40 метров', 'Largo Премиум')).toBe('Интересует: Largo Премиум\nНужно 40 метров')
  })

  it('заменяет ранее выбранный вариант', () => {
    expect(applyLeadPlan('Интересует: Largo Стандарт\nНужно 40 метров', 'Doppio Премиум')).toBe(
      'Интересует: Doppio Премиум\nНужно 40 метров',
    )
  })
})
