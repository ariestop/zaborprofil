import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
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

const metadata = { createdAt: '2026-01-01T00:00:00Z', updatedAt: '2026-01-01T00:00:00Z' }

const hero: BuilderBlock = {
  id: 'b1',
  type: 'hero.classic',
  name: 'hero classic',
  enabled: true,
  position: 0,
  content: { title: 'Забор под ключ', subtitle: 'Монтаж за 3 дня', text: '', cta: { label: 'Рассчитать стоимость', href: '#lead' }, image: '', imageAlt: '' },
  settings: {},
  metadata,
}

const prices: BuilderBlock = {
  id: 'b2',
  type: 'price-table',
  name: 'Цены',
  enabled: true,
  position: 1,
  content: { title: '', columns: ['Позиция', 'Единица', 'Цена, ₽'], rows: [['Позиция 1', 'м.п.', 'от 1 000']] },
  settings: {},
  metadata,
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

const onOpenSeo = vi.fn()

async function renderWorkspace() {
  renderWithProviders(
    <ContentTab pageType="service" pageH1="Забор из профнастила" pagePath="/zabor/" metaDescription="" ogImage="" onOpenSeo={onOpenSeo} />,
  )
  // Рабочее место грузится лениво (React.lazy): на медленном CI первый импорт дольше секунды.
  await screen.findByRole('region', { name: 'Предпросмотр страницы' }, { timeout: 4000 })
}

function structure() {
  return within(screen.getByRole('list', { name: 'Блоки страницы' }))
}

beforeAll(async () => {
  await import('./workspace/ContentWorkspace')
}, 30000)

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockResolvedValue({ templates: [customSection] })
  onOpenSeo.mockReset()
  loginAs('ROLE_ADMIN')
  useBuilderStore.getState().setBlocks([hero, prices])
  useBuilderStore.getState().selectBlock(hero.id)
})

afterEach(cleanup)

describe('ContentTab: рабочее место блоков', () => {
  it('shows human block names, summaries and placeholder markers instead of technical types', async () => {
    await renderWorkspace()

    const rows = structure().getAllByTestId('structure-row')
    expect(rows[0]?.textContent).toContain('Первый экран')
    expect(rows[0]?.textContent).toContain('Забор под ключ')
    expect(rows[0]?.textContent).not.toContain('hero classic')
    expect(rows[1]?.textContent).toContain('Цены')
    expect(rows[1]?.textContent).toContain('заготовка')
    expect(screen.getByText('Заготовки в 1 блоке')).toBeTruthy()
    expect(screen.queryByText(/Content JSON/)).toBeNull()
  })

  it('edits block fields in a form and updates the page preview immediately', async () => {
    await renderWorkspace()

    const fields = within(screen.getByRole('complementary', { name: 'Поля блока' }))
    const title = fields.getByLabelText(/^Заголовок/) as HTMLInputElement
    expect(title.value).toBe('Забор под ключ')

    fireEvent.change(title, { target: { value: 'Заборы с гарантией 5 лет' } })

    expect(useBuilderStore.getState().blocks[0]?.content.title).toBe('Заборы с гарантией 5 лет')
    expect(useBuilderStore.getState().dirty).toBe(true)
    expect(within(screen.getByRole('region', { name: 'Предпросмотр страницы' })).getByText('Заборы с гарантией 5 лет')).toBeTruthy()
  })

  it('reports a missing required field right in the form', async () => {
    await renderWorkspace()

    fireEvent.change(within(screen.getByRole('complementary', { name: 'Поля блока' })).getByLabelText(/^Заголовок/), { target: { value: '' } })

    expect(await screen.findByText('Укажите заголовок первого экрана.')).toBeTruthy()
    expect(structure().getAllByTestId('structure-row')[0]?.textContent).toContain('ошибка')
  })

  it('edits price table cells and adds a row', async () => {
    await renderWorkspace()
    fireEvent.click(structure().getByRole('button', { name: /Цены/ }))

    fireEvent.change(await screen.findByLabelText('Позиция, строка 1'), { target: { value: 'Забор С8' } })
    fireEvent.click(screen.getByRole('button', { name: 'Добавить строку' }))

    const content = useBuilderStore.getState().blocks[1]?.content
    expect(content?.rows).toEqual([['Забор С8', 'м.п.', 'от 1 000'], ['', '', '']])
  })

  it('adds a block from the catalog after the selected one and opens its fields', async () => {
    await renderWorkspace()

    fireEvent.click(screen.getByRole('button', { name: 'Добавить блок после «Первый экран»' }))
    const dialog = await screen.findByRole('dialog', { name: 'Добавить блок' })
    expect(within(dialog).getByText('Вставим после блока «Первый экран»')).toBeTruthy()
    expect(within(dialog).getAllByRole('listitem')).toHaveLength(11)

    fireEvent.change(within(dialog).getByLabelText('Найти блок'), { target: { value: 'вопрос' } })
    fireEvent.click(within(dialog).getByRole('button', { name: /Вопросы и ответы/ }))

    const blocks = useBuilderStore.getState().blocks
    expect(blocks.map((block) => block.type)).toEqual(['hero.classic', 'faq', 'price-table'])
    expect(blocks[1]?.name).toBe('Вопросы и ответы')
    expect(useBuilderStore.getState().selectedBlockId).toBe(blocks[1]?.id)
  })

  it('deletes a block and brings it back with «Отменить»', async () => {
    await renderWorkspace()

    fireEvent.click(within(screen.getByRole('complementary', { name: 'Поля блока' })).getByRole('button', { name: 'Удалить блок' }))
    expect(useBuilderStore.getState().blocks.map((block) => block.id)).toEqual(['b2'])

    const toast = (await screen.findByText('Блок «Первый экран» удалён')).closest('[role="status"]') as HTMLElement
    fireEvent.click(within(toast).getByRole('button', { name: 'Отменить' }))

    await waitFor(() => expect(useBuilderStore.getState().blocks.map((block) => block.id)).toEqual(['b1', 'b2']))
    expect(useBuilderStore.getState().selectedBlockId).toBe('b1')
  })

  it('moves and hides blocks from the selection toolbar', async () => {
    await renderWorkspace()

    fireEvent.click(screen.getByRole('button', { name: 'Переместить ниже' }))
    expect(useBuilderStore.getState().blocks.map((block) => block.id)).toEqual(['b2', 'b1'])

    fireEvent.click(screen.getByRole('button', { name: 'Скрыть блок на сайте' }))
    expect(useBuilderStore.getState().blocks.find((block) => block.id === 'b1')?.enabled).toBe(false)
  })

  it('points to SEO from the readiness checklist', async () => {
    await renderWorkspace()

    const readiness = within(screen.getByRole('region', { name: 'Готовность к публикации' }))
    expect(readiness.getByText('Нет описания для поиска')).toBeTruthy()
    fireEvent.click(readiness.getAllByRole('button', { name: 'SEO' })[0]!)
    expect(onOpenSeo).toHaveBeenCalled()
  })

  it('offers section templates only to roles with pages.manage_templates', async () => {
    await renderWorkspace()
    expect(screen.getByRole('button', { name: 'Сохранить как шаблон секции' })).toBeTruthy()

    fireEvent.click(screen.getAllByRole('button', { name: 'Добавить блок' })[0]!)
    const dialog = await screen.findByRole('dialog', { name: 'Добавить блок' })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Вставить секцию из шаблона' }))
    expect(await screen.findByText('FAQ про гарантию')).toBeTruthy()
    await waitFor(() => expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/templates?kind=section'))
  })

  it.each([['ROLE_EDITOR'], ['ROLE_SEO'], ['ROLE_MANAGER']] as const)('%s не видит действий с шаблонами', async (role) => {
    loginAs(role)
    await renderWorkspace()

    expect(screen.queryByRole('button', { name: 'Сохранить как шаблон секции' })).toBeNull()
    fireEvent.click(screen.getAllByRole('button', { name: 'Добавить блок' })[0]!)
    const dialog = await screen.findByRole('dialog', { name: 'Добавить блок' })
    expect(within(dialog).queryByRole('button', { name: 'Вставить секцию из шаблона' })).toBeNull()
  })
})
