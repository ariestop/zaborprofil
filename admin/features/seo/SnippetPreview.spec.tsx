import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { LengthCounter } from './LengthCounter'
import { SnippetPreview } from './SnippetPreview'
import { descriptionLimits, titleLimits } from './snippet'

afterEach(cleanup)

const baseProps = {
  metaTitle: '',
  title: 'Забор из профнастила',
  h1: 'Установка заборов',
  path: '/zabory/profnastil/',
  canonicalUrl: '',
  metaDescription: '',
  titleTemplate: '',
  siteName: 'ЗаборПрофиль',
  origin: 'https://zaborprofil.ru',
}

describe('SnippetPreview', () => {
  it('shows page title, url crumbs and description placeholder', () => {
    render(<SnippetPreview {...baseProps} />)

    expect(screen.getByTestId('snippet-title').textContent).toBe('Забор из профнастила')
    expect(screen.getByTestId('snippet-url').textContent).toBe('zaborprofil.ru › zabory › profnastil')
    expect(screen.getByTestId('snippet-description').textContent).toContain('Описание не задано')
  })

  it('prefers meta title over template and truncates long text', () => {
    render(<SnippetPreview {...baseProps} metaTitle={'а'.repeat(80)} titleTemplate="{h1} | {site_name}" metaDescription={'б'.repeat(200)} />)

    const title = screen.getByTestId('snippet-title').textContent ?? ''
    expect(Array.from(title)).toHaveLength(60)
    expect(title.endsWith('…')).toBe(true)
    expect(Array.from(screen.getByTestId('snippet-description').textContent ?? '')).toHaveLength(155)
  })

  it('uses default template when meta title is empty', () => {
    render(<SnippetPreview {...baseProps} titleTemplate="{h1} | {site_name}" />)

    expect(screen.getByTestId('snippet-title').textContent).toBe('Установка заборов | ЗаборПрофиль')
  })
})

describe('LengthCounter', () => {
  it('marks status and hint for the value', () => {
    const { rerender } = render(<LengthCounter value="" limits={titleLimits} emptyHint="Пусто — берём title." testId="counter" />)
    expect(screen.getByTestId('counter').dataset.status).toBe('empty')
    expect(screen.getByTestId('counter').textContent).toContain('Пусто — берём title.')

    rerender(<LengthCounter value={'а'.repeat(70)} limits={titleLimits} testId="counter" />)
    expect(screen.getByTestId('counter').dataset.status).toBe('long')
    expect(screen.getByTestId('counter').textContent).toContain('70 / 60')

    rerender(<LengthCounter value={'а'.repeat(100)} limits={descriptionLimits} testId="counter" />)
    expect(screen.getByTestId('counter').dataset.status).toBe('good')
  })
})
