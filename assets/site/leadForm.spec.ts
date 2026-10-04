import { describe, expect, it } from 'vitest'
import { collectUtm, initLeadFormContext } from './leadForm'

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
