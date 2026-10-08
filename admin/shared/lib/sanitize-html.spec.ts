import { describe, expect, it } from 'vitest'
import { sanitizeRichTextHtml } from './sanitize-html'

describe('sanitizeRichTextHtml', () => {
  it('removes scripts, event handlers and javascript links', () => {
    const html = sanitizeRichTextHtml(
      `<p onclick='alert(1)'>Текст</p><a href='javascript:alert(2)'>ссылка</a><img src="x" onerror="alert(3)"><script>alert(4)</script>`,
    )

    expect(html).not.toContain('alert')
    expect(html).not.toContain('<img')
    expect(html).toContain('<p>Текст</p>')
  })

  it('keeps editor formatting, alignment and safe links', () => {
    const html = sanitizeRichTextHtml(
      '<h2 style="text-align: center">Заголовок</h2><p>Текст <strong>жирный</strong> <a href="/contacts/" target="_blank" rel="noopener">ссылка</a></p><ul><li>пункт</li></ul>',
    )

    expect(html).toContain('<h2 style="text-align: center">Заголовок</h2>')
    expect(html).toContain('<strong>жирный</strong>')
    expect(html).toContain('<a href="/contacts/" target="_blank" rel="noopener">ссылка</a>')
    expect(html).toContain('<ul><li>пункт</li></ul>')
  })

  it('drops unknown wrappers but keeps their text', () => {
    expect(sanitizeRichTextHtml('<div><mark>важно</mark></div>')).toBe('важно')
  })
})
