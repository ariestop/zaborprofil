import { type BrowserContext, expect, test as base } from '@playwright/test'

/**
 * Собирает нарушения Content-Security-Policy, о которых Chromium пишет в консоль
 * («Refused to execute inline script because it violates the following Content Security Policy directive…»).
 * В APP_ENV=test CSP применяется так же, как в prod, поэтому нарушение здесь — сломанная функция на сайте.
 */
export function collectCspViolations(context: BrowserContext): string[] {
  const violations: string[] = []
  context.on('console', (message) => {
    if (message.text().includes('Content Security Policy')) {
      violations.push(`${message.page()?.url() ?? '?'}: ${message.text()}`)
    }
  })

  return violations
}

export const test = base.extend<{ cspGuard: void }>({
  cspGuard: [
    async ({ context }, use) => {
      const violations = collectCspViolations(context)
      await use()
      expect(violations, 'Content-Security-Policy violations').toEqual([])
    },
    { auto: true },
  ],
})

export { expect }
