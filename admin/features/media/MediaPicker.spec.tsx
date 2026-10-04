import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ToastProvider } from '../../app/providers/toast-provider'
import type { MediaAssetItem } from '../../types/api'
import { MediaPicker, resolveMediaValue } from './MediaPicker'

const asset: MediaAssetItem = {
  id: 'a1',
  originalName: 'fence.jpg',
  filename: 'fence.jpg',
  publicPath: '/uploads/media/fence.jpg',
  mimeType: 'image/jpeg',
  size: 2048,
  width: 800,
  height: 600,
  variants: [],
  alt: 'Забор',
  title: null,
  createdAt: '2026-01-01T00:00:00+00:00',
}

const apiRequest = vi.fn()

vi.mock('../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

afterEach(() => {
  cleanup()
  apiRequest.mockReset()
})

function renderPicker(props: Partial<React.ComponentProps<typeof MediaPicker>> = {}) {
  apiRequest.mockResolvedValue({ assets: [asset], pagination: { page: 1, perPage: 12, total: 1, totalPages: 1 } })
  const onChange = vi.fn()
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  render(
    <QueryClientProvider client={client}>
      <ToastProvider>
        <MediaPicker label="Изображение" value="" onChange={onChange} {...props} />
      </ToastProvider>
    </QueryClientProvider>,
  )

  return onChange
}

describe('resolveMediaValue', () => {
  it('returns a site-relative path by default', () => {
    expect(resolveMediaValue(asset, false)).toBe('/uploads/media/fence.jpg')
  })

  it('prefixes the current origin for absolute urls', () => {
    expect(resolveMediaValue(asset, true)).toBe(`${window.location.origin}/uploads/media/fence.jpg`)
  })
})

describe('MediaPicker', () => {
  it('shows an empty field without preview and without a clear button', () => {
    renderPicker()

    expect(screen.getByLabelText('Изображение')).toHaveProperty('value', '')
    expect(screen.getByPlaceholderText('Файл не выбран')).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'Очистить' })).toBeNull()
    expect(document.querySelector('[data-testid="media-picker"] img')).toBeNull()
  })

  it('renders a preview for the current image and clears it', () => {
    const onChange = renderPicker({ value: '/uploads/media/fence.jpg' })

    expect(document.querySelector('[data-testid="media-picker"] img')?.getAttribute('src')).toBe('/uploads/media/fence.jpg')

    fireEvent.click(screen.getByRole('button', { name: 'Очистить' }))
    expect(onChange).toHaveBeenCalledWith('')
  })

  it('does not render a preview when the picker accepts any file type', () => {
    renderPicker({ value: '/uploads/media/price.pdf', imagesOnly: false })

    expect(document.querySelector('[data-testid="media-picker"] img')).toBeNull()
  })

  it('lets the user type a url manually unless manual input is disabled', () => {
    const onChange = renderPicker()
    fireEvent.change(screen.getByLabelText('Изображение'), { target: { value: 'https://cdn.example.test/a.jpg' } })
    expect(onChange).toHaveBeenCalledWith('https://cdn.example.test/a.jpg')

    cleanup()
    renderPicker({ allowManualInput: false })
    expect(screen.getByLabelText('Изображение').hasAttribute('readonly')).toBe(true)
  })

  it('opens the library dialog and reports the chosen asset with a relative path', async () => {
    const onChange = renderPicker()

    fireEvent.click(screen.getByRole('button', { name: 'Выбрать из медиатеки' }))
    const dialog = await screen.findByRole('dialog', { name: 'Выбор из медиатеки' })
    fireEvent.doubleClick(await within(dialog).findByRole('button', { name: /fence\.jpg/ }))

    expect(onChange).toHaveBeenCalledWith('/uploads/media/fence.jpg', asset)
    expect(screen.queryByRole('dialog')).toBeNull()
  })

  it('reports an absolute url for social previews', async () => {
    const onChange = renderPicker({ absoluteUrl: true })

    fireEvent.click(screen.getByRole('button', { name: 'Выбрать из медиатеки' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.doubleClick(await within(dialog).findByRole('button', { name: /fence\.jpg/ }))

    expect(onChange).toHaveBeenCalledWith(`${window.location.origin}/uploads/media/fence.jpg`, asset)
  })

  it('requests only images from the library when imagesOnly is on', async () => {
    renderPicker({ imagesOnly: true })

    fireEvent.click(screen.getByRole('button', { name: 'Выбрать из медиатеки' }))
    await screen.findByRole('dialog')
    await within(await screen.findByRole('dialog')).findByRole('button', { name: /fence\.jpg/ })

    const requestedUrls = apiRequest.mock.calls.map((call) => String(call[0]))
    expect(requestedUrls.some((url) => url.includes('/admin/api/media/assets') && url.includes('type=image'))).toBe(true)
  })

  it('keeps the value untouched when the dialog is closed without a choice', async () => {
    const onChange = renderPicker({ value: '/uploads/media/fence.jpg' })

    fireEvent.click(screen.getByRole('button', { name: 'Выбрать из медиатеки' }))
    await screen.findByRole('dialog')
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' })

    expect(onChange).not.toHaveBeenCalled()
  })
})
