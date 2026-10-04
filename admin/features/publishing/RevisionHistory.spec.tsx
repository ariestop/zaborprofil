import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { PageRevisionItem } from '../../types/api'
import { renderWithProviders } from '../seo/test-utils'
import { RevisionHistory } from './RevisionHistory'

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

beforeEach(() => { apiRequest.mockReset() })
afterEach(cleanup)

describe('RevisionHistory', () => {
  it('loads the diff against the current version', async () => {
    apiRequest.mockResolvedValue({
      pageId: 'page-1',
      from: { id: 'rev-1', version: 2, createdAt: '2026-10-01T10:00:00+00:00', createdBy: null, comment: null, action: null },
      to: { id: 'current', version: null, createdAt: '2026-10-02T10:00:00+00:00', createdBy: null, comment: null, action: null },
      hasChanges: false,
      summary: { fields: 0, seo: 0, settings: 0, blocksAdded: 0, blocksRemoved: 0, blocksChanged: 0 },
      fields: [],
      seo: [],
      settings: [],
      blocks: [],
    })
    renderWithProviders(<RevisionHistory pageId="page-1" revisions={[revision]} onRolledBack={vi.fn()} />)

    fireEvent.click(screen.getByRole('button', { name: 'Сравнить с текущей' }))

    expect(await screen.findByText('Различий нет.')).toBeTruthy()
    expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/page-1/revisions/diff?from=rev-1&to=current')
  })

  it('rolls back only after confirmation', async () => {
    apiRequest.mockResolvedValue({})
    const onRolledBack = vi.fn()
    renderWithProviders(<RevisionHistory pageId="page-1" revisions={[revision]} onRolledBack={onRolledBack} />)

    fireEvent.click(screen.getByRole('button', { name: 'Откатить' }))
    expect(apiRequest).not.toHaveBeenCalled()

    const dialog = await screen.findByRole('dialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Откатить' }))

    await waitFor(() => expect(onRolledBack).toHaveBeenCalled())
    expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/page-1/revisions/rev-1/rollback', { method: 'POST' })
  })
})
