import DOMPurify from 'dompurify'

/**
 * Тот же набор, что у серверного санитайзера `app.rich_text_sanitizer` (config/packages/html_sanitizer.yaml).
 * Превью в редакторе показывает и HTML, сохранённый до появления серверной очистки, и шаблоны секций,
 * и правки, ещё не отправленные на сервер, поэтому разметку перед вставкой очищаем и здесь.
 */
const RICH_TEXT_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'a', 'span']
const RICH_TEXT_ATTRIBUTES = ['style', 'href', 'title', 'target', 'rel']

export function sanitizeRichTextHtml(html: string): string {
  return DOMPurify.sanitize(html, {
    ALLOWED_TAGS: RICH_TEXT_TAGS,
    ALLOWED_ATTR: RICH_TEXT_ATTRIBUTES,
  })
}
