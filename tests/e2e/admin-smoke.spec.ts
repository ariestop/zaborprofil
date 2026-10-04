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
  MEDIA_SELECTORS,
  PAGES_SELECTORS,
  SEO_SELECTORS,
} from './helpers/selectors'
import {
  createBlockViaAdminApi,
  createPageExpectValidationError,
  createPageViaAdminApi,
  dragWithRetries,
  fetchBuilderBlocks,
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

  test('builder dnd reorders blocks and persists order after save', async ({ page }) => {
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

    // dnd-kit подавляет click в течение ~50 мс после drop, поэтому слишком быстрый клик по «Save now» теряется.
    await page.waitForTimeout(200)
    await Promise.all([
      waitForBuilderResponse(page, createdPage.id, 'PUT'),
      page.getByRole('button', { name: BUILDER_SELECTORS.saveNowButtonName }).click(),
    ])

    const savedBlocks = await fetchBuilderBlocks(page, createdPage.id)
    expect(savedBlocks.map((block) => block.type)).toEqual(['text', 'hero'])
    expect(savedBlocks.map((block) => block.position)).toEqual([0, 1])
    expect(savedBlocks.every((block) => block.contentIsObject && block.settingsIsObject)).toBe(true)

    await page.reload()
    await expect(page.getByRole('button', { name: 'text position: 0' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'hero position: 1' })).toBeVisible()
  })

  test('admin api returns 422 for invalid page payload', async ({ page }) => {
    await loginToAdmin(page)
    await createPageExpectValidationError(page, buildInvalidPagePayload())
  })

  test('seo panel: redirect from 404 journal, robots validation', async ({ page, request }) => {
    await loginToAdmin(page)
    const suffix = Date.now().toString(36)
    const missingPath = `/e2e-missing-${suffix}/`
    const targetPath = `/e2e-target-${suffix}/`

    const missing = await request.get(missingPath)
    expect(missing.status()).toBe(404)

    await page.goto(ADMIN_ROUTES.seo)
    await expect(page.getByRole('heading', { name: SEO_SELECTORS.headingName })).toBeVisible()

    // Плавающий виджет сборки ассетов перекрывает правую колонку таблиц.
    const assetWidgetToggle = page.getByRole('button', { name: SEO_SELECTORS.collapseAssetWidgetName })
    if (await assetWidgetToggle.isVisible()) {
      await assetWidgetToggle.click()
    }

    await page.getByRole('tab', { name: SEO_SELECTORS.notFoundTabName }).click()
    await page.getByLabel(SEO_SELECTORS.notFoundSearchLabel).fill(`e2e-missing-${suffix}`)
    const row = page.getByTestId('not-found-row').filter({ hasText: missingPath })
    await expect(row).toBeVisible()

    await row.getByRole('button', { name: SEO_SELECTORS.createRedirectButtonName }).click()
    await expect(page.getByLabel(SEO_SELECTORS.sourceInputLabel)).toHaveValue(missingPath)
    await page.getByLabel(SEO_SELECTORS.targetInputLabel).fill(targetPath)
    await page.getByRole('button', { name: SEO_SELECTORS.saveButtonName }).click()
    await expect(row.getByText('Редирект есть')).toBeVisible()

    const redirected = await request.get(missingPath, { maxRedirects: 0 })
    expect(redirected.status()).toBe(301)
    expect(redirected.headers().location).toBe(targetPath)

    await page.getByRole('tab', { name: 'Редиректы' }).click()
    await page.getByLabel(SEO_SELECTORS.redirectSearchLabel).fill(`e2e-missing-${suffix}`)
    await expect(page.getByTestId('redirect-row').filter({ hasText: missingPath })).toBeVisible()

    await page.getByRole('tab', { name: SEO_SELECTORS.robotsTabName }).click()
    await page.getByLabel(SEO_SELECTORS.robotsEditorLabel).fill('User-agent: *\nDisallow: private')
    await expect(page.getByLabel(SEO_SELECTORS.robotsIssuesLabel)).toContainText('строка 2')
    await expect(page.getByRole('button', { name: SEO_SELECTORS.saveButtonName })).toBeDisabled()
  })

  test('media library: upload, edit alt, search and delete', async ({ page }) => {
    await loginToAdmin(page)
    await page.goto(ADMIN_ROUTES.media)
    await expect(page.getByRole('heading', { name: MEDIA_SELECTORS.headingName })).toBeVisible()

    const fileName = `e2e-fence-${Date.now()}.png`
    await Promise.all([
      page.waitForResponse((response) => response.url().endsWith('/admin/api/media/assets') && response.request().method() === 'POST' && response.status() === 201),
      page.getByTestId('media-file-input').setInputFiles({ name: fileName, mimeType: 'image/png', buffer: MEDIA_SELECTORS.pngBuffer }),
    ])

    const details = page.getByTestId('asset-details')
    await expect(details).toBeVisible()
    await details.getByLabel(/^Alt/).fill('Забор из профнастила E2E')
    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/admin/api/media/assets/') && response.request().method() === 'PATCH' && response.ok()),
      details.getByRole('button', { name: 'Сохранить' }).click(),
    ])

    await page.getByLabel('Поиск по медиатеке').fill('профнастила E2E')
    await expect(page.getByTestId('media-grid').getByRole('button', { name: new RegExp(fileName) })).toBeVisible()

    await page.getByTestId('media-grid').getByRole('button', { name: new RegExp(fileName) }).click()
    await page.getByTestId('asset-details').getByRole('button', { name: 'Удалить' }).click()
    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/admin/api/media/assets/') && response.request().method() === 'DELETE' && response.status() === 204),
      page.getByRole('dialog').getByRole('button', { name: 'Удалить' }).click(),
    ])
    await expect(page.getByText('Ничего не найдено')).toBeVisible()
  })
})
