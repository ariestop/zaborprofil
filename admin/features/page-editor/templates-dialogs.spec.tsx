import { cleanup, fireEvent, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { BuilderBlock } from '../../modules/page-builder/types'
import type { PageTemplateItem } from '../../types/api'
import { loginAs } from '../../shared/testing/roles'
import { renderWithProviders } from '../seo/test-utils'
import { SaveTemplateDialog } from './SaveTemplateDialog'
import { SectionTemplatesDialog } from './SectionTemplatesDialog'

const apiRequest = vi.fn()

vi.mock('../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

const hero: BuilderBlock = {
  id: 'b1',
  type: 'hero.classic',
  enabled: true,
  position: 0,
  content: { title: 'Забор под ключ' },
  settings: {},
  metadata: { createdAt: '2026-01-01T00:00:00Z', updatedAt: '2026-01-01T00:00:00Z' },
}

const section: PageTemplateItem = {
  id: 't1',
  code: 'custom_faq',
  name: 'FAQ про гарантию',
  description: null,
  kind: 'section',
  pageType: 'service',
  blocksSchema: [{ type: 'faq', name: 'FAQ', position: 0, content: { items: [{ question: 'Q', answer: 'A' }] }, settings: {}, isEnabled: true }],
  defaultSeo: {},
  defaultSettings: {},
  isSystem: false,
  isActive: true,
}

beforeEach(() => {
  apiRequest.mockReset()
})

afterEach(cleanup)

describe('SaveTemplateDialog', () => {
  it('saves the selected block as a section template', async () => {
    apiRequest.mockResolvedValue(section)
    const onClose = vi.fn()
    renderWithProviders(<SaveTemplateDialog request={{ kind: 'section', blocks: [hero] }} pageType="service" onClose={onClose} />)

    fireEvent.click(await screen.findByRole('button', { name: 'Сохранить шаблон' }))
    expect(await screen.findByText('Укажите название шаблона.')).toBeTruthy()
    expect(apiRequest).not.toHaveBeenCalled()

    fireEvent.change(screen.getByLabelText('Название шаблона'), { target: { value: '  Первый экран  ' } })
    fireEvent.click(screen.getByRole('button', { name: 'Сохранить шаблон' }))

    await waitFor(() => expect(onClose).toHaveBeenCalled())
    expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/templates', {
      method: 'POST',
      body: expect.objectContaining({ name: 'Первый экран', kind: 'section', pageType: 'service', description: null }) as unknown,
    })
  })

  it('shows the server validation message', async () => {
    const { ApiError } = await import('../../shared/api/client')
    apiRequest.mockRejectedValue(new ApiError('Block content field "title" must be a non-empty string.', 422, { error: 'Block content field "title" must be a non-empty string.' }))
    renderWithProviders(<SaveTemplateDialog request={{ kind: 'page', blocks: [hero] }} pageType="service" onClose={vi.fn()} />)

    fireEvent.change(await screen.findByLabelText('Название шаблона'), { target: { value: 'Шаблон' } })
    fireEvent.click(screen.getByRole('button', { name: 'Сохранить шаблон' }))

    expect((await screen.findByRole('alert')).textContent).toContain('must be a non-empty string')
  })
})

describe('SectionTemplatesDialog', () => {
  it('inserts the chosen section template', async () => {
    apiRequest.mockResolvedValue({ templates: [section] })
    const onInsert = vi.fn()
    renderWithProviders(<SectionTemplatesDialog open onClose={vi.fn()} onInsert={onInsert} />)

    expect(await screen.findByText('FAQ про гарантию')).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Вставить' }))

    expect(onInsert).toHaveBeenCalledWith(section)
    expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/templates?kind=section')
  })

  it('asks for confirmation before deleting a custom template', async () => {
    apiRequest.mockImplementation((url: string, options?: { method?: string }) => {
      if (options?.method === 'DELETE') {
        return Promise.resolve(null)
      }
      return Promise.resolve({ templates: [section] })
    })
    renderWithProviders(<SectionTemplatesDialog open onClose={vi.fn()} onInsert={vi.fn()} />)

    fireEvent.click(await screen.findByRole('button', { name: 'Удалить' }))
    expect(apiRequest).not.toHaveBeenCalledWith('/admin/api/content/templates/custom_faq', expect.anything())

    fireEvent.click(screen.getByRole('button', { name: 'Точно удалить' }))
    await waitFor(() => expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/templates/custom_faq', { method: 'DELETE' }))
  })
})

describe('SectionTemplatesDialog: удаление по праву pages.manage_templates', () => {
  beforeEach(() => {
    apiRequest.mockResolvedValue({ templates: [section] })
  })

  it.each([
    ['ROLE_EDITOR'],
    ['ROLE_SEO'],
    ['ROLE_MANAGER'],
  ] as const)('%s не видит удаление пользовательских шаблонов', async (role) => {
    loginAs(role)
    renderWithProviders(<SectionTemplatesDialog open onClose={vi.fn()} onInsert={vi.fn()} />)

    expect(await screen.findByText('FAQ про гарантию')).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'Удалить' })).toBeNull()
  })

  it('ROLE_ADMIN видит удаление пользовательских шаблонов', async () => {
    loginAs('ROLE_ADMIN')
    renderWithProviders(<SectionTemplatesDialog open onClose={vi.fn()} onInsert={vi.fn()} />)

    expect(await screen.findByRole('button', { name: 'Удалить' })).toBeTruthy()
  })
})
