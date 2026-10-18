import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, apiRequest, apiUpload } from './client'
import { onSessionExpired } from './session'

describe('ApiError', () => {
  it('keeps the server error code next to the status-based code', () => {
    const error = new ApiError('Media asset is used on the site.', 409, { error: 'Media asset is used on the site.', code: 'MEDIA_IN_USE' })

    expect(error.code).toBe('UNKNOWN')
    expect(error.serverCode).toBe('MEDIA_IN_USE')
  })

  it('has no server code when the payload is not an error object', () => {
    expect(new ApiError('Bad gateway', 502, '<html>').serverCode).toBeNull()
    expect(new ApiError('Network error', 0, null).serverCode).toBeNull()
    expect(new ApiError('Odd', 422, { error: 'Odd', code: 42 }).serverCode).toBeNull()
  })
})

function jsonResponse(status: number, body: unknown, redirected = false): Response {
  const response = new Response(JSON.stringify(body), { status, headers: { 'content-type': 'application/json' } })
  Object.defineProperty(response, 'redirected', { value: redirected })

  return response
}

function loginPageResponse(): Response {
  const response = new Response('<!doctype html><title>Вход</title>', { status: 200, headers: { 'content-type': 'text/html; charset=UTF-8' } })
  Object.defineProperty(response, 'redirected', { value: true })

  return response
}

describe('apiRequest и истёкшая сессия', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('не принимает форму входа после редиректа за успешный ответ', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => loginPageResponse()))
    const expired = vi.fn()
    const unsubscribe = onSessionExpired(expired)

    const request = apiRequest('/admin/api/content/pages/P1/publish', { method: 'POST', body: {} })

    await expect(request).rejects.toMatchObject({ status: 401, code: 'UNAUTHORIZED', serverCode: 'UNAUTHORIZED' })
    expect(expired).toHaveBeenCalledTimes(1)
    unsubscribe()
  })

  it('сообщает об истёкшей сессии при ответе 401 и отдаёт текст сервера', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => jsonResponse(401, { error: 'Сессия истекла. Войдите в админку снова.', code: 'UNAUTHORIZED' })))
    const expired = vi.fn()
    const unsubscribe = onSessionExpired(expired)

    await expect(apiRequest('/admin/api/content/pages')).rejects.toMatchObject({
      status: 401,
      message: 'Сессия истекла. Войдите в админку снова.',
    })
    expect(expired).toHaveBeenCalledTimes(1)
    unsubscribe()
  })

  it('не сообщает об истёкшей сессии при обычных ответах и ошибках', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(jsonResponse(200, { pages: [] }))
      .mockResolvedValueOnce(jsonResponse(403, { error: 'Access denied.', code: 'ACCESS_DENIED' }))
    vi.stubGlobal('fetch', fetchMock)
    const expired = vi.fn()
    const unsubscribe = onSessionExpired(expired)

    await expect(apiRequest('/admin/api/content/pages')).resolves.toEqual({ pages: [] })
    await expect(apiRequest('/admin/api/users', { method: 'POST', body: {} })).rejects.toMatchObject({ status: 403 })
    expect(expired).not.toHaveBeenCalled()
    unsubscribe()
  })
})

describe('apiUpload и истёкшая сессия', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  function stubXhr(responseURL: string, status: number, responseText: string, contentType: string): void {
    class FakeXhr {
      upload = { onprogress: null as unknown }
      withCredentials = false
      status = status
      responseText = responseText
      responseURL = responseURL
      onload: (() => void) | null = null
      onerror: (() => void) | null = null
      onabort: (() => void) | null = null
      open(): void {}
      setRequestHeader(): void {}
      getResponseHeader(name: string): string | null {
        return name.toLowerCase() === 'content-type' ? contentType : null
      }
      abort(): void {}
      send(): void {
        queueMicrotask(() => this.onload?.())
      }
    }
    vi.stubGlobal('XMLHttpRequest', FakeXhr)
  }

  it('не принимает форму входа после редиректа за загруженный файл', async () => {
    stubXhr(`${window.location.origin}/admin/login`, 200, '<!doctype html>', 'text/html')
    const expired = vi.fn()
    const unsubscribe = onSessionExpired(expired)

    await expect(apiUpload('/admin/api/media/assets', new FormData())).rejects.toMatchObject({ status: 401, serverCode: 'UNAUTHORIZED' })
    expect(expired).toHaveBeenCalledTimes(1)
    unsubscribe()
  })

  it('возвращает ответ, если адрес не менялся', async () => {
    stubXhr(`${window.location.origin}/admin/api/media/assets`, 201, '{"id":"M1"}', 'application/json')
    const expired = vi.fn()
    const unsubscribe = onSessionExpired(expired)

    await expect(apiUpload('/admin/api/media/assets', new FormData())).resolves.toEqual({ id: 'M1' })
    expect(expired).not.toHaveBeenCalled()
    unsubscribe()
  })
})
