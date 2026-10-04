import { expect, type Locator, type Page } from '@playwright/test'
import { ADMIN_LOGIN_SELECTORS, ADMIN_ROUTES } from './selectors'

export interface CreatedPagePayload {
  id: string
}

export interface CreatedBlockPayload {
  id: string
}

export function getAdminCredentials(): { email: string, password: string } {
  return {
    email: process.env.E2E_ADMIN_EMAIL ?? 'e2e-admin@example.test',
    password: process.env.E2E_ADMIN_PASSWORD ?? 'e2e-admin-password',
  }
}

export async function loginToAdmin(page: Page): Promise<void> {
  const { email, password } = getAdminCredentials()

  await page.goto(ADMIN_ROUTES.login)
  await page.locator(ADMIN_LOGIN_SELECTORS.usernameInput).fill(email)
  await page.locator(ADMIN_LOGIN_SELECTORS.passwordInput).fill(password)
  await page.getByRole('button', { name: ADMIN_LOGIN_SELECTORS.submitButtonName }).click()
  await expect(page).toHaveURL(new RegExp(`${ADMIN_ROUTES.dashboard}$`))
}

export async function createPageViaAdminApi(page: Page, payload: Record<string, unknown>): Promise<CreatedPagePayload> {
  const csrfToken = await page.locator('meta[name="admin-csrf-token"]').getAttribute('content')
  if (csrfToken === null || csrfToken === '') {
    throw new Error('Admin CSRF token is missing in page meta.')
  }

  const response = await page.evaluate(
    async ({ requestPayload, token }) => {
      const request = await fetch('/admin/api/content/pages', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': token,
        },
        body: JSON.stringify(requestPayload),
      })

      return {
        status: request.status,
        payload: await request.json(),
      }
    },
    { requestPayload: payload, token: csrfToken },
  )

  if (response.status !== 201 || typeof response.payload?.id !== 'string') {
    throw new Error(`Could not create page for e2e smoke. Status: ${response.status}`)
  }

  return response.payload as CreatedPagePayload
}

export async function createPageExpectValidationError(page: Page, payload: Record<string, unknown>): Promise<void> {
  const response = await postAdminApi(page, '/admin/api/content/pages', payload)
  if (response.status !== 422) {
    throw new Error(`Expected 422 validation error, got ${response.status}.`)
  }

  const payloadObject = (response.payload ?? {}) as Record<string, unknown>
  const details = payloadObject.details
  const hasArrayDetails = Array.isArray(details) && details.length > 0
  const hasErrorMessage = typeof payloadObject.error === 'string' && payloadObject.error.trim() !== ''
  const hasDetailMessage = typeof payloadObject.detail === 'string' && payloadObject.detail.trim() !== ''
  if (!hasArrayDetails && !hasErrorMessage && !hasDetailMessage) {
    throw new Error('Validation error payload does not contain expected fields.')
  }
}

export async function createBlockViaAdminApi(
  page: Page,
  pageId: string,
  payload: Record<string, unknown>,
): Promise<CreatedBlockPayload> {
  const response = await postAdminApi(page, `/admin/api/content/pages/${pageId}/blocks`, payload)

  if (response.status !== 201 || typeof response.payload?.id !== 'string') {
    throw new Error(`Could not create block for e2e smoke. Status: ${response.status}`)
  }

  return response.payload as CreatedBlockPayload
}

export async function dragWithRetries(
  page: Page,
  source: Locator,
  target: Locator,
  isReordered: () => Promise<boolean>,
): Promise<void> {
  for (const targetYOffsetRatio of [0.2, 0.5, 0.8]) {
    await dragElementToElement(page, source, target, targetYOffsetRatio)
    if (await isReordered()) {
      return
    }
  }

  throw new Error('DnD reorder did not change block order after retries.')
}

export interface BuilderBlockSnapshot {
  type: string
  position: number
  contentIsObject: boolean
  settingsIsObject: boolean
}

export async function fetchBuilderBlocks(page: Page, pageId: string): Promise<BuilderBlockSnapshot[]> {
  const response = await page.evaluate(async (id) => {
    const request = await fetch(`/admin/api/content/pages/${id}/builder`, {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })

    return { status: request.status, payload: await request.json() as { blocks?: Array<Record<string, unknown>> } }
  }, pageId)

  if (response.status !== 200 || !Array.isArray(response.payload.blocks)) {
    throw new Error(`Could not load builder document. Status: ${response.status}`)
  }

  const isObject = (value: unknown): boolean => typeof value === 'object' && value !== null && !Array.isArray(value)

  return response.payload.blocks.map((block) => ({
    type: String(block.type),
    position: Number(block.position),
    contentIsObject: isObject(block.content),
    settingsIsObject: isObject(block.settings),
  }))
}

export function waitForBuilderResponse(page: Page, pageId: string, method: 'PUT' | 'POST', suffix = ''): Promise<unknown> {
  return page.waitForResponse((response) => {
    return response.request().method() === method
      && response.url().endsWith(`/admin/api/content/pages/${pageId}/builder${suffix}`)
      && response.status() === 200
  }, { timeout: 10000 })
}

async function dragElementToElement(
  page: Page,
  source: Locator,
  target: Locator,
  targetYOffsetRatio: number,
): Promise<void> {
  const sourceBox = await source.boundingBox()
  const targetBox = await target.boundingBox()
  if (sourceBox === null || targetBox === null) {
    throw new Error('Could not resolve drag source/target bounding boxes.')
  }

  await page.mouse.move(sourceBox.x + sourceBox.width / 2, sourceBox.y + sourceBox.height / 2)
  await page.mouse.down()
  await page.mouse.move(targetBox.x + targetBox.width / 2, targetBox.y + targetBox.height * targetYOffsetRatio, { steps: 18 })
  await page.mouse.up()
}

async function postAdminApi(page: Page, path: string, payload: Record<string, unknown>): Promise<{ status: number, payload: unknown }> {
  const csrfToken = await page.locator('meta[name="admin-csrf-token"]').getAttribute('content')
  if (csrfToken === null || csrfToken === '') {
    throw new Error('Admin CSRF token is missing in page meta.')
  }

  const response = await page.evaluate(
    async ({ requestPath, requestPayload, token }) => {
      const request = await fetch(requestPath, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': token,
        },
        body: JSON.stringify(requestPayload),
      })

      return {
        status: request.status,
        payload: await request.json(),
      }
    },
    { requestPath: path, requestPayload: payload, token: csrfToken },
  )

  return response
}
