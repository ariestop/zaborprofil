import { vi } from 'vitest'

/**
 * Data router (`createMemoryRouter`) создаёт `new Request(url, { signal })`. В jsdom сигнал принадлежит
 * jsdom-реализации AbortSignal, а глобальный Request — Node (undici), и новые версии Node отклоняют такой сигнал.
 * Для тестов навигации сигнал не нужен, поэтому отбрасываем его.
 */
export function stubRequestWithoutSignal(): void {
  const NodeRequest = globalThis.Request

  class TestRequest extends NodeRequest {
    constructor(input: RequestInfo | URL, init?: RequestInit) {
      const rest: RequestInit = { ...init }
      delete rest.signal
      super(input, rest)
    }
  }

  vi.stubGlobal('Request', TestRequest)
}
