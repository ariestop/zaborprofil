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
  PAGE_EDITOR_SELECTORS,
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
  test('login -> pages -> editor (legacy builder route) -> add block from catalog -> save', async ({ page }) => {
    await loginToAdmin(page)
    await expect(page.locator('#admin-app')).toBeVisible()

    const createdPage = await createPageViaAdminApi(page, buildSmokePagePayload())

    await page.goto(ADMIN_ROUTES.pages)
    await expect(page).toHaveURL(new RegExp(`${ADMIN_ROUTES.pages}$`))
    await expect(page.getByRole('heading', { name: PAGES_SELECTORS.headingName })).toBeVisible()
    await expect(page.getByRole('link', { name: PAGES_SELECTORS.createLinkName })).toBeVisible()

    // Старый URL билдера продолжает работать и открывает вкладку «Контент».
    await page.goto(`/admin/pages/${createdPage.id}/builder`)
    await expect(page.getByTestId('page-editor')).toBeVisible()
    await expect(page.getByRole('tab', { name: PAGE_EDITOR_SELECTORS.tabContent })).toHaveAttribute('aria-selected', 'true')
    await expect(page.getByRole('heading', { name: BUILDER_SELECTORS.structureHeading })).toBeVisible()
    await expect(page.getByRole('region', { name: BUILDER_SELECTORS.canvasLabel })).toBeVisible()

    // Плавающий виджет сборки ассетов может перекрыть правую колонку.
    const assetWidgetToggle = page.getByRole('button', { name: SEO_SELECTORS.collapseAssetWidgetName })
    if (await assetWidgetToggle.isVisible()) {
      await assetWidgetToggle.click()
    }

    await page.getByRole('button', { name: BUILDER_SELECTORS.addBlockButtonName, exact: true }).first().click()
    const dialog = page.getByRole('dialog', { name: BUILDER_SELECTORS.addBlockDialogTitle })
    await dialog.getByRole('button', { name: /^Цены/ }).click()
    await expect(dialog).toBeHidden()

    const rows = page.getByTestId(BUILDER_SELECTORS.structureRowTestId)
    await expect(rows.last()).toContainText('Цены')
    await expect(page.getByLabel('Позиция, строка 1')).toBeVisible()
    await expect(page.getByTestId('save-indicator')).toHaveAttribute('data-state', 'dirty')

    await Promise.all([
      waitForBuilderResponse(page, createdPage.id, 'PUT'),
      page.getByRole('button', { name: PAGE_EDITOR_SELECTORS.saveButtonName, exact: true }).click(),
    ])

    const savedBlocks = await fetchBuilderBlocks(page, createdPage.id)
    expect(savedBlocks.map((block) => block.type)).toEqual(['price-table'])
    expect(savedBlocks[0]?.name).toBe('Цены')
  })

  test('create page -> edit settings and SEO -> save -> preview -> leave guard', async ({ page }) => {
    await loginToAdmin(page)
    const suffix = Date.now().toString(36)
    const title = `E2E Editor ${suffix}`

    await page.goto(ADMIN_ROUTES.pages)
    await page.getByRole('link', { name: PAGES_SELECTORS.createLinkName }).click()
    await expect(page).toHaveURL(/\/admin\/pages\/new$/)

    await page.getByLabel(PAGE_EDITOR_SELECTORS.createTitleLabel).fill(title)
    await page.getByRole('button', { name: PAGE_EDITOR_SELECTORS.createSubmitName }).click()

    await expect(page).toHaveURL(/\/admin\/pages\/(?!new$)[0-9A-Za-z-]+$/)
    const pageId = new URL(page.url()).pathname.split('/').pop() ?? ''
    await expect(page.getByTestId('page-editor')).toBeVisible()
    await expect(page.getByRole('heading', { name: title })).toBeVisible()
    await expect(page.getByTestId('save-indicator')).toHaveAttribute('data-state', 'clean')

    await page.getByRole('tab', { name: PAGE_EDITOR_SELECTORS.tabSeo }).click()
    await expect(page).toHaveURL(new RegExp(`/admin/pages/${pageId}/seo$`))
    await page.getByLabel(PAGE_EDITOR_SELECTORS.seoTitleLabel).fill(`SEO ${title}`)
    await page.getByLabel(PAGE_EDITOR_SELECTORS.seoDescriptionLabel).fill(`Описание страницы ${suffix}`)
    await expect(page.getByTestId('save-indicator')).toHaveAttribute('data-state', 'dirty')

    // Переключение вкладок не теряет несохранённые правки и не вызывает предупреждения.
    await page.getByRole('tab', { name: PAGE_EDITOR_SELECTORS.tabSettings }).click()
    await page.getByRole('tab', { name: PAGE_EDITOR_SELECTORS.tabSeo }).click()
    await expect(page.getByLabel(PAGE_EDITOR_SELECTORS.seoTitleLabel)).toHaveValue(`SEO ${title}`)

    const [seoResponse] = await Promise.all([
      page.waitForResponse((response) => response.request().method() === 'PUT' && response.url().endsWith(`/admin/api/content/pages/${pageId}/seo`)),
      page.getByRole('button', { name: PAGE_EDITOR_SELECTORS.saveButtonName, exact: true }).click(),
    ])
    expect(seoResponse.status()).toBe(200)
    await expect(page.getByTestId('save-indicator')).toHaveAttribute('data-state', 'saved')

    await page.reload()
    await page.getByRole('tab', { name: PAGE_EDITOR_SELECTORS.tabSeo }).click()
    await expect(page.getByLabel(PAGE_EDITOR_SELECTORS.seoTitleLabel)).toHaveValue(`SEO ${title}`)

    const [popup] = await Promise.all([
      page.waitForEvent('popup'),
      page.getByRole('button', { name: PAGE_EDITOR_SELECTORS.previewButtonName, exact: true }).click(),
    ])
    await popup.waitForURL(/preview/, { timeout: 15000 })
    await popup.close()

    await page.getByLabel(PAGE_EDITOR_SELECTORS.seoDescriptionLabel).fill(`Изменено ${suffix}`)
    await page.getByRole('link', { name: 'К списку страниц' }).click()
    await expect(page.getByRole('dialog', { name: PAGE_EDITOR_SELECTORS.leaveDialogTitle })).toBeVisible()
    await page.getByRole('button', { name: 'Остаться' }).click()
    await expect(page).toHaveURL(new RegExp(`/admin/pages/${pageId}/seo$`))
  })

  test('builder dnd reorders blocks and persists order after save', async ({ page }) => {
    await loginToAdmin(page)

    const createdPage = await createPageViaAdminApi(page, buildDndPagePayload())
    await createBlockViaAdminApi(page, createdPage.id, buildHeroBlockPayload())
    await createBlockViaAdminApi(page, createdPage.id, buildTextBlockPayload())

    await page.goto(`/admin/pages/${createdPage.id}`)
    const rows = page.getByTestId(BUILDER_SELECTORS.structureRowTestId)
    await expect(rows.nth(0)).toContainText('Hero block')
    await expect(rows.nth(1)).toContainText('Text block')

    await dragWithRetries(
      page,
      rows.nth(1).getByRole('button'),
      rows.nth(0).getByRole('button'),
      async () => ((await rows.nth(0).textContent()) ?? '').includes('Text block'),
    )
    await expect(rows.nth(1)).toContainText('Hero block')

    // dnd-kit подавляет click в течение ~50 мс после drop, поэтому слишком быстрый клик по «Сохранить» теряется.
    await page.waitForTimeout(200)
    await Promise.all([
      waitForBuilderResponse(page, createdPage.id, 'PUT'),
      page.getByRole('button', { name: PAGE_EDITOR_SELECTORS.saveButtonName, exact: true }).click(),
    ])

    const savedBlocks = await fetchBuilderBlocks(page, createdPage.id)
    expect(savedBlocks.map((block) => block.type)).toEqual(['text', 'hero'])
    expect(savedBlocks.map((block) => block.position)).toEqual([0, 1])
    expect(savedBlocks.map((block) => block.name)).toEqual(['Text block', 'Hero block'])
    expect(savedBlocks.every((block) => block.contentIsObject && block.settingsIsObject)).toBe(true)

    await page.reload()
    await expect(rows.nth(0)).toContainText('Text block')
    await expect(rows.nth(1)).toContainText('Hero block')
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

    await Promise.all([
      page.waitForResponse((response) => response.url().endsWith('/admin/api/media/assets') && response.request().method() === 'POST' && response.status() === 200),
      page.getByTestId('media-file-input').setInputFiles({ name: `copy-${fileName}`, mimeType: 'image/png', buffer: MEDIA_SELECTORS.pngBuffer }),
    ])
    await expect(page.getByTestId('duplicate-note').first()).toBeVisible()

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
