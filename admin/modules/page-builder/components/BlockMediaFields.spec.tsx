import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ToastProvider } from '../../../app/providers/toast-provider'
import type { MediaAssetItem } from '../../../types/api'
import { createBlock } from '../utils/pageBlocks'
import { BlockMediaFields } from './BlockMediaFields'

const asset: MediaAssetItem = {
  id: 'g1',
  originalName: 'gate.jpg',
  filename: 'gate.jpg',
  publicPath: '/uploads/media/gate.jpg',
  mimeType: 'image/jpeg',
  size: 10,
  width: 10,
  height: 10,
  variants: [],
  alt: 'Ворота',
  title: null,
  createdAt: '2026-01-01T00:00:00+00:00',
}

vi.mock('../../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../../shared/api/client')>()
  return {
    ...original,
    apiRequest: async () => ({ assets: [asset], pagination: { page: 1, perPage: 12, total: 1, totalPages: 1 } }),
  }
})

afterEach(cleanup)

function renderFields(onUpdate: (block: unknown) => void, type: Parameters<typeof createBlock>[0]) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ToastProvider>
        <BlockMediaFields block={createBlock(type, 0)} onUpdate={onUpdate} />
      </ToastProvider>
    </QueryClientProvider>,
  )
}

describe('BlockMediaFields', () => {
  it('renders nothing for blocks without images', () => {
    const { container } = renderFields(vi.fn(), 'cta')
    expect(container.querySelector('[data-testid="block-media-fields"]')).toBeNull()
  })

  it('updates the image and alt of a block through the picker', async () => {
    const onUpdate = vi.fn()
    renderFields(onUpdate, 'hero.with-image')

    fireEvent.click(screen.getByRole('button', { name: 'Выбрать из медиатеки' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.doubleClick(await within(dialog).findByRole('button', { name: /gate\.jpg/ }))

    expect(onUpdate).toHaveBeenCalledTimes(1)
    expect(onUpdate.mock.calls[0]?.[0]).toMatchObject({ content: { image: '/uploads/media/gate.jpg', imageAlt: 'Ворота' } })
  })

  it('appends an item to gallery blocks', async () => {
    const onUpdate = vi.fn()
    renderFields(onUpdate, 'gallery')

    fireEvent.click(screen.getByRole('button', { name: 'Добавить изображение из медиатеки' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.doubleClick(await within(dialog).findByRole('button', { name: /gate\.jpg/ }))

    expect(onUpdate.mock.calls[0]?.[0]).toMatchObject({ content: { items: [{ src: '/uploads/media/gate.jpg', alt: 'Ворота' }] } })
  })
})
