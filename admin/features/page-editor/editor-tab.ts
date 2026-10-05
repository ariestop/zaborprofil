import type { EditorTab } from './form'

/**
 * Сегмент URL → вкладка. `builder` и пустой сегмент открывают «Контент»
 * (обратная совместимость со старым маршрутом /admin/pages/:id/builder).
 * Неизвестный сегмент → null (редирект на страницу по умолчанию).
 */
export function resolveEditorTab(segment: string | undefined): EditorTab | null {
  switch (segment) {
    case undefined:
    case 'builder':
    case 'content':
      return 'content'
    case 'seo':
    case 'settings':
    case 'revisions':
      return segment
    default:
      return null
  }
}
