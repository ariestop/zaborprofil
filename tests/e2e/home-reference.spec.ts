import { expect, test } from './helpers/test'

test('reference home: mobile navigation and contextual enquiry work without submitting a lead', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  const response = await page.goto('/')
  expect(response?.status()).toBe(200)
  await expect(page.locator('.ref-hero h1')).toContainText('Хороший забор')
  await expect(page.locator('.ref-hero__image')).toHaveJSProperty('complete', true)
  await page.evaluate(() => document.fonts.ready)
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)

  const menu = page.getByRole('button', { name: 'Открыть меню' })
  await menu.click()
  await expect(menu).toHaveAttribute('aria-expanded', 'true')
  await page.locator('#ref-mobile-menu').getByRole('link', { name: 'Заборы', exact: true }).click()
  await expect(menu).toHaveAttribute('aria-expanded', 'false')
  await expect(page.locator('#ref-mobile-menu')).toBeHidden()

  await page.locator('.ref-project--large').getByRole('link', { name: 'Хочу похожий', exact: true }).click()
  const enquiry = page.getByRole('dialog', { name: 'Получить расчёт', exact: true })
  await expect(enquiry).toBeVisible()
  await expect(enquiry.locator('textarea[name="message"]')).toHaveValue(/Ранчо у современного дома/)
  await enquiry.getByRole('button', { name: 'Закрыть', exact: true }).press('Escape')
  await expect(enquiry).toBeHidden()

  await page.getByRole('link', { name: 'Подробнее: Жалюзи', exact: true }).click()
  await expect(page.getByRole('dialog', { name: 'Забор жалюзи', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Закрыть', exact: true }).click()
  await page.screenshot({ path: test.info().outputPath('home-mobile.png'), fullPage: true })
})
