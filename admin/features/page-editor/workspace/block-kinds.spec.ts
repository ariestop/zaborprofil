import { describe, expect, it } from 'vitest'
import { blockSummary, blockTitle, canonicalType, isPlaceholderBlock, isPlaceholderText, kindName } from './block-kinds'
import { fieldSpecsFor, inferFields } from './field-specs'

describe('blockTitle', () => {
  it('replaces machine names saved by the old builder with the kind name', () => {
    expect(blockTitle({ type: 'hero.classic', name: 'hero classic' })).toBe('Первый экран')
    expect(blockTitle({ type: 'rich-text', name: 'rich text' })).toBe('Текст')
    expect(blockTitle({ type: 'price-table', name: undefined })).toBe('Цены')
  })

  it('keeps names given by templates and editors', () => {
    expect(blockTitle({ type: 'rich-text', name: 'SEO-текст' })).toBe('SEO-текст')
    expect(blockTitle({ type: 'faq', name: 'faq для дилеров' })).toBe('faq для дилеров')
  })

  it('names legacy blocks after their modern counterpart', () => {
    expect(canonicalType('hero')).toBe('hero.classic')
    expect(canonicalType('seo_text')).toBe('rich-text')
    expect(kindName('hero')).toBe('Первый экран')
    expect(kindName('html_embed')).toBe('HTML-вставка')
  })
})

describe('blockSummary', () => {
  it('describes the content in one line', () => {
    expect(blockSummary({ type: 'hero.classic', content: { title: 'Забор под ключ' } })).toBe('Забор под ключ')
    expect(blockSummary({ type: 'rich-text', content: { html: '<h2>Почему мы</h2><p>Свой цех.</p>' } })).toBe('Почему мы Свой цех.')
    expect(blockSummary({ type: 'steps', content: { items: [{ title: 'Замер', text: '' }, { title: 'Монтаж', text: '' }] } })).toBe('2 шага · Замер, Монтаж')
    expect(blockSummary({ type: 'gallery', content: { items: [] } })).toBe('Фото не добавлены')
    expect(blockSummary({ type: 'price-table', content: { columns: ['А', 'Б'], rows: [['Стандарт', 'от 1 900']] } })).toBe('1 строка · «Стандарт» от 1 900')
  })
})

describe('placeholders', () => {
  it('detects template instructions and anonymous rows', () => {
    expect(isPlaceholderText('Да. Укажите срок гарантии на материалы и монтаж.')).toBe(true)
    expect(isPlaceholderText('Позиция 1')).toBe(true)
    expect(isPlaceholderText('<h2>Подзаголовок</h2><p>Основной текст страницы.</p>')).toBe(true)
    expect(isPlaceholderText('Забор из профнастила под ключ')).toBe(false)
  })

  it('marks blocks with template texts or without photos', () => {
    expect(isPlaceholderBlock({ type: 'gallery', content: { items: [] } })).toBe(true)
    expect(isPlaceholderBlock({ type: 'faq', content: { items: [{ question: 'Какие сроки?', answer: 'Укажите реальные сроки изготовления и монтажа.' }] } })).toBe(true)
    expect(isPlaceholderBlock({ type: 'hero.classic', content: { title: 'Забор под ключ', subtitle: 'Монтаж за 3 дня' } })).toBe(false)
  })
})

describe('field specs', () => {
  it('uses hand-made forms for catalog kinds', () => {
    const specs = fieldSpecsFor('hero.classic', {})
    expect(specs.map((spec) => spec.kind)).toEqual(['text', 'textarea', 'textarea', 'group', 'image'])
  })

  it('infers a form from content for other blocks, without JSON', () => {
    const specs = inferFields({ title: 'Отзывы', items: [{ name: 'Иван', text: 'Спасибо' }], enabled: true, alt: 'skip' })
    expect(specs.map((spec) => [spec.kind, 'key' in spec ? spec.key : ''])).toEqual([
      ['text', 'title'],
      ['items', 'items'],
      ['checkbox', 'enabled'],
    ])
  })
})
