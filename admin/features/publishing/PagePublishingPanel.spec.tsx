import { cleanup, fireEvent, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../seo/test-utils'
import { PagePublishingPanel } from './PagePublishingPanel'
import type { PageWorkflow } from './types'

const apiRequest = vi.fn()

vi.mock('../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

function makeWorkflow(overrides: Partial<PageWorkflow> = {}): PageWorkflow {
  return {
    pageId: 'page-1',
    status: 'draft',
    scheduledPublishAt: null,
    scheduledUnpublishAt: null,
    hasUnpublishedChanges: false,
    publishedRevision: null,
    scheduledRevision: null,
    transitions: [
      { status: 'review', allowed: true },
      { status: 'approved', allowed: false },
      { status: 'published', allowed: true },
      { status: 'deleted', allowed: true },
    ],
    canCancelSchedule: false,
    history: [],
    ...overrides,
  }
}

function mockWorkflow(workflow: PageWorkflow): void {
  apiRequest.mockImplementation((url: string) => {
    if (url === '/admin/api/content/pages/page-1/workflow') return Promise.resolve(workflow)

    return Promise.resolve({})
  })
}

beforeEach(() => { apiRequest.mockReset() })
afterEach(cleanup)

describe('PagePublishingPanel', () => {
  it('shows the hidden workflow statuses and disables transitions without permission', async () => {
    mockWorkflow(makeWorkflow())
    renderWithProviders(<PagePublishingPanel pageId="page-1" onChanged={vi.fn()} />)

    expect(await screen.findByText('Черновик')).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Отправить на проверку' }).hasAttribute('disabled')).toBe(false)
    expect(screen.getByRole('button', { name: 'Одобрить' }).hasAttribute('disabled')).toBe(true)
    expect(screen.queryByRole('button', { name: 'Опубликовать' })).toBeNull()
  })

  it('sends the status with a comment and notifies the parent', async () => {
    mockWorkflow(makeWorkflow())
    const onChanged = vi.fn()
    renderWithProviders(<PagePublishingPanel pageId="page-1" onChanged={onChanged} />)

    fireEvent.change(await screen.findByLabelText('Комментарий к изменению статуса'), { target: { value: 'Готово' } })
    fireEvent.click(screen.getByRole('button', { name: 'Отправить на проверку' }))

    await waitFor(() => expect(onChanged).toHaveBeenCalled())
    expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/page-1/status', { method: 'PATCH', body: { status: 'review', comment: 'Готово' } })
  })

  it('flags unpublished changes and lists the schedule and journal', async () => {
    mockWorkflow(makeWorkflow({
      status: 'scheduled',
      hasUnpublishedChanges: true,
      scheduledPublishAt: '2026-11-05T10:00:00+00:00',
      canCancelSchedule: true,
      transitions: [{ status: 'published', allowed: true }, { status: 'approved', allowed: true }, { status: 'scheduled', allowed: true }],
      history: [{ id: 'e1', event: 'scheduled', occurredAt: '2026-10-04T10:00:00+00:00', actor: 'admin@example.test', fromStatus: 'approved', toStatus: 'scheduled', comment: 'Акция', details: {} }],
    }))
    renderWithProviders(<PagePublishingPanel pageId="page-1" onChanged={vi.fn()} />)

    expect(await screen.findByText('Есть неопубликованные изменения')).toBeTruthy()
    expect(screen.getByTestId('schedule-summary').textContent).toContain('Публикация по расписанию')
    expect(screen.getByRole('button', { name: 'Изменить расписание…' })).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Отменить публикацию по расписанию' })).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'Одобрить' })).toBeNull()
    expect(screen.getByTestId('workflow-event').textContent).toContain('Запланировано')
    expect(screen.getByTestId('workflow-event').textContent).toContain('Акция')
  })

  it('cancels the schedule through the dedicated endpoint', async () => {
    mockWorkflow(makeWorkflow({ status: 'scheduled', scheduledPublishAt: '2026-11-05T10:00:00+00:00', canCancelSchedule: true, transitions: [{ status: 'approved', allowed: true }] }))
    const onChanged = vi.fn()
    renderWithProviders(<PagePublishingPanel pageId="page-1" onChanged={onChanged} />)

    fireEvent.click(await screen.findByRole('button', { name: 'Отменить публикацию по расписанию' }))

    await waitFor(() => expect(onChanged).toHaveBeenCalled())
    expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/page-1/schedule', { method: 'DELETE', body: { comment: null } })
  })

  it('submits the schedule dialog with an ISO date and shows server errors', async () => {
    mockWorkflow(makeWorkflow({
      status: 'approved',
      transitions: [{ status: 'scheduled', allowed: true }, { status: 'draft', allowed: true }],
    }))
    renderWithProviders(<PagePublishingPanel pageId="page-1" onChanged={vi.fn()} />)

    fireEvent.click(await screen.findByRole('button', { name: 'Запланировать…' }))
    const input = await screen.findByLabelText('Опубликовать')
    fireEvent.change(input, { target: { value: '2030-01-02T09:15' } })

    apiRequest.mockImplementation((url: string) => {
      if (url.endsWith('/schedule')) return Promise.reject(new Error('Дата публикации должна быть в будущем'))
      return Promise.resolve(makeWorkflow({ status: 'approved' }))
    })
    fireEvent.click(screen.getByRole('button', { name: 'Запланировать' }))

    expect(await screen.findByText('Дата публикации должна быть в будущем')).toBeTruthy()
    expect(apiRequest).toHaveBeenCalledWith('/admin/api/content/pages/page-1/schedule', {
      method: 'POST',
      body: { publishAt: new Date(2030, 0, 2, 9, 15).toISOString(), unpublishAt: null, comment: null },
    })
  })
})
