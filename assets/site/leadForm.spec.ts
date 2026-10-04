import { describe, expect, it } from 'vitest'
import { initLeadFormContext } from './leadForm'

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
