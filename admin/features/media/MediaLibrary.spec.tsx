import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import type { ReactNode } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ToastProvider } from '../../app/providers/toast-provider'
import { ApiError } from '../../shared/api/client'
import type { MediaAssetItem } from '../../types/api'
import { MediaLibrary } from './MediaLibrary'
import { MediaPicker } from './MediaPicker'

const apiRequest = vi.fn()
const apiUpload = vi.fn()

vi.mock('../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../shared/api/client')>()
  return {
    ...original,
    apiRequest: (...args: unknown[]) => apiRequest(...args),
    apiUpload: (...args: unknown[]) => apiUpload(...args),
  }
})

function makeAsset(id: string, overrides: Partial<MediaAssetItem> = {}): MediaAssetItem {
  return {
    id,
    originalName: `${id}.jpg`,
    filename: `${id}.jpg`,
    publicPath: `/uploads/media/${id}.jpg`,
    mimeType: 'image/jpeg',
    size: 2048,
    width: 800,
    height: 600,
    variants: [],
    alt: null,
    title: null,
    createdAt: '2026-01-01T00:00:00+00:00',
    ...overrides,
  }
}

function listResponse(assets: MediaAssetItem[], pagination: Partial<{ page: number; perPage: number; total: number; totalPages: number }> = {}) {
  return {
    assets,
    pagination: { page: 1, perPage: 24, total: assets.length, totalPages: 1, ...pagination },
  }
}

function renderWithProviders(node: ReactNode) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ToastProvider>{node}</ToastProvider>
    </QueryClientProvider>,
  )
}

function lastListPath(): string {
  const calls = apiRequest.mock.calls.filter(([path]) => typeof path === 'string' && path.startsWith('/admin/api/media/assets?'))
  return String(calls[calls.length - 1]?.[0])
}

beforeEach(() => {
  apiRequest.mockReset()
  apiUpload.mockReset()
})

afterEach(cleanup)

describe('MediaLibrary', () => {
  it('renders the asset grid and shows details with alt/title on click', async () => {
    apiRequest.mockResolvedValue(listResponse([makeAsset('fence', { alt: 'Забор' })]))
    renderWithProviders(<MediaLibrary mode="manage" />)

    const grid = await screen.findByTestId('media-grid')
    fireEvent.click(within(grid).getByRole('button', { name: /fence\.jpg/ }))

    const details = await screen.findByTestId('asset-details')
    expect((within(details).getByLabelText(/Alt/) as HTMLInputElement).value).toBe('Забор')
    expect(within(details).queryByRole('button', { name: 'Выбрать' })).toBeNull()
  })

  it('searches with debounce and resets the page', async () => {
    apiRequest.mockResolvedValue(listResponse([makeAsset('a')], { total: 50, totalPages: 3 }))
    renderWithProviders(<MediaLibrary mode="manage" />)
    await screen.findByTestId('media-grid')

    fireEvent.click(screen.getByRole('button', { name: 'Вперёд' }))
    await waitFor(() => expect(lastListPath()).toContain('page=2'))

    fireEvent.change(screen.getByLabelText('Поиск по медиатеке'), { target: { value: 'забор' } })
    await waitFor(() => expect(lastListPath()).toContain('q=%D0%B7%D0%B0%D0%B1%D0%BE%D1%80'))
    expect(lastListPath()).toContain('page=1')
  })

  it('paginates through the server pagination', async () => {
    apiRequest.mockResolvedValue(listResponse([makeAsset('a')], { total: 50, totalPages: 3 }))
    renderWithProviders(<MediaLibrary mode="manage" />)

    expect(await screen.findByTestId('media-page-indicator')).toHaveProperty('textContent', 'Страница 1 из 3')
    expect((screen.getByRole('button', { name: 'Назад' }) as HTMLButtonElement).disabled).toBe(true)
    fireEvent.click(screen.getByRole('button', { name: 'Вперёд' }))
    await waitFor(() => expect(lastListPath()).toContain('page=2'))
  })

  it('uploads files with progress, per-file errors and selects the uploaded asset', async () => {
    const uploaded = makeAsset('new')
    apiRequest.mockResolvedValue(listResponse([]))
    apiUpload.mockImplementation(async (_path: string, body: FormData, options: { onProgress?: (value: number) => void }) => {
      const file = body.get('file') as File
      options.onProgress?.(0.5)
      if (file.name === 'broken.png') {
        throw new ApiError('Uploaded file MIME type is not allowed.', 422, { error: 'x', code: 'VALIDATION' })
      }
      return uploaded
    })
    renderWithProviders(<MediaLibrary mode="manage" />)
    await screen.findByText('Медиатека пуста')

    const input = screen.getByTestId('media-file-input')
    fireEvent.change(input, {
      target: {
        files: [
          new File(['x'], 'new.png', { type: 'image/png' }),
          new File(['x'], 'broken.png', { type: 'image/png' }),
          new File(['x'], 'notes.txt', { type: 'text/plain' }),
        ],
      },
    })

    await waitFor(() => expect(screen.getAllByTestId('upload-item')).toHaveLength(3))
    await waitFor(() => expect(screen.getByText('Загружен')).toBeTruthy())
    expect(screen.getAllByRole('alert').map((node) => node.textContent)).toEqual(
      expect.arrayContaining([expect.stringContaining('Содержимое файла'), expect.stringContaining('Недопустимый тип файла')]),
    )
    expect(apiUpload).toHaveBeenCalledTimes(2)
    expect(await screen.findByTestId('asset-details')).toBeTruthy()
  })

  it('accepts dropped files', async () => {
    apiRequest.mockResolvedValue(listResponse([]))
    apiUpload.mockResolvedValue(makeAsset('dropped'))
    renderWithProviders(<MediaLibrary mode="manage" />)
    await screen.findByText('Медиатека пуста')

    const file = new File(['x'], 'dropped.png', { type: 'image/png' })
    fireEvent.drop(screen.getByTestId('media-library'), { dataTransfer: { files: [file], types: ['Files'] } })

    await waitFor(() => expect(apiUpload).toHaveBeenCalledTimes(1))
  })

  it('asks for confirmation before deleting', async () => {
    apiRequest.mockImplementation(async (path: string, options?: { method?: string }) => {
      if (options?.method === 'DELETE') return null
      return listResponse([makeAsset('gone')])
    })
    renderWithProviders(<MediaLibrary mode="manage" />)

    fireEvent.click(await screen.findByRole('button', { name: /gone\.jpg/ }))
    const details = await screen.findByTestId('asset-details')
    fireEvent.click(within(details).getByRole('button', { name: 'Удалить' }))

    const dialog = await screen.findByRole('dialog')
    expect(apiRequest).not.toHaveBeenCalledWith('/admin/api/media/assets/gone', expect.anything())
    fireEvent.click(within(dialog).getByRole('button', { name: 'Удалить' }))

    await waitFor(() => expect(apiRequest).toHaveBeenCalledWith('/admin/api/media/assets/gone', { method: 'DELETE' }))
  })

  it('saves alt and title through PATCH', async () => {
    apiRequest.mockImplementation(async (path: string, options?: { method?: string; body?: unknown }) => {
      if (options?.method === 'PATCH') return makeAsset('a', { alt: 'Новый alt', title: 'T' })
      return listResponse([makeAsset('a')])
    })
    renderWithProviders(<MediaLibrary mode="manage" />)

    fireEvent.click(await screen.findByRole('button', { name: /a\.jpg/ }))
    const details = await screen.findByTestId('asset-details')
    fireEvent.change(within(details).getByLabelText(/Alt/), { target: { value: 'Новый alt' } })
    fireEvent.change(within(details).getByLabelText(/Title/), { target: { value: 'T' } })
    fireEvent.click(within(details).getByRole('button', { name: 'Сохранить' }))

    await waitFor(() =>
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/media/assets/a', { method: 'PATCH', body: { alt: 'Новый alt', title: 'T' } }),
    )
  })

  it('shows an error state when the list fails', async () => {
    apiRequest.mockRejectedValue(new ApiError('Internal server error', 500, { error: 'Internal server error', code: 'INTERNAL' }))
    renderWithProviders(<MediaLibrary mode="manage" />)

    expect(await screen.findByText('Не удалось загрузить медиатеку')).toBeTruthy()
  })
})

describe('MediaPicker', () => {
  it('selects an asset from the library and reports the public path with the asset', async () => {
    const onChange = vi.fn()
    apiRequest.mockResolvedValue(listResponse([makeAsset('hero', { alt: 'Hero alt' })]))
    renderWithProviders(<MediaPicker label="Изображение" value="" onChange={onChange} />)

    fireEvent.click(screen.getByRole('button', { name: 'Выбрать из медиатеки' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(await within(dialog).findByRole('button', { name: /hero\.jpg/ }))
    fireEvent.click(await within(dialog).findByRole('button', { name: 'Выбрать' }))

    expect(onChange).toHaveBeenCalledWith('/uploads/media/hero.jpg', expect.objectContaining({ id: 'hero' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull())
  })

  it('can produce absolute URLs and clear the value', async () => {
    const onChange = vi.fn()
    apiRequest.mockResolvedValue(listResponse([makeAsset('og')]))
    renderWithProviders(<MediaPicker label="OG" value="https://x.test/a.jpg" absoluteUrl onChange={onChange} />)

    fireEvent.click(screen.getByRole('button', { name: 'Очистить' }))
    expect(onChange).toHaveBeenCalledWith('')

    fireEvent.click(screen.getByRole('button', { name: 'Выбрать из медиатеки' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.doubleClick(await within(dialog).findByRole('button', { name: /og\.jpg/ }))

    expect(onChange).toHaveBeenLastCalledWith(`${window.location.origin}/uploads/media/og.jpg`, expect.objectContaining({ id: 'og' }))
  })

  it('lets the user upload straight from the picker and choose the new file', async () => {
    const onChange = vi.fn()
    const uploaded = makeAsset('fresh')
    apiRequest.mockResolvedValue(listResponse([]))
    apiUpload.mockResolvedValue(uploaded)
    renderWithProviders(<MediaPicker label="Изображение" value="" onChange={onChange} />)

    fireEvent.click(screen.getByRole('button', { name: 'Выбрать из медиатеки' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.change(within(dialog).getByTestId('media-file-input'), { target: { files: [new File(['x'], 'fresh.png', { type: 'image/png' })] } })

    fireEvent.click(await within(dialog).findByRole('button', { name: 'Выбрать' }))
    expect(onChange).toHaveBeenCalledWith('/uploads/media/fresh.jpg', expect.objectContaining({ id: 'fresh' }))
  })
})
