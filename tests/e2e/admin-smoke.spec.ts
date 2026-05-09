import { expect, test } from '@playwright/test'
import {
  buildDndPagePayload,
  buildHeroBlockPayload,
  buildInvalidPagePayload,
  buildSmokePagePayload,
  buildTextBlockPayload,
  RICH_TEXT_SMOKE_APPEND,
} from './helpers/fixtures'
import {
  ADMIN_ROUTES,
  BUILDER_SELECTORS,
} from './helpers/selectors'
import {
  assertPreviewPageIsReachable,
  createBlockViaAdminApi,
  createPageExpectValidationError,
  createPageViaAdminApi,
  loginToAdmin,
  triggerReorderWithRetries,
  waitForRichTextSaveResponse,
} from './helpers/admin'

test.describe('Admin smoke flow', () => {
  test('login -> pages -> detail -> builder -> preview', async ({ page }) => {
    await loginToAdmin(page)
    await expect(page.locator('#admin-app')).toBeVisible()

    const createdPage = await createPageViaAdminApi(page, buildSmokePagePayload())

    await page.goto(ADMIN_ROUTES.pages)
    await expect(page).toHaveURL(new RegExp(`${ADMIN_ROUTES.pages}$`))
    await expect(page.getByText('Pages')).toBeVisible()

    await page.goto(`/admin/pages/${createdPage.id}`)
    await expect(page).toHaveURL(new RegExp(`/admin/pages/${createdPage.id}$`))
    await expect(page.getByRole('link', { name: BUILDER_SELECTORS.openBuilderLinkName })).toBeVisible()
    await page.getByRole('link', { name: BUILDER_SELECTORS.openBuilderLinkName }).click()

    await expect(page).toHaveURL(new RegExp(`/admin/pages/${createdPage.id}/builder$`))
    await expect(page.getByText(BUILDER_SELECTORS.runtimeText)).toBeVisible()

    await page.getByRole('button', { name: BUILDER_SELECTORS.saveNowButtonName }).click()
    const previewLink = page.getByRole('link', { name: BUILDER_SELECTORS.previewLinkName })
    await expect(previewLink).toBeVisible()
    await assertPreviewPageIsReachable(page, previewLink, createdPage.id)
  })

  test('builder dnd reorder + rich text save mutations', async ({ page }) => {
    await loginToAdmin(page)

    const createdPage = await createPageViaAdminApi(page, buildDndPagePayload())

    await createBlockViaAdminApi(page, createdPage.id, buildHeroBlockPayload())

    await createBlockViaAdminApi(page, createdPage.id, buildTextBlockPayload())

    await page.goto(`/admin/pages/${createdPage.id}/builder`)
    await expect(page).toHaveURL(new RegExp(`/admin/pages/${createdPage.id}/builder$`))
    await expect(page.getByText(BUILDER_SELECTORS.dndSectionTitle)).toBeVisible()

    const textBlockRow = page.getByRole('button', { name: 'Text block' })
    const heroBlockRow = page.getByRole('button', { name: 'Hero block' })

    await triggerReorderWithRetries(page, createdPage.id, textBlockRow, heroBlockRow)

    await textBlockRow.click()
    await page.getByLabel(BUILDER_SELECTORS.richTextAriaLabel).click()
    await page.keyboard.type(RICH_TEXT_SMOKE_APPEND)

    await Promise.all([
      waitForRichTextSaveResponse(page),
      page.getByRole('button', { name: BUILDER_SELECTORS.saveRichTextButtonName }).click(),
    ])
  })

  test('admin api returns 422 for invalid page payload', async ({ page }) => {
    await loginToAdmin(page)
    await createPageExpectValidationError(page, buildInvalidPagePayload())
  })
})
