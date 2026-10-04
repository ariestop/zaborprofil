import { describe, expect, it } from 'vitest'
import {
  buildSnippetUrl,
  descriptionLimits,
  evaluateLength,
  resolveSeoTitle,
  titleLimits,
  truncateForSnippet,
} from './snippet'
import { readSeoTitleSettings } from './seoSettings'

describe('evaluateLength', () => {
  it('reports empty, short, good, long and over-limit values', () => {
    expect(evaluateLength('   ', titleLimits).status).toBe('empty')
    expect(evaluateLength('Заборы', titleLimits).status).toBe('short')
    expect(evaluateLength('Заборы из профнастила под ключ', titleLimits).status).toBe('good')
    expect(evaluateLength('а'.repeat(61), titleLimits).status).toBe('long')
    expect(evaluateLength('а'.repeat(256), titleLimits).status).toBe('over-limit')
  })

  it('counts length in characters and ignores surrounding spaces', () => {
    expect(evaluateLength('  привет  ', titleLimits).length).toBe(6)
    expect(evaluateLength('😀'.repeat(3), titleLimits).length).toBe(3)
  })

  it('uses description limits', () => {
    expect(evaluateLength('а'.repeat(79), descriptionLimits).status).toBe('short')
    expect(evaluateLength('а'.repeat(80), descriptionLimits).status).toBe('good')
    expect(evaluateLength('а'.repeat(155), descriptionLimits).status).toBe('good')
    expect(evaluateLength('а'.repeat(156), descriptionLimits).status).toBe('long')
    expect(evaluateLength('а'.repeat(321), descriptionLimits).status).toBe('over-limit')
  })

  it('explains how to fix the problem', () => {
    expect(evaluateLength('а'.repeat(70), titleLimits).hint).toContain('обрежет')
    expect(evaluateLength('Заборы', titleLimits).hint).toContain('ключевые слова')
  })
})

describe('resolveSeoTitle', () => {
  const base = { title: 'Забор', h1: 'Установка забора', siteName: 'ЗаборПрофиль' }

  it('prefers explicit meta title over template', () => {
    expect(resolveSeoTitle({ ...base, metaTitle: ' Свой title ', template: '{h1} | {site_name}' })).toBe('Свой title')
  })

  it('falls back to page title without template', () => {
    expect(resolveSeoTitle({ ...base, metaTitle: '', template: '' })).toBe('Забор')
  })

  it('applies template placeholders', () => {
    expect(resolveSeoTitle({ ...base, metaTitle: '', template: '{h1} — заборы в Москве | {site_name}' }))
      .toBe('Установка забора — заборы в Москве | ЗаборПрофиль')
    expect(resolveSeoTitle({ ...base, metaTitle: '', template: '{title}/{title}' })).toBe('Забор/Забор')
  })

  it('falls back to title when template renders empty', () => {
    expect(resolveSeoTitle({ ...base, metaTitle: '', template: '{site_name}', siteName: ' ' })).toBe('Забор')
  })
})

describe('truncateForSnippet', () => {
  it('keeps short text and truncates long text with ellipsis', () => {
    expect(truncateForSnippet('Короткий', 60)).toBe('Короткий')
    const truncated = truncateForSnippet('а'.repeat(80), 60)
    expect(Array.from(truncated)).toHaveLength(60)
    expect(truncated.endsWith('…')).toBe(true)
  })
})

describe('buildSnippetUrl', () => {
  it('builds host and breadcrumbs from origin and path', () => {
    expect(buildSnippetUrl('https://zaborprofil.ru', '/catalog/zabory/')).toEqual({
      host: 'zaborprofil.ru',
      crumbs: ['catalog', 'zabory'],
    })
  })

  it('prefers canonical url when present', () => {
    expect(buildSnippetUrl('https://dev.zaborprofil.ru', '/zabory/', 'https://zaborprofil.ru/zabory/')).toEqual({
      host: 'zaborprofil.ru',
      crumbs: ['zabory'],
    })
  })
})

describe('readSeoTitleSettings', () => {
  it('reads template and site name with defaults', () => {
    expect(readSeoTitleSettings(undefined)).toEqual({ titleTemplate: '', siteName: 'ЗаборПрофиль' })
    expect(readSeoTitleSettings([
      { id: '1', scope: 'seo', key: 'title_template', value: '{h1} | {site_name}', description: null, updatedAt: '' },
      { id: '2', scope: 'seo', key: 'site_name', value: 'Забор-Про', description: null, updatedAt: '' },
      { id: '3', scope: 'content', key: 'title_template', value: 'ignored', description: null, updatedAt: '' },
    ])).toEqual({ titleTemplate: '{h1} | {site_name}', siteName: 'Забор-Про' })
  })
})
