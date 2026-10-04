import { cleanup, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import type { PageTemplateItem } from '../../types/api'
import { TemplatePreview } from './TemplatePreview'

afterEach(cleanup)

const template: PageTemplateItem = {
  id: 'template-1',
  code: 'fence_profnastil',
  name: 'Забор из профнастила',
  description: 'Услуга «забор из профнастила».',
  kind: 'page',
  pageType: 'service',
  blocksSchema: [
    { type: 'faq', name: 'Вопросы и ответы', position: 1, content: {}, settings: {}, isEnabled: false, hint: 'Замените ответы на реальные.' },
    { type: 'hero.classic', name: 'Первый экран', position: 0, content: {}, settings: {}, isEnabled: true, hint: 'Укажите город.' },
  ],
  defaultSeo: {},
  defaultSettings: {},
  isSystem: true,
  isActive: true,
}

describe('TemplatePreview', () => {
  it('lists blocks in order with fill-in hints', () => {
    render(<TemplatePreview template={template} />)

    const items = screen.getAllByRole('listitem')
    expect(items).toHaveLength(2)
    expect(items[0]?.textContent).toContain('Первый экран')
    expect(items[0]?.textContent).toContain('Укажите город.')
    expect(items[1]?.textContent).toContain('Вопросы и ответы')
    expect(items[1]?.textContent).toContain('(выключен)')
    expect(screen.getByText('Услуга «забор из профнастила».')).toBeTruthy()
  })
})
