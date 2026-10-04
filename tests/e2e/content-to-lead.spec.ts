import { expect, test } from '@playwright/test'
import { createBlockViaAdminApi, loginToAdmin } from './helpers/admin'
import { buildTextBlockPayload, buildUniquePng } from './helpers/fixtures'
import {
  ADMIN_ROUTES,
  CRM_SELECTORS,
  PAGE_EDITOR_SELECTORS,
  PUBLIC_LEAD_FORM_SELECTORS,
} from './helpers/selectors'

test.describe('Content to lead flow', () => {
  test('page with SEO and image is published, visitor sends a lead, manager takes it into work', async ({ page, browser }) => {
    const suffix = Date.now().toString(36)
    const title = `E2E Заборы ${suffix}`
    const seoTitle = `Забор из профнастила ${suffix}`
    const seoDescription = `Описание страницы ${suffix}: установка заборов под ключ.`
    const imageName = `e2e-og-${suffix}.png`
    const visitorName = `Посетитель ${suffix}`

    await loginToAdmin(page)

    await page.goto(ADMIN_ROUTES.pages)
    await page.getByRole('link', { name: 'Создать страницу' }).click()
    await page.getByLabel(PAGE_EDITOR_SELECTORS.createTitleLabel).fill(title)
    await page.getByRole('button', { name: PAGE_EDITOR_SELECTORS.createSubmitName }).click()
    await expect(page).toHaveURL(/\/admin\/pages\/(?!new$)[0-9A-Za-z-]+$/)
    const pageId = new URL(page.url()).pathname.split('/').pop() ?? ''
    await expect(page.getByTestId('page-editor')).toBeVisible()

    await createBlockViaAdminApi(page, pageId, { ...buildTextBlockPayload(), position: 0 })
    await page.reload()
    await expect(page.getByTestId('page-editor')).toBeVisible()
    const pagePath = (await page.locator('header .font-mono').first().textContent())?.trim() ?? ''
    expect(pagePath).toMatch(/^\/.+\/$/)

    await page.getByRole('tab', { name: PAGE_EDITOR_SELECTORS.tabSeo }).click()
    await page.getByLabel(PAGE_EDITOR_SELECTORS.seoTitleLabel).fill(seoTitle)
    await page.getByLabel(PAGE_EDITOR_SELECTORS.seoDescriptionLabel).fill(seoDescription)

    await page.getByTestId('media-picker').getByRole('button', { name: 'Выбрать из медиатеки' }).click()
    const dialog = page.getByRole('dialog', { name: 'Выбор из медиатеки' })
    await Promise.all([
      page.waitForResponse((response) => response.url().endsWith('/admin/api/media/assets') && response.request().method() === 'POST' && response.ok()),
      dialog.getByTestId('media-file-input').setInputFiles({ name: imageName, mimeType: 'image/png', buffer: buildUniquePng() }),
    ])
    await dialog.getByTestId('media-grid').getByRole('button', { name: new RegExp(imageName) }).click()
    await dialog.getByTestId('asset-details').getByRole('button', { name: 'Выбрать' }).click()
    await expect(dialog).toBeHidden()
    await expect(page.getByLabel('Изображение для соцсетей')).toHaveValue(/\/uploads\/media\/[0-9a-z]{26}\.png$/)
    const ogImage = await page.getByLabel('Изображение для соцсетей').inputValue()

    const [seoResponse] = await Promise.all([
      page.waitForResponse((response) => response.request().method() === 'PUT' && response.url().endsWith(`/admin/api/content/pages/${pageId}/seo`)),
      page.getByRole('button', { name: PAGE_EDITOR_SELECTORS.saveButtonName, exact: true }).click(),
    ])
    expect(seoResponse.status()).toBe(200)

    const [publishResponse] = await Promise.all([
      page.waitForResponse((response) => response.request().method() === 'POST' && response.url().endsWith(`/admin/api/content/pages/${pageId}/publish`)),
      page.getByRole('button', { name: PAGE_EDITOR_SELECTORS.publishButtonName, exact: true }).click(),
    ])
    expect(publishResponse.status()).toBe(200)
    await expect(page.getByText('Опубликовано', { exact: true }).first()).toBeVisible()

    const visitor = await browser.newContext()
    try {
      const publicPage = await visitor.newPage()
      const response = await publicPage.goto(pagePath)
      expect(response?.status()).toBe(200)
      await expect(publicPage).toHaveTitle(new RegExp(seoTitle))
      await expect(publicPage.locator('meta[name="description"]')).toHaveAttribute('content', seoDescription)
      await expect(publicPage.locator('meta[property="og:image"]')).toHaveAttribute('content', ogImage)
      await expect(publicPage.getByRole('heading', { level: 1 })).toHaveText(title)

      const form = publicPage.locator(PUBLIC_LEAD_FORM_SELECTORS.form)
      await form.locator('input[name="formLoadedAt"]').evaluate((input: HTMLInputElement) => {
        input.value = new Date(Date.now() - 60_000).toISOString()
      })
      await form.locator('input[name="name"]').fill(visitorName)
      await form.locator('input[name="phone"]').fill('+7 900 123-45-67')
      await form.locator('textarea[name="message"]').fill(`Нужен расчёт забора ${suffix}`)
      await form.locator('input[name="consent"]').check()
      await Promise.all([
        publicPage.waitForResponse((leadResponse) => leadResponse.url().endsWith('/api/leads') && leadResponse.request().method() === 'POST' && leadResponse.status() === 201),
        form.getByRole('button', { name: PUBLIC_LEAD_FORM_SELECTORS.submitName }).click(),
      ])
      await expect(form.locator('.js-lead-form-status')).toHaveText(PUBLIC_LEAD_FORM_SELECTORS.successText)
    } finally {
      await visitor.close()
    }

    await page.goto(ADMIN_ROUTES.crm)
    await page.getByLabel(CRM_SELECTORS.searchLabel).fill(visitorName)
    const row = page.getByTestId('lead-row').filter({ hasText: visitorName })
    await expect(row).toHaveCount(1)
    await expect(row.getByText('Новая', { exact: true })).toBeVisible()
    await row.getByRole('link', { name: visitorName }).click()

    const detail = page.getByTestId('lead-detail')
    await expect(detail.getByRole('heading', { name: visitorName })).toBeVisible()
    await Promise.all([
      page.waitForResponse((statusResponse) => statusResponse.request().method() === 'PATCH' && /\/admin\/api\/leads\/[0-9A-Za-z]{26}\/status$/.test(statusResponse.url()) && statusResponse.ok()),
      detail.getByRole('button', { name: CRM_SELECTORS.takeIntoWorkName }).click(),
    ])
    await expect(detail.getByText('В работе', { exact: true }).first()).toBeVisible()
    await expect(row.getByText('В работе', { exact: true })).toBeVisible()
  })
})
