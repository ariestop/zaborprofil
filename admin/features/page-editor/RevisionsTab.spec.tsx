import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { PageRevisionItem } from '../../types/api'
import { renderWithProviders } from '../seo/test-utils'
import { RevisionsTab } from './RevisionsTab'
import type { PageEditorController } from './usePageEditorController'

const apiRequest = vi.fn()

vi.mock('../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

const revision: PageRevisionItem = {
  id: 'rev-1',
  pageId: 'page-1',
  version: 2,
  title: 'Забор',
  h1: 'Забор',
  path: '/zabor/',
  type: 'landing',
  template: 'landing',
  createdAt: '2026-10-01T10:00:00+00:00',
  comment: null,
  changeSummary: {},
}

function controller(overrides: Partial<PageEditorController> = {}): PageEditorController {
  return { pageId: 'page-1', hasUnsavedChanges: false, reloadFromServer: vi.fn().mockResolvedValue(undefined), ...overrides } as unknown as PageEditorController
}

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string) => (url.endsWith('/revisions') ? Promise.resolve({ revisions: [revision] }) : Promise.resolve({})))
})
afterEach(cleanup)

describe('RevisionsTab', () => {
  it('shows each revision once, with compare and rollback in the same row', async () => {
    renderWithProviders(<RevisionsTab controller={controller()} />)

    expect(await screen.findByRole('button', { name: 'Сравнить с текущей' })).toBeTruthy()
    expect(screen.getAllByRole('button', { name: 'Откатить' })).toHaveLength(1)
    expect(screen.getAllByText(/v2 · Забор/)).toHaveLength(1)
  })

  it('rolls back through the revisions endpoint and reloads the editor', async () => {
    const reloadFromServer = vi.fn().mockResolvedValue(undefined)
    renderWithProviders(<RevisionsTab controller={controller({ reloadFromServer })} />)

    fireEvent.click(await screen.findByRole('button', { name: 'Откатить' }))
    fireEvent.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Откатить' }))

    await waitFor(() => expect(reloadFromServer).toHaveBeenCalled())
    expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/page-1/revisions/rev-1/rollback', { method: 'POST' })
  })

  it('explains that versions appear after the first publication', async () => {
    apiRequest.mockImplementation(() => Promise.resolve({ revisions: [] }))
    renderWithProviders(<RevisionsTab controller={controller()} />)

    expect(await screen.findByText('Версий пока нет')).toBeTruthy()
  })
})
