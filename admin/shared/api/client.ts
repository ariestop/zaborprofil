import { resolveApiErrorCode, type ApiErrorCode } from './errors'

export type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

export class ApiError extends Error {
  public readonly code: ApiErrorCode

  constructor(
    message: string,
    public readonly status: number,
    public readonly payload: unknown,
  ) {
    super(message)
    this.name = 'ApiError'
    this.code = resolveApiErrorCode(status)
  }
}

function csrfHeaderName(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="admin-csrf-header"]')?.content ?? 'X-CSRF-Token'
}

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="admin-csrf-token"]')?.content ?? ''
}

function requestId(): string {
  if ('randomUUID' in crypto) {
    return crypto.randomUUID()
  }

  return `${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function parseResponsePayload(response: Response): Promise<unknown> {
  const contentType = response.headers.get('content-type') ?? ''
  if (contentType.includes('application/json')) {
    return response.json()
  }

  return response.text()
}

export async function apiRequest<T>(
  path: string,
  options: {
    method?: ApiMethod
    body?: unknown
    signal?: AbortSignal
  } = {},
): Promise<T> {
  const method = options.method ?? 'GET'
  const headers = new Headers({
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-Request-Id': requestId(),
  })

  if (method !== 'GET') {
    headers.set('Content-Type', 'application/json')
    headers.set(csrfHeaderName(), csrfToken())
  }

  const response = await fetch(path, {
    method,
    headers,
    credentials: 'same-origin',
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
    signal: options.signal,
  })

  const payload = await parseResponsePayload(response)

  if (!response.ok) {
    const message = typeof payload === 'object' && payload !== null && 'error' in payload
      ? String((payload as { error: unknown }).error)
      : `Admin API request failed with status ${response.status}`

    throw new ApiError(message, response.status, payload)
  }

  return payload as T
}

interface ApiUploadOptions {
  signal?: AbortSignal
  onProgress?: (fraction: number) => void
}

function uploadErrorMessage(status: number, payload: unknown): string {
  if (typeof payload === 'object' && payload !== null && 'error' in payload) {
    return String((payload as { error: unknown }).error)
  }

  if (status === 413) {
    return 'Request entity too large'
  }

  return `Admin API request failed with status ${status}`
}

function parseUploadPayload(xhr: XMLHttpRequest): unknown {
  const contentType = xhr.getResponseHeader('content-type') ?? ''
  if (xhr.responseText === '') {
    return null
  }

  if (!contentType.includes('application/json')) {
    return xhr.responseText
  }

  try {
    return JSON.parse(xhr.responseText) as unknown
  } catch {
    return xhr.responseText
  }
}

export function apiUpload<T>(path: string, body: FormData, options: ApiUploadOptions = {}): Promise<T> {
  return new Promise<T>((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('POST', path)
    xhr.withCredentials = true
    xhr.setRequestHeader('Accept', 'application/json')
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
    xhr.setRequestHeader('X-Request-Id', requestId())
    xhr.setRequestHeader(csrfHeaderName(), csrfToken())

    xhr.upload.onprogress = (event) => {
      if (event.lengthComputable && event.total > 0) {
        options.onProgress?.(event.loaded / event.total)
      }
    }

    xhr.onload = () => {
      const payload = parseUploadPayload(xhr)
      if (xhr.status >= 200 && xhr.status < 300) {
        options.onProgress?.(1)
        resolve(payload as T)
        return
      }

      reject(new ApiError(uploadErrorMessage(xhr.status, payload), xhr.status, payload))
    }

    xhr.onerror = () => reject(new ApiError('Network error', 0, null))
    xhr.onabort = () => reject(new DOMException('Upload aborted', 'AbortError'))

    if (options.signal !== undefined) {
      if (options.signal.aborted) {
        reject(new DOMException('Upload aborted', 'AbortError'))
        return
      }
      options.signal.addEventListener('abort', () => xhr.abort(), { once: true })
    }

    xhr.send(body)
  })
}
