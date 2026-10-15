// PNG 1600×1200 из docs/brand/catalog/*.svg для загрузки в медиатеку (она принимает JPEG, PNG, WebP, AVIF).
// Запуск: node tools/brand/render-catalog-png.mjs [папка-результата=var/brand/catalog]
// Chromium — из Playwright (@playwright/test); свой путь можно задать PLAYWRIGHT_CHROMIUM_PATH.
import { mkdirSync, readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import { chromium } from '@playwright/test'

const source = new URL('../../docs/brand/catalog/', import.meta.url).pathname
const target = process.argv[2] ?? 'var/brand/catalog'
mkdirSync(target, { recursive: true })

const browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM_PATH || undefined })
try {
    const page = await browser.newPage({ viewport: { width: 800, height: 600 }, deviceScaleFactor: 2 })
    for (const file of readdirSync(source).filter((name) => name.endsWith('.svg')).sort()) {
        const svg = readFileSync(join(source, file), 'utf8').replace('width="800" height="600"', 'width="800" height="600" style="display:block"')
        await page.setContent(`<html><body style="margin:0">${svg}</body></html>`)
        const out = join(target, file.replace(/\.svg$/, '.png'))
        await page.screenshot({ path: out, clip: { x: 0, y: 0, width: 800, height: 600 } })
        console.log(out)
    }
} finally {
    await browser.close()
}
