import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../api/client'
import { installGlobalClientErrorReporting, reportClientError, resetClientErrorReporterForTests } from './client-error-reporter'

const apiRequest = vi.fn()

vi.mock('../api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

describe('reportClientError', () => {
  beforeEach(() => {
    apiRequest.mockReset()
    apiRequest.mockResolvedValue({ status: 'accepted' })
    resetClientErrorReporterForTests()
  })

  it('posts the error with source, stack and component stack', async () => {
    const error = new TypeError('x is undefined')

    await expect(reportClientError(error, 'error-boundary', { componentStack: '\n  at Page' })).resolves.toBe(true)

    expect(apiRequest).toHaveBeenCalledWith('/admin/api/client-errors', {
      method: 'POST',
      body: expect.objectContaining({
        message: 'TypeError: x is undefined',
        source: 'error-boundary',
        url: window.location.href,
        componentStack: '\n  at Page',
        stack: expect.any(String),
      }),
    })
  })

  it('does not repeat identical errors', async () => {
    await reportClientError(new Error('boom'), 'window-error')

    await expect(reportClientError(new Error('boom'), 'window-error')).resolves.toBe(false)
    expect(apiRequest).toHaveBeenCalledTimes(1)
  })

  it('caps the number of reports per page session', async () => {
    for (let index = 0; index < 8; index += 1) {
      await reportClientError(new Error(`boom ${index}`), 'window-error')
    }

    expect(apiRequest).toHaveBeenCalledTimes(5)
  })

  it('ignores API errors, aborted requests and browser noise', async () => {
    await reportClientError(new ApiError('Forbidden', 403, null), 'unhandled-rejection')
    await reportClientError(new DOMException('aborted', 'AbortError'), 'unhandled-rejection')
    await reportClientError('ResizeObserver loop completed with undelivered notifications.', 'window-error')

    expect(apiRequest).not.toHaveBeenCalled()
  })

  it('never throws when the report request fails', async () => {
    apiRequest.mockRejectedValue(new Error('offline'))

    await expect(reportClientError(new Error('boom'), 'window-error')).resolves.toBe(false)
  })

  it('reports global errors and unhandled rejections until uninstalled', async () => {
    const target = new EventTarget()
    const uninstall = installGlobalClientErrorReporting(target)

    target.dispatchEvent(new ErrorEvent('error', { message: 'bad', error: new Error('bad') }))
    const rejection = new Event('unhandledrejection') as PromiseRejectionEvent
    Object.defineProperty(rejection, 'reason', { value: new Error('rejected') })
    target.dispatchEvent(rejection)
    await Promise.resolve()

    expect(apiRequest).toHaveBeenCalledTimes(2)

    uninstall()
    target.dispatchEvent(new ErrorEvent('error', { message: 'later', error: new Error('later') }))
    await Promise.resolve()

    expect(apiRequest).toHaveBeenCalledTimes(2)
  })
})
