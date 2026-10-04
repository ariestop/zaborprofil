import { describe, expect, it } from 'vitest'
import { makePage } from './fixtures'
import {
  emptyFormValues,
  hasFieldChanges,
  pageEditorSchema,
  pageToFormValues,
  pathFromSlug,
  pickFields,
  PAGE_SEO_FIELDS,
  PAGE_SETTINGS_FIELDS,
  slugify,
  tabForField,
  toPagePayload,
  toSeoPayload,
} from './form'

describe('pageToFormValues', () => {
  it('keeps parentId so that saving settings does not reset the parent page', () => {
    const values = pageToFormValues(makePage({ parentId: 'parent-1' }))

    expect(values.parentId).toBe('parent-1')
    expect(toPagePayload(values).parentId).toBe('parent-1')
  })

  it('maps empty parent to null in the payload', () => {
    expect(toPagePayload(pageToFormValues(makePage({ parentId: null }))).parentId).toBeNull()
  })

  it('serializes JSON-LD for the textarea and back', () => {
    const jsonLd = [{ '@type': 'Organization', name: 'Заборпрофиль' }]
    const values = pageToFormValues(makePage({ seo: { ...makePage().seo, jsonLd } }))

    expect(JSON.parse(values.jsonLd)).toEqual(jsonLd)
    expect(toSeoPayload(values).jsonLd).toEqual(jsonLd)
  })
})

describe('toSeoPayload', () => {
  it('sends null for blank fields and trims text', () => {
    const payload = toSeoPayload({ ...emptyFormValues(), metaTitle: '  Заголовок  ', metaDescription: '   ' })

    expect(payload.metaTitle).toBe('Заголовок')
    expect(payload.metaDescription).toBeNull()
    expect(payload.jsonLd).toBeNull()
  })
})

describe('pageEditorSchema', () => {
  const valid = { ...emptyFormValues(), title: 'Заборы', h1: 'Заборы', slug: 'zabory', path: '/zabory/' }

  it('accepts a valid form', () => {
    expect(pageEditorSchema.safeParse(valid).success).toBe(true)
  })

  it('rejects slug with slash and path without leading slash', () => {
    const result = pageEditorSchema.safeParse({ ...valid, slug: 'a/b', path: 'zabory' })

    expect(result.success).toBe(false)
    expect(result.error?.issues.map((issue) => issue.path[0])).toEqual(expect.arrayContaining(['slug', 'path']))
  })

  it('rejects NaN sortOrder and invalid JSON-LD', () => {
    const result = pageEditorSchema.safeParse({ ...valid, sortOrder: Number.NaN, jsonLd: '{"a":1}' })

    expect(result.success).toBe(false)
    expect(result.error?.issues.map((issue) => issue.path[0])).toEqual(expect.arrayContaining(['sortOrder', 'jsonLd']))
  })

  it('limits meta title length', () => {
    expect(pageEditorSchema.safeParse({ ...valid, metaTitle: 'а'.repeat(256) }).success).toBe(false)
  })
})

describe('change tracking helpers', () => {
  it('detects changes only in the requested group of fields', () => {
    const saved = emptyFormValues()
    const current = { ...saved, metaTitle: 'SEO' }

    expect(hasFieldChanges(current, saved, PAGE_SEO_FIELDS)).toBe(true)
    expect(hasFieldChanges(current, saved, PAGE_SETTINGS_FIELDS)).toBe(false)
  })

  it('pickFields copies only the selected group', () => {
    const saved = emptyFormValues()
    const current = { ...saved, metaTitle: 'SEO', title: 'Новое' }
    const next = pickFields(current, saved, PAGE_SEO_FIELDS)

    expect(next.metaTitle).toBe('SEO')
    expect(next.title).toBe(saved.title)
  })

  it('maps fields to tabs', () => {
    expect(tabForField('metaTitle')).toBe('seo')
    expect(tabForField('slug')).toBe('settings')
  })
})

describe('slug helpers', () => {
  it('transliterates cyrillic titles', () => {
    expect(slugify('Забор из профнастила №1')).toBe('zabor-iz-profnastila-1')
    expect(slugify('  Ёлка!  ')).toBe('elka')
  })

  it('builds path from slug and parent path', () => {
    expect(pathFromSlug('zabory')).toBe('/zabory/')
    expect(pathFromSlug('profnastil', '/zabory/')).toBe('/zabory/profnastil/')
    expect(pathFromSlug('')).toBe('')
  })
})
