import { apiRequest, ApiError } from '../api/client'

export type ClientErrorSource = 'error-boundary' | 'window-error' | 'unhandled-rejection'

const MAX_REPORTS_PER_SESSION = 5
const IGNORED_MESSAGES = [/ResizeObserver loop/i, /^Script error\.?$/i]

const reportedFingerprints = new Set<string>()

function describeError(error: unknown): { message: string; stack?: string } {
  if (error instanceof Error) {
    return { message: `${error.name}: ${error.message}`, stack: error.stack }
  }

  if (typeof error === 'string') {
    return { message: error }
  }

  try {
    return { message: JSON.stringify(error) ?? String(error) }
  } catch {
    return { message: String(error) }
  }
}

function shouldIgnore(error: unknown, message: string): boolean {
  if (error instanceof ApiError) {
    return true
  }

  if (error instanceof DOMException && error.name === 'AbortError') {
    return true
  }

  return IGNORED_MESSAGES.some((pattern) => pattern.test(message))
}

/**
 * Отправляет ошибку SPA в `POST /admin/api/client-errors`. Не бросает исключений, не повторяет
 * одинаковые ошибки и ограничивает число отчётов за сессию страницы, чтобы цикл рендера не завалил сервер.
 */
export async function reportClientError(
  error: unknown,
  source: ClientErrorSource,
  extra: { componentStack?: string | null } = {},
): Promise<boolean> {
  const { message, stack } = describeError(error)
  if (shouldIgnore(error, message) || reportedFingerprints.size >= MAX_REPORTS_PER_SESSION) {
    return false
  }

  const fingerprint = `${source}|${message}`
  if (reportedFingerprints.has(fingerprint)) {
    return false
  }
  reportedFingerprints.add(fingerprint)

  try {
    await apiRequest('/admin/api/client-errors', {
      method: 'POST',
      body: {
        message,
        source,
        url: window.location.href,
        stack,
        componentStack: extra.componentStack ?? undefined,
      },
    })

    return true
  } catch {
    return false
  }
}

export function installGlobalClientErrorReporting(target: Pick<Window, 'addEventListener' | 'removeEventListener'> = window): () => void {
  const onError = (event: ErrorEvent) => {
    void reportClientError(event.error ?? event.message, 'window-error')
  }
  const onRejection = (event: PromiseRejectionEvent) => {
    void reportClientError(event.reason, 'unhandled-rejection')
  }

  target.addEventListener('error', onError)
  target.addEventListener('unhandledrejection', onRejection)

  return () => {
    target.removeEventListener('error', onError)
    target.removeEventListener('unhandledrejection', onRejection)
  }
}

export function resetClientErrorReporterForTests(): void {
  reportedFingerprints.clear()
}
