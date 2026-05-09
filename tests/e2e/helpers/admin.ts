import { expect, type Locator, type Page } from '@playwright/test'
import { RICH_TEXT_SMOKE_APPEND } from './fixtures'
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

export async function triggerReorderWithRetries(
  page: Page,
  pageId: string,
  source: Locator,
  target: Locator,
): Promise<void> {
  for (const targetYOffsetRatio of [0.2, 0.5, 0.8]) {
    const responsePromise = page.waitForResponse((response) => {
      return response.request().method() === 'POST'
        && response.url().includes(`/admin/api/content/pages/${pageId}/blocks/reorder`)
        && response.status() === 200
    }, { timeout: 5000 }).then(() => true).catch(() => false)

    await dragElementToElement(page, source, target, targetYOffsetRatio)
    if (await responsePromise) {
      return
    }
  }

  throw new Error('DnD reorder mutation did not fire after retries.')
}

export async function waitForRichTextSaveResponse(page: Page): Promise<void> {
  await page.waitForResponse((response) => {
    const requestBody = response.request().postData() ?? ''
    return response.request().method() === 'PUT'
      && response.url().includes('/admin/api/content/blocks/')
      && response.status() === 200
      && requestBody.includes(RICH_TEXT_SMOKE_APPEND.trim())
  }, { timeout: 10000 })
}

export async function assertPreviewPageIsReachable(
  page: Page,
  previewLink: Locator,
  pageId: string,
): Promise<void> {
  const href = await previewLink.getAttribute('href')
  if (href === null || href === '') {
    throw new Error('Preview link href is empty.')
  }

  const [previewPopup] = await Promise.all([
    page.waitForEvent('popup'),
    previewLink.click(),
  ])
  await previewPopup.waitForLoadState('domcontentloaded')
  await expect(previewPopup).toHaveURL(new RegExp(`/_preview/content/pages/${pageId}/`))
  await previewPopup.close()

  const previewResponse = await page.request.get(href)
  expect(previewResponse.status()).toBe(200)
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
