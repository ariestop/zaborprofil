export const RICH_TEXT_SMOKE_APPEND = ' smoke-update'

export function buildSmokePagePayload(): Record<string, unknown> {
  const uniqueId = Date.now()

  return {
    type: 'landing',
    title: 'E2E Smoke Page',
    slug: `e2e-smoke-${uniqueId}`,
    path: `/e2e-smoke-${uniqueId}/`,
    h1: 'E2E Smoke Page',
    template: 'default',
    sortOrder: 0,
    isIndexable: true,
    visibility: 'public',
  }
}

export function buildDndPagePayload(): Record<string, unknown> {
  const uniqueId = Date.now()

  return {
    type: 'landing',
    title: 'E2E DnD Page',
    slug: `e2e-dnd-${uniqueId}`,
    path: `/e2e-dnd-${uniqueId}/`,
    h1: 'E2E DnD Page',
    template: 'default',
    sortOrder: 0,
    isIndexable: true,
    visibility: 'public',
  }
}

export function buildHeroBlockPayload(): Record<string, unknown> {
  return {
    type: 'hero',
    name: 'Hero block',
    position: 0,
    content: { title: 'Hero' },
    settings: {},
    isEnabled: true,
    visibility: 'public',
  }
}

export function buildTextBlockPayload(): Record<string, unknown> {
  return {
    type: 'text',
    name: 'Text block',
    position: 1,
    content: { richText: '<p>Initial text</p>' },
    settings: {},
    isEnabled: true,
    visibility: 'public',
  }
}

export function buildInvalidPagePayload(): Record<string, unknown> {
  return {
    type: 'landing',
    title: 'x',
    slug: '',
    path: '',
    h1: '',
    template: 'default',
    sortOrder: 0,
    isIndexable: true,
    visibility: 'public',
  }
}
