import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, cleanup, renderHook } from '@testing-library/react'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ToastProvider } from '../../app/providers/toast-provider'
import { useBuilderStore } from '../../modules/page-builder/state/builderStore'
import { createBlock } from '../../modules/page-builder/utils/pageBlocks'
import { ApiError } from '../../shared/api/client'
import type { ContentPageDetail } from '../../types/api'
import { makePage } from './fixtures'
import { AUTOSAVE_DELAY_MS, usePageEditorController } from './usePageEditorController'

const apiRequest = vi.fn()

vi.mock('../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })

  return (
    <QueryClientProvider client={client}>
      <ToastProvider>
        <MemoryRouter>{children}</MemoryRouter>
      </ToastProvider>
    </QueryClientProvider>
  )
}

function renderController(page: ContentPageDetail = makePage()) {
  return renderHook(() => usePageEditorController({ page }), { wrapper })
}

function calls(method: string, suffix: string): unknown[][] {
  return apiRequest.mock.calls.filter(([url, options]) => (
    typeof url === 'string' && url.endsWith(suffix) && (options as { method?: string } | undefined)?.method === method
  ))
}

async function advance(ms: number): Promise<void> {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(ms)
  })
}

beforeEach(() => {
  vi.useFakeTimers()
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string) => {
    if (url.endsWith('/builder')) {
      return Promise.resolve({ pageId: 'page-1', updatedAt: null, blocks: [] })
    }

    return Promise.resolve(makePage())
  })
  useBuilderStore.getState().setBlocks([])
})

afterEach(() => {
  cleanup()
  vi.useRealTimers()
})

describe('usePageEditorController autosave', () => {
  it('autosaves SEO changes after the debounce delay without touching page settings', async () => {
    const { result } = renderController()

    act(() => {
      result.current.form.setValue('metaTitle', 'Новый SEO-заголовок', { shouldDirty: true })
      result.current.form.setValue('title', 'Изменённое название', { shouldDirty: true })
    })

    expect(result.current.saveState).toBe('dirty')
    expect(result.current.seoDirty).toBe(true)
    expect(result.current.settingsDirty).toBe(true)

    await advance(AUTOSAVE_DELAY_MS - 1)
    expect(apiRequest).not.toHaveBeenCalled()

    await advance(1)
    expect(calls('PUT', '/seo')).toHaveLength(1)
    expect(calls('PUT', '/pages/page-1')).toHaveLength(0)
    expect(result.current.seoDirty).toBe(false)
    expect(result.current.settingsDirty).toBe(true)
    expect(result.current.saveState).toBe('dirty')
  })

  it('debounces repeated edits into a single request', async () => {
    const { result } = renderController()

    act(() => result.current.form.setValue('metaTitle', 'Первый', { shouldDirty: true }))
    await advance(AUTOSAVE_DELAY_MS - 5_000)
    act(() => result.current.form.setValue('metaTitle', 'Второй', { shouldDirty: true }))
    await advance(AUTOSAVE_DELAY_MS - 1)
    expect(calls('PUT', '/seo')).toHaveLength(0)

    await advance(1)
    const seoCalls = calls('PUT', '/seo')
    expect(seoCalls).toHaveLength(1)
    expect((seoCalls[0]?.[1] as { body: { metaTitle: string } }).body.metaTitle).toBe('Второй')
  })

  it('autosaves changed blocks through the builder endpoint', async () => {
    const { result } = renderController()

    act(() => useBuilderStore.getState().setBlocks([createBlock('text', 0)], true))
    expect(result.current.blocksDirty).toBe(true)

    await advance(AUTOSAVE_DELAY_MS)
    expect(calls('PUT', '/builder')).toHaveLength(1)
    expect(useBuilderStore.getState().dirty).toBe(false)
    expect(result.current.saveState).toBe('saved')
  })

  it('does not autosave published pages', async () => {
    const { result } = renderController(makePage({ status: 'published' }))

    expect(result.current.autosaveEnabled).toBe(false)
    act(() => result.current.form.setValue('metaTitle', 'Правка опубликованной', { shouldDirty: true }))

    await advance(AUTOSAVE_DELAY_MS * 3)
    expect(apiRequest).not.toHaveBeenCalled()
    expect(result.current.hasUnsavedChanges).toBe(true)
  })

  it('does not autosave invalid SEO data and reports the error state', async () => {
    const { result } = renderController()

    act(() => result.current.form.setValue('metaTitle', 'а'.repeat(300), { shouldDirty: true }))
    await advance(AUTOSAVE_DELAY_MS)

    expect(calls('PUT', '/seo')).toHaveLength(0)
    expect(result.current.saveState).toBe('error')
  })

  it('shows the error state when the server rejects autosave and recovers after the next edit', async () => {
    apiRequest.mockRejectedValueOnce(new ApiError('Ошибка', 500, undefined))
    const { result } = renderController()

    act(() => result.current.form.setValue('metaTitle', 'Заголовок', { shouldDirty: true }))
    await advance(AUTOSAVE_DELAY_MS)
    expect(result.current.saveState).toBe('error')

    act(() => result.current.form.setValue('metaTitle', 'Заголовок 2', { shouldDirty: true }))
    expect(result.current.saveState).toBe('dirty')
  })
})

describe('usePageEditorController manual save', () => {
  it('saves settings only on manual save and sends parentId as is', async () => {
    const { result } = renderController(makePage({ parentId: 'parent-1' }))

    act(() => result.current.form.setValue('title', 'Новое название', { shouldDirty: true }))
    await advance(AUTOSAVE_DELAY_MS * 2)
    expect(calls('PUT', '/pages/page-1')).toHaveLength(0)

    await act(async () => {
      await result.current.saveAll()
    })

    const pageCalls = calls('PUT', '/pages/page-1')
    expect(pageCalls).toHaveLength(1)
    const body = (pageCalls[0]?.[1] as { body: { title: string, parentId: string | null } }).body
    expect(body.title).toBe('Новое название')
    expect(body.parentId).toBe('parent-1')
    expect(result.current.settingsDirty).toBe(false)
    expect(result.current.saveState).toBe('saved')
  })

  it('blocks manual save and switches to the failing tab when settings are invalid', async () => {
    const onInvalidTab = vi.fn()
    const { result } = renderHook(() => usePageEditorController({ page: makePage(), onInvalidTab }), { wrapper })

    act(() => result.current.form.setValue('slug', 'a/b', { shouldDirty: true }))
    await act(async () => {
      expect(await result.current.saveAll()).toBe(false)
    })

    expect(onInvalidTab).toHaveBeenCalledWith('settings')
    expect(apiRequest).not.toHaveBeenCalled()
  })

  it('publish saves pending changes first and then calls the publish endpoint', async () => {
    const { result } = renderController()

    act(() => result.current.form.setValue('metaTitle', 'Заголовок', { shouldDirty: true }))
    await act(async () => {
      expect(await result.current.publish()).toBe(true)
    })

    const urls = apiRequest.mock.calls.map(([url]) => url as string)
    expect(urls.findIndex((url) => url.endsWith('/seo'))).toBeLessThan(urls.findIndex((url) => url.endsWith('/publish')))
  })
})
