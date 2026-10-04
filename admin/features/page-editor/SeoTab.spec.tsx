import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ToastProvider } from '../../app/providers/toast-provider'
import type { SeoAuditResult } from '../../entities/seo/model'
import type { MediaAssetItem } from '../../types/api'
import { useAdvancedMode } from './advanced-mode'
import { makePage } from './fixtures'
import { pageToFormValues, type PageEditorFormValues } from './form'
import { SeoTab } from './SeoTab'
import type { PageEditorController } from './usePageEditorController'

const apiRequest = vi.fn()

vi.mock('../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

const asset: MediaAssetItem = {
  id: 'og1',
  originalName: 'og-cover.jpg',
  filename: 'og-cover.jpg',
  publicPath: '/uploads/media/og-cover.jpg',
  mimeType: 'image/jpeg',
  size: 4096,
  width: 1200,
  height: 630,
  variants: [],
  alt: null,
  title: null,
  createdAt: '2026-01-01T00:00:00+00:00',
}

const emptyAssets = { assets: [asset], pagination: { page: 1, perPage: 12, total: 1, totalPages: 1 } }

function handleApi(path: string, audit?: SeoAuditResult): unknown {
  if (path.startsWith('/admin/api/media/assets')) {
    return emptyAssets
  }
  if (path.startsWith('/admin/api/seo/audit/pages/')) {
    return audit ?? { pageId: 'page-1', path: '/zabory/', passed: true, issues: [] }
  }
  if (path.startsWith('/admin/api/settings')) {
    return []
  }

  return {}
}

interface HarnessProps {
  initial?: Partial<PageEditorFormValues>
  autosaveEnabled?: boolean
  saveAll?: () => Promise<boolean>
  onOpenTab?: (tab: string) => void
  onForm?: (values: () => PageEditorFormValues) => void
}

function Harness({ initial, autosaveEnabled = true, saveAll = async () => true, onOpenTab = () => undefined, onForm }: HarnessProps) {
  const form = useForm<PageEditorFormValues>({ defaultValues: { ...pageToFormValues(makePage()), ...initial } })
  onForm?.(() => form.getValues())
  const controller = { form, autosaveEnabled, pageId: 'page-1', saveAll } as unknown as PageEditorController

  return <SeoTab controller={controller} onOpenTab={onOpenTab} />
}

function renderTab(props: HarnessProps = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={client}>
      <ToastProvider>
        <Harness {...props} />
      </ToastProvider>
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  apiRequest.mockImplementation(async (path: string) => handleApi(path))
  useAdvancedMode.getState().setEnabled(false)
})

afterEach(() => {
  cleanup()
  apiRequest.mockReset()
})

describe('SeoTab', () => {
  it('updates the snippet preview and length counter while the title is typed', () => {
    renderTab()

    expect(screen.getByTestId('snippet-title').textContent).toContain('Заборы')

    fireEvent.change(screen.getByLabelText('SEO-заголовок (title)'), { target: { value: 'Забор из профнастила под ключ' } })

    expect(screen.getByTestId('snippet-title').textContent).toContain('Забор из профнастила под ключ')
    expect(screen.getByText(/29 \/ 60/)).toBeTruthy()
  })

  it('marks an over-long description as exceeding the recommendation', () => {
    renderTab()

    fireEvent.change(screen.getByLabelText('Описание (meta description)'), { target: { value: 'б'.repeat(200) } })

    expect(screen.getByText(/200 \/ 155/)).toBeTruthy()
  })

  it('explains autosave for drafts and manual saving for published pages', () => {
    const { unmount } = renderTab({ autosaveEnabled: true })
    expect(screen.getByText(/Изменения SEO сохраняются автоматически/)).toBeTruthy()
    unmount()

    renderTab({ autosaveEnabled: false })
    expect(screen.getByText(/сохраняйте изменения вручную/)).toBeTruthy()
  })

  it('shows indexation state and opens the settings tab from the hint', () => {
    const onOpenTab = vi.fn()
    renderTab({ initial: { isIndexable: false }, onOpenTab })

    expect(screen.getByText(/запрещена \(noindex\)/)).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: /Изменить во вкладке «Настройки»/ }))

    expect(onOpenTab).toHaveBeenCalledWith('settings')
  })

  it('stores an absolute og:image url chosen from the media library', async () => {
    let read: () => PageEditorFormValues = () => pageToFormValues(makePage())
    renderTab({ onForm: (values) => { read = values } })

    const picker = screen.getByTestId('media-picker')
    fireEvent.click(within(picker).getByRole('button', { name: 'Выбрать из медиатеки' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.doubleClick(await within(dialog).findByRole('button', { name: /og-cover\.jpg/ }))

    await waitFor(() => expect(read().ogImage).toBe(`${window.location.origin}/uploads/media/og-cover.jpg`))
    expect(within(screen.getByTestId('media-picker')).getByRole('button', { name: 'Очистить' })).toBeTruthy()
  })

  it('hides the JSON-LD editor until the advanced mode is enabled', () => {
    renderTab()
    expect(screen.queryByLabelText('JSON-LD')).toBeNull()

    cleanup()
    act(() => useAdvancedMode.getState().setEnabled(true))
    renderTab()
    expect(screen.getByLabelText('JSON-LD')).toBeTruthy()
  })

  it('saves the page before running the audit and lists the blocking issues', async () => {
    const saveAll = vi.fn(async () => true)
    apiRequest.mockImplementation(async (path: string) => handleApi(path, {
      pageId: 'page-1',
      path: '/zabory/',
      passed: false,
      issues: [
        { severity: 'P0', code: 'meta_description_missing', message: 'Не заполнено описание страницы.', field: 'metaDescription' },
        { severity: 'P2', code: 'og_image_missing', message: 'Нет изображения для соцсетей.', field: 'ogImage' },
      ],
    }))
    renderTab({ saveAll })

    fireEvent.click(screen.getByRole('button', { name: 'Проверить SEO' }))

    expect(await screen.findByText(/Публикация заблокирована: P0 — 1, P1 — 0, P2 — 1/)).toBeTruthy()
    expect(saveAll).toHaveBeenCalledTimes(1)
    const issues = within(screen.getByTestId('seo-audit-issues')).getAllByRole('listitem')
    expect(issues.map((item) => item.textContent)).toEqual([
      'P0Не заполнено описание страницы.',
      'P2Нет изображения для соцсетей.',
    ])
    expect(apiRequest).toHaveBeenCalledWith('/admin/api/seo/audit/pages/page-1')
  })

  it('reports a clean audit', async () => {
    renderTab()

    fireEvent.click(screen.getByRole('button', { name: 'Проверить SEO' }))

    expect(await screen.findByText('Замечаний нет')).toBeTruthy()
    expect(screen.queryByTestId('seo-audit-issues')).toBeNull()
  })

  it('skips the audit request when saving fails', async () => {
    const saveAll = vi.fn(async () => false)
    renderTab({ saveAll })

    fireEvent.click(screen.getByRole('button', { name: 'Проверить SEO' }))

    await waitFor(() => expect(saveAll).toHaveBeenCalled())
    expect(apiRequest.mock.calls.some((call) => String(call[0]).startsWith('/admin/api/seo/audit/'))).toBe(false)
    expect(screen.getByRole('button', { name: 'Проверить SEO' })).toBeTruthy()
  })

  it('shows an error message when the audit request fails', async () => {
    apiRequest.mockImplementation(async (path: string) => {
      if (path.startsWith('/admin/api/seo/audit/')) {
        throw new Error('boom')
      }

      return handleApi(path)
    })
    renderTab()

    fireEvent.click(screen.getByRole('button', { name: 'Проверить SEO' }))

    expect(await screen.findByText('Не удалось выполнить аудит.')).toBeTruthy()
  })
})
