import { expect, test } from '@playwright/test'
import {
  buildDndPagePayload,
  buildHeroBlockPayload,
  buildInvalidPagePayload,
  buildSmokePagePayload,
  buildTextBlockPayload,
} from './helpers/fixtures'
import {
  ADMIN_ROUTES,
  BUILDER_SELECTORS,
  PAGES_SELECTORS,
} from './helpers/selectors'
import {
  createBlockViaAdminApi,
  createPageExpectValidationError,
  createPageViaAdminApi,
  dragWithRetries,
  loginToAdmin,
  waitForBuilderResponse,
} from './helpers/admin'

test.describe('Admin smoke flow', () => {
  test('login -> pages -> detail -> builder -> preview', async ({ page }) => {
    await loginToAdmin(page)
    await expect(page.locator('#admin-app')).toBeVisible()

    const createdPage = await createPageViaAdminApi(page, buildSmokePagePayload())

    await page.goto(ADMIN_ROUTES.pages)
    await expect(page).toHaveURL(new RegExp(`${ADMIN_ROUTES.pages}$`))
    await expect(page.getByRole('heading', { name: PAGES_SELECTORS.headingName })).toBeVisible()

    await page.goto(`/admin/pages/${createdPage.id}`)
    await expect(page).toHaveURL(new RegExp(`/admin/pages/${createdPage.id}$`))
    await expect(page.getByRole('link', { name: BUILDER_SELECTORS.openBuilderLinkName })).toBeVisible()
    await page.getByRole('link', { name: BUILDER_SELECTORS.openBuilderLinkName }).click()

    await expect(page).toHaveURL(new RegExp(`/admin/pages/${createdPage.id}/builder$`))
    await expect(page.getByText(BUILDER_SELECTORS.runtimeText)).toBeVisible()

    await Promise.all([
      waitForBuilderResponse(page, createdPage.id, 'PUT'),
      page.getByRole('button', { name: BUILDER_SELECTORS.saveNowButtonName }).click(),
    ])

    await expect(page.getByText(BUILDER_SELECTORS.previewPlaceholderText)).toBeVisible()
    await Promise.all([
      waitForBuilderResponse(page, createdPage.id, 'POST', '/preview'),
      page.getByRole('button', { name: BUILDER_SELECTORS.previewButtonName, exact: true }).click(),
    ])
    await expect(page.getByRole('heading', { name: BUILDER_SELECTORS.previewSectionTitle })).toBeVisible()
    await expect(page.getByText(BUILDER_SELECTORS.previewPlaceholderText)).toBeHidden()
  })

  test('builder dnd reorders blocks', async ({ page }) => {
    await loginToAdmin(page)

    const createdPage = await createPageViaAdminApi(page, buildDndPagePayload())
    await createBlockViaAdminApi(page, createdPage.id, buildHeroBlockPayload())
    await createBlockViaAdminApi(page, createdPage.id, buildTextBlockPayload())

    await page.goto(`/admin/pages/${createdPage.id}/builder`)
    await expect(page).toHaveURL(new RegExp(`/admin/pages/${createdPage.id}/builder$`))
    await expect(page.getByRole('heading', { name: BUILDER_SELECTORS.blocksSectionTitle })).toBeVisible()

    const heroFirst = page.getByRole('button', { name: 'hero position: 0' })
    const textSecond = page.getByRole('button', { name: 'text position: 1' })
    await expect(heroFirst).toBeVisible()
    await expect(textSecond).toBeVisible()

    await dragWithRetries(
      page,
      textSecond,
      heroFirst,
      async () => page.getByRole('button', { name: 'text position: 0' }).isVisible(),
    )
    await expect(page.getByRole('button', { name: 'hero position: 1' })).toBeVisible()
  })

  test('admin api returns 422 for invalid page payload', async ({ page }) => {
    await loginToAdmin(page)
    await createPageExpectValidationError(page, buildInvalidPagePayload())
  })
})
