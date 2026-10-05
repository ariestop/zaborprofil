import { describe, expect, it } from 'vitest'
import { makePageItem } from '../page-editor/fixtures'
import type { PageTemplateItem } from '../../types/api'
import { blocksCount, formatUpdated, pagesSummary, statusTabs, templateBars, visiblePages } from './list-model'

const now = new Date(2026, 9, 5, 15, 0)

describe('formatUpdated', () => {
  it('names today and yesterday, short dates this year and the year otherwise', () => {
    expect(formatUpdated(new Date(2026, 9, 5, 14, 32).toISOString(), now)).toBe('сегодня, 14:32')
    expect(formatUpdated(new Date(2026, 9, 4, 18, 40).toISOString(), now)).toBe('вчера, 18:40')
    expect(formatUpdated(new Date(2026, 9, 2, 10, 20).toISOString(), now)).toMatch(/^2 окт\.?, 10:20$/)
    expect(formatUpdated(new Date(2025, 2, 12, 9, 0).toISOString(), now)).toMatch(/^12 мар\.? 2025/)
    expect(formatUpdated(null, now)).toBe('—')
  })
})

describe('status tabs', () => {
  const pages = [
    makePageItem({ id: 'a', status: 'draft' }),
    makePageItem({ id: 'b', status: 'published' }),
    makePageItem({ id: 'c', status: 'archived' }),
    makePageItem({ id: 'd', status: 'deleted' }),
  ]

  it('shows rare statuses only when such pages exist; «Все» skips deleted pages', () => {
    const tabs = statusTabs(pages, 'all')
    expect(tabs.map((tab) => `${tab.label} ${tab.count}`)).toEqual(['Все 3', 'Черновики 1', 'На проверке 0', 'Одобрены 0', 'Опубликованы 1', 'Сняты 0', 'В архиве 1', 'Удалённые 1'])
  })

  it('keeps the active tab even when it is empty', () => {
    expect(statusTabs([], 'scheduled').map((tab) => tab.id)).toContain('scheduled')
  })

  it('sorts by title in Russian order and filters by type', () => {
    const list = [
      makePageItem({ id: '1', title: 'Ёлки', path: '/b/', type: 'service' }),
      makePageItem({ id: '2', title: 'Ворота', path: '/a/', type: 'service' }),
      makePageItem({ id: '3', title: 'Контакты', path: '/c/', type: 'contacts' }),
    ]
    expect(visiblePages(list, { status: 'all', query: '', type: 'service', sort: 'title' }).map((page) => page.id)).toEqual(['2', '1'])
  })
})

describe('summary and templates', () => {
  it('counts live pages and unpublished changes', () => {
    expect(pagesSummary([makePageItem({ status: 'published', hasUnpublishedChanges: true }), makePageItem({ status: 'deleted' })])).toBe('1 страница · опубликовано 1 · с неопубликованными правками 1')
    expect(pagesSummary([makePageItem(), makePageItem(), makePageItem(), makePageItem(), makePageItem()])).toBe('5 страниц · опубликовано 0')
  })

  it('draws a bar per template block in position order', () => {
    const template = {
      blocksSchema: [
        { type: 'contact-form', name: 'Форма', position: 2, content: {}, settings: {}, isEnabled: true },
        { type: 'hero.classic', name: 'Первый экран', position: 0, content: {}, settings: {}, isEnabled: true },
        { type: 'rich-text', name: 'Текст', position: 1, content: {}, settings: {}, isEnabled: true },
      ],
    } as unknown as PageTemplateItem
    expect(templateBars(template).map((bar) => bar.color)).toEqual(['var(--color-graphite)', 'var(--color-line-strong)', 'var(--color-graphite)'])
    expect(blocksCount(0)).toBe('без блоков')
    expect(blocksCount(9)).toBe('9 блоков')
  })
})
