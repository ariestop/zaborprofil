import { cleanup, fireEvent, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useBuilderStore } from '../../modules/page-builder/state/builderStore'
import type { BuilderBlock } from '../../modules/page-builder/types'
import { loginAs } from '../../shared/testing/roles'
import type { PageTemplateItem } from '../../types/api'
import { renderWithProviders } from '../seo/test-utils'
import { ContentTab } from './ContentTab'

const apiRequest = vi.fn()

vi.mock('../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

vi.mock('../../components/TiptapRichTextEditor', () => ({ default: () => null }))

const hero: BuilderBlock = {
  id: 'b1',
  type: 'hero.classic',
  enabled: true,
  position: 0,
  content: { title: 'Забор под ключ' },
  settings: {},
  metadata: { createdAt: '2026-01-01T00:00:00Z', updatedAt: '2026-01-01T00:00:00Z' },
}

const customSection: PageTemplateItem = {
  id: 't1',
  code: 'custom_faq',
  name: 'FAQ про гарантию',
  description: null,
  kind: 'section',
  pageType: 'service',
  blocksSchema: [],
  defaultSeo: {},
  defaultSettings: {},
  isSystem: false,
  isActive: true,
}

const SAVE_PAGE = 'Сохранить страницу как шаблон'
const SAVE_SECTION = 'Сохранить как шаблон секции'
const INSERT_SECTION = 'Вставить секцию из шаблона'

async function renderContentTab() {
  renderWithProviders(<ContentTab pageId="page-1" pageType="service" />)
  await screen.findByRole('button', { name: 'Дублировать' })
}

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockResolvedValue({ templates: [customSection] })
  useBuilderStore.getState().setBlocks([hero])
  useBuilderStore.getState().selectBlock(hero.id)
})

afterEach(cleanup)

describe('ContentTab: управление шаблонами по праву pages.manage_templates', () => {
  it.each([
    ['ROLE_EDITOR'],
    ['ROLE_SEO'],
    ['ROLE_MANAGER'],
  ] as const)('%s не видит кнопки шаблонов', async (role) => {
    loginAs(role)
    await renderContentTab()

    expect(screen.queryByRole('button', { name: SAVE_PAGE })).toBeNull()
    expect(screen.queryByRole('button', { name: SAVE_SECTION })).toBeNull()
    expect(screen.queryByRole('button', { name: INSERT_SECTION })).toBeNull()
  })

  it('ROLE_ADMIN видит все кнопки шаблонов', async () => {
    loginAs('ROLE_ADMIN')
    await renderContentTab()

    expect(screen.getByRole('button', { name: SAVE_PAGE })).toBeTruthy()
    expect(screen.getByRole('button', { name: SAVE_SECTION })).toBeTruthy()
    expect(screen.getByRole('button', { name: INSERT_SECTION })).toBeTruthy()
  })

  it('ROLE_ADMIN в списке секций видит удаление пользовательского шаблона', async () => {
    loginAs('ROLE_ADMIN')
    await renderContentTab()

    fireEvent.click(screen.getByRole('button', { name: INSERT_SECTION }))
    expect(await screen.findByText('FAQ про гарантию')).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Удалить' })).toBeTruthy()
    await waitFor(() => expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/templates?kind=section'))
  })
})
