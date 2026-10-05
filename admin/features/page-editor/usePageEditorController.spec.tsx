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
import { draftKey, readDraft, writeDraft } from './draft-storage'
import { makePage } from './fixtures'
import { pageToFormValues } from './form'
import { AUTOSAVE_DELAY_MS, DRAFT_WRITE_DELAY_MS, usePageEditorController } from './usePageEditorController'

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

function renderController(page: ContentPageDetail = makePage(), builderVersion: string | null = 'v1') {
  return renderHook(() => usePageEditorController({ page, builderVersion }), { wrapper })
}

/** Своё хранилище в памяти: в Node 25 глобальный localStorage без --localstorage-file не поддерживает clear(). */
function installMemoryStorage() {
  const data = new Map<string, string>()
  const storage = {
    getItem: (key: string) => data.get(key) ?? null,
    setItem: (key: string, value: string) => void data.set(key, String(value)),
    removeItem: (key: string) => void data.delete(key),
    clear: () => data.clear(),
    key: (index: number) => Array.from(data.keys())[index] ?? null,
    get length() {
      return data.size
    },
  }
  Object.defineProperty(window, 'localStorage', { value: storage, configurable: true })
}

function conflictError(version = 'server-v2'): ApiError {
  return new ApiError('Page blocks were changed by another editor.', 409, { error: 'conflict', code: 'EDIT_CONFLICT', version, updatedAt: '2026-10-04T10:00:00+00:00' })
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
  installMemoryStorage()
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string) => {
    if (url.endsWith('/builder')) {
      return Promise.resolve({ pageId: 'page-1', updatedAt: null, version: 'v-saved', blocks: [] })
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

describe('usePageEditorController edit conflicts', () => {
  it('sends the known builder version and adopts the one returned by the server', async () => {
    const { result } = renderController()

    act(() => useBuilderStore.getState().setBlocks([createBlock('text', 0)], true))
    await act(async () => {
      await result.current.saveAll()
    })
    expect((calls('PUT', '/builder')[0]?.[1] as { body: { baseVersion: string } }).body.baseVersion).toBe('v1')

    act(() => useBuilderStore.getState().updateBlock(useBuilderStore.getState().blocks[0]?.id ?? '', (block) => ({ ...block })))
    await act(async () => {
      await result.current.saveAll()
    })
    expect((calls('PUT', '/builder')[1]?.[1] as { body: { baseVersion: string } }).body.baseVersion).toBe('v-saved')
  })

  it('omits baseVersion when the editor was opened without a known version', async () => {
    const { result } = renderController(makePage(), null)

    act(() => useBuilderStore.getState().setBlocks([createBlock('text', 0)], true))
    await act(async () => {
      await result.current.saveAll()
    })
    expect(Object.keys((calls('PUT', '/builder')[0]?.[1] as { body: object }).body)).toEqual(['blocks'])
  })

  it('reports a conflict instead of a generic error and stops autosave until it is resolved', async () => {
    apiRequest.mockImplementation((url: string, options?: { method?: string }) => {
      if (url.endsWith('/builder') && options?.method === 'PUT') {
        return Promise.reject(conflictError())
      }

      return Promise.resolve(makePage())
    })
    const { result } = renderController()

    act(() => useBuilderStore.getState().setBlocks([createBlock('text', 0)], true))
    await advance(AUTOSAVE_DELAY_MS)

    expect(result.current.conflict).toEqual({ serverVersion: 'server-v2', serverUpdatedAt: '2026-10-04T10:00:00+00:00' })
    expect(result.current.saveState).toBe('error')
    expect(result.current.errorMessage).toContain('другой пользователь')
    expect(result.current.autosaveEnabled).toBe(false)

    await advance(AUTOSAVE_DELAY_MS * 3)
    expect(calls('PUT', '/builder')).toHaveLength(1)
  })

  it('overwrites the server blocks by resending them with the server version', async () => {
    let attempts = 0
    apiRequest.mockImplementation((url: string, options?: { method?: string }) => {
      if (url.endsWith('/builder') && options?.method === 'PUT') {
        attempts += 1
        return attempts === 1
          ? Promise.reject(conflictError())
          : Promise.resolve({ pageId: 'page-1', updatedAt: null, version: 'v-after-overwrite', blocks: [] })
      }

      return Promise.resolve(makePage())
    })
    const { result } = renderController()

    act(() => useBuilderStore.getState().setBlocks([createBlock('text', 0)], true))
    await act(async () => {
      await result.current.saveAll()
    })
    expect(result.current.conflict).not.toBeNull()

    await act(async () => {
      expect(await result.current.overwriteConflict()).toBe(true)
    })

    const puts = calls('PUT', '/builder')
    expect(puts).toHaveLength(2)
    expect((puts[1]?.[1] as { body: { baseVersion: string } }).body.baseVersion).toBe('server-v2')
    expect(result.current.conflict).toBeNull()
    expect(result.current.saveState).toBe('saved')
    expect(useBuilderStore.getState().dirty).toBe(false)
  })

  it('reloads the server state and drops local changes when the user picks the server version', async () => {
    apiRequest.mockImplementation((url: string, options?: { method?: string }) => {
      if (url.endsWith('/builder')) {
        return options?.method === 'PUT'
          ? Promise.reject(conflictError())
          : Promise.resolve({ pageId: 'page-1', updatedAt: null, version: 'server-v2', blocks: [] })
      }

      return Promise.resolve(makePage())
    })
    const { result } = renderController()

    act(() => useBuilderStore.getState().setBlocks([createBlock('text', 0)], true))
    await act(async () => {
      await result.current.saveAll()
    })
    expect(result.current.conflict).not.toBeNull()

    await act(async () => {
      await result.current.reloadFromServer()
    })

    expect(result.current.conflict).toBeNull()
    expect(useBuilderStore.getState().blocks).toHaveLength(0)
    expect(result.current.hasUnsavedChanges).toBe(false)

    act(() => useBuilderStore.getState().setBlocks([createBlock('text', 0)], true))
    await act(async () => {
      await result.current.saveAll()
    })
    expect((calls('PUT', '/builder')[1]?.[1] as { body: { baseVersion: string } }).body.baseVersion).toBe('server-v2')
  })

  it('can dismiss the conflict dialog without losing the error state', async () => {
    apiRequest.mockImplementation((url: string, options?: { method?: string }) => (
      url.endsWith('/builder') && options?.method === 'PUT' ? Promise.reject(conflictError()) : Promise.resolve(makePage())
    ))
    const { result } = renderController()

    act(() => useBuilderStore.getState().setBlocks([createBlock('text', 0)], true))
    await act(async () => {
      await result.current.saveAll()
    })
    act(() => result.current.dismissConflict())

    expect(result.current.conflict).toBeNull()
    expect(result.current.saveState).toBe('error')
  })
})

describe('usePageEditorController local draft', () => {
  it('stores unsaved changes in localStorage after a short pause and removes them once saved', async () => {
    const { result } = renderController()

    act(() => result.current.form.setValue('metaTitle', 'Черновой SEO', { shouldDirty: true }))
    act(() => useBuilderStore.getState().setBlocks([createBlock('text', 0)], true))
    expect(readDraft('page-1')).toBeNull()

    await advance(DRAFT_WRITE_DELAY_MS)
    const draft = readDraft('page-1')
    expect(draft?.baseVersion).toBe('v1')
    expect(draft?.values.metaTitle).toBe('Черновой SEO')
    expect(draft?.blocks).toHaveLength(1)

    await act(async () => {
      await result.current.saveAll()
    })
    expect(readDraft('page-1')).toBeNull()
  })

  it('offers to restore a draft that differs from the server state', () => {
    const saved = pageToFormValues(makePage())
    writeDraft('page-1', { baseVersion: 'v0', blocks: [createBlock('text', 0)], values: { ...saved, title: 'Из черновика' } })

    const { result } = renderController()

    expect(result.current.pendingDraft?.serverChanged).toBe(true)
    expect(result.current.hasUnsavedChanges).toBe(false)

    act(() => result.current.restoreDraft())

    expect(result.current.pendingDraft).toBeNull()
    expect(result.current.form.getValues('title')).toBe('Из черновика')
    expect(result.current.settingsDirty).toBe(true)
    expect(useBuilderStore.getState().blocks).toHaveLength(1)
    expect(result.current.blocksDirty).toBe(true)
  })

  it('restored drafts keep the original base version so that a stale draft surfaces as a conflict', async () => {
    apiRequest.mockImplementation((url: string, options?: { method?: string }) => (
      url.endsWith('/builder') && options?.method === 'PUT' ? Promise.reject(conflictError()) : Promise.resolve(makePage())
    ))
    writeDraft('page-1', { baseVersion: 'v0', blocks: [createBlock('text', 0)], values: pageToFormValues(makePage()) })
    const { result } = renderController()

    act(() => result.current.restoreDraft())
    await act(async () => {
      await result.current.saveAll()
    })

    expect((calls('PUT', '/builder')[0]?.[1] as { body: { baseVersion: string } }).body.baseVersion).toBe('v0')
    expect(result.current.conflict).not.toBeNull()
  })

  it('discards a pending draft and does not overwrite it before the user decides', async () => {
    const saved = pageToFormValues(makePage())
    writeDraft('page-1', { baseVersion: 'v1', blocks: [], values: { ...saved, h1: 'Другой H1' } })
    const { result } = renderController()

    act(() => result.current.form.setValue('metaTitle', 'Новое', { shouldDirty: true }))
    await advance(DRAFT_WRITE_DELAY_MS * 2)
    expect(readDraft('page-1')?.values.h1).toBe('Другой H1')

    act(() => result.current.discardDraft())
    expect(result.current.pendingDraft).toBeNull()
    expect(window.localStorage.getItem(draftKey('page-1'))).toBeNull()
  })

  it('ignores a draft identical to the server state', () => {
    writeDraft('page-1', { baseVersion: 'v1', blocks: [], values: pageToFormValues(makePage()) })

    const { result } = renderController()

    expect(result.current.pendingDraft).toBeNull()
    expect(readDraft('page-1')).toBeNull()
  })
})
