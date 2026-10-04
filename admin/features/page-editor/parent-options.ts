import type { ContentPageItem } from '../../types/api'

export interface ParentOption {
  value: string
  label: string
}

function collectDescendantIds(pages: ContentPageItem[], rootId: string): Set<string> {
  const descendants = new Set<string>()
  let frontier = [rootId]

  while (frontier.length > 0) {
    const next = pages
      .filter((page) => page.parentId != null && frontier.includes(page.parentId) && !descendants.has(page.id))
      .map((page) => page.id)
    next.forEach((id) => descendants.add(id))
    frontier = next
  }

  return descendants
}

/** Страницы, которые можно выбрать родителем: без самой страницы, её потомков и удалённых. */
export function buildParentOptions(pages: ContentPageItem[], currentPageId: string | null, currentParentId: string): ParentOption[] {
  const excluded = currentPageId === null ? new Set<string>() : collectDescendantIds(pages, currentPageId)
  if (currentPageId !== null) {
    excluded.add(currentPageId)
  }

  const options = pages
    .filter((page) => !excluded.has(page.id) && (page.status !== 'deleted' || page.id === currentParentId))
    .map((page) => ({ value: page.id, label: `${page.title} — ${page.path}` }))

  if (currentParentId !== '' && !options.some((option) => option.value === currentParentId)) {
    options.push({ value: currentParentId, label: 'Текущая родительская страница' })
  }

  return options
}
