export type ApiMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly payload: unknown,
  ) {
    super(message)
    this.name = 'ApiError'
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

  const contentType = response.headers.get('content-type') ?? ''
  const payload = contentType.includes('application/json') ? await response.json() : await response.text()

  if (!response.ok) {
    const message = typeof payload === 'object' && payload !== null && 'error' in payload
      ? String((payload as { error: unknown }).error)
      : `Admin API request failed with status ${response.status}`

    throw new ApiError(message, response.status, payload)
  }

  return payload as T
}
