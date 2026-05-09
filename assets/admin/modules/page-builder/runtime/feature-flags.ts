const FALSE_LIKE = new Set(['0', 'false', 'off', 'no'])

function normalizeFlagValue(value: string | boolean | undefined): string {
  if (typeof value === 'boolean') {
    return value ? 'true' : 'false'
  }

  if (typeof value === 'string') {
    return value.trim().toLowerCase()
  }

  return ''
}

function readBooleanFlag(value: string | boolean | undefined, fallback: boolean): boolean {
  const normalized = normalizeFlagValue(value)
  if (normalized === '') {
    return fallback
  }

  return !FALSE_LIKE.has(normalized)
}

export function isPageBuilderEnabled(): boolean {
  return readBooleanFlag(import.meta.env.VITE_ADMIN_PAGE_BUILDER_ENABLED, true)
}
