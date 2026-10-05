import { cleanup, fireEvent, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { PageRevisionItem } from '../../types/api'
import type { PageWorkflow } from '../publishing/types'
import { renderWithProviders } from '../seo/test-utils'
import { PagePublishingSlot, PageRevisionsSlot } from './slots'

const apiRequest = vi.fn()

vi.mock('../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

const workflow: PageWorkflow = {
  pageId: 'page-1',
  status: 'approved',
  scheduledPublishAt: null,
  scheduledUnpublishAt: null,
  hasUnpublishedChanges: false,
  publishedRevision: null,
  scheduledRevision: null,
  transitions: [
    { status: 'draft', allowed: true },
    { status: 'scheduled', allowed: true },
    { status: 'published', allowed: true },
  ],
  canCancelSchedule: false,
  history: [],
}

const revision: PageRevisionItem = {
  id: 'rev-1',
  pageId: 'page-1',
  version: 3,
  title: 'Забор',
  h1: 'Забор',
  path: '/zabor/',
  type: 'landing',
  template: 'landing',
  createdAt: '2026-10-01T10:00:00+00:00',
  comment: null,
  changeSummary: {},
}

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string) => {
    if (url.endsWith('/workflow')) return Promise.resolve(workflow)
    if (url.endsWith('/revisions')) return Promise.resolve({ revisions: [revision] })
    if (url.includes('/revisions/diff')) {
      return Promise.resolve({
        pageId: 'page-1',
        from: { id: 'rev-1', version: 3, createdAt: '2026-10-01T10:00:00+00:00', createdBy: null, comment: null, action: null },
        to: { id: 'current', version: null, createdAt: '2026-10-02T10:00:00+00:00', createdBy: null, comment: null, action: null },
        hasChanges: false,
        summary: { fields: 0, seo: 0, settings: 0, blocksAdded: 0, blocksRemoved: 0, blocksChanged: 0 },
        fields: [],
        seo: [],
        settings: [],
        blocks: [],
      })
    }

    return Promise.resolve({})
  })
})
afterEach(cleanup)

function renderPublishingSlot(overrides: { hasUnsavedChanges?: boolean, saveAll?: () => Promise<boolean> } = {}) {
  const saveAll = overrides.saveAll ?? vi.fn().mockResolvedValue(true)
  renderWithProviders(
    <PagePublishingSlot
      pageId="page-1"
      status="approved"
      saveState="saved"
      hasUnsavedChanges={overrides.hasUnsavedChanges ?? false}
      saveAll={saveAll}
    />,
  )

  return saveAll
}

describe('PagePublishingSlot', () => {
  it('opens the workflow panel with the schedule action', async () => {
    renderPublishingSlot()

    fireEvent.click(screen.getByRole('button', { name: 'Публикация и расписание' }))

    expect(await screen.findByRole('button', { name: 'Запланировать…' })).toBeTruthy()
    expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/page-1/workflow')
  })

  it('saves pending editor changes before opening the panel', async () => {
    const saveAll = renderPublishingSlot({ hasUnsavedChanges: true })

    fireEvent.click(screen.getByRole('button', { name: 'Публикация и расписание' }))

    await screen.findByRole('button', { name: 'Запланировать…' })
    expect(saveAll).toHaveBeenCalledTimes(1)
  })

  it('does not open the panel when saving fails', async () => {
    const saveAll = vi.fn().mockResolvedValue(false)
    renderPublishingSlot({ hasUnsavedChanges: true, saveAll })

    fireEvent.click(screen.getByRole('button', { name: 'Публикация и расписание' }))

    await waitFor(() => expect(saveAll).toHaveBeenCalled())
    expect(screen.queryByRole('dialog')).toBeNull()
  })

  it('changes the status through the panel', async () => {
    renderPublishingSlot()

    fireEvent.click(screen.getByRole('button', { name: 'Публикация и расписание' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Вернуть в черновик' }))

    await waitFor(() => expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/page-1/status', { method: 'PATCH', body: { status: 'draft', comment: null } }))
  })
})

describe('PageRevisionsSlot', () => {
  it('lets the editor compare a revision with the current version without a second rollback button', async () => {
    renderWithProviders(<PageRevisionsSlot pageId="page-1" status="draft" />)

    fireEvent.click(await screen.findByRole('button', { name: 'Сравнить с текущей' }))

    expect(await screen.findByText('Различий нет.')).toBeTruthy()
    expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/page-1/revisions/diff?from=rev-1&to=current')
    expect(screen.queryByRole('button', { name: 'Откатить' })).toBeNull()
  })

  it('renders nothing while there are no revisions', async () => {
    apiRequest.mockResolvedValue({ revisions: [] })
    renderWithProviders(<PageRevisionsSlot pageId="page-1" status="draft" />)

    await waitFor(() => expect(apiRequest).toHaveBeenCalled())
    expect(screen.queryByLabelText('История ревизий')).toBeNull()
  })
})
