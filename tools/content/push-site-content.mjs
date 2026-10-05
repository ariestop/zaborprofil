#!/usr/bin/env node
/**
 * Загружает контент из site-content.mjs в CMS через административный API — так же, как это делает админка:
 * вход по сессии, загрузка картинок в медиатеку, страницы и блоки, SEO, публикация, пункты меню.
 * Скрипт идемпотентен: повторный запуск обновляет те же страницы и не плодит дубликаты
 * (картинки сопоставляются по хешу файла, меню — по адресу).
 *
 * Пароль берётся только из окружения, чтобы не попасть в историю shell:
 *
 *   ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='…' \
 *     node tools/content/push-site-content.mjs --base-url https://dev.zaborprofil.ru --media-dir ./media
 *
 * Параметры:
 *   --base-url   адрес сайта (обязательно), например https://dev.zaborprofil.ru
 *   --media-dir  каталог с файлами для ссылок `media:<имя>` (обязательно, если контент их содержит)
 *   --only       ключи страниц через запятую (home,zabor-jaluzi); по умолчанию — все
 *   --no-publish не публиковать страницы (оставить черновиками)
 *   --no-menu    не трогать меню
 *   --cookie     дополнительные cookie (на Beget-стенде нужен `beget=begetok`, по умолчанию он уже добавлен для *.zaborprofil.ru)
 *   --dry-run    только показать, что будет сделано
 */
import { readFile } from 'node:fs/promises'
import path from 'node:path'
import { menuItems, pages } from './site-content.mjs'

const MIME = { '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png', '.webp': 'image/webp' }

function parseArgs(argv) {
  const args = {}
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i]
    if (!arg.startsWith('--')) {
      continue
    }
    const key = arg.slice(2)
    const next = argv[i + 1]
    if (next === undefined || next.startsWith('--')) {
      args[key] = true
    } else {
      args[key] = next
      i += 1
    }
  }

  return args
}

class AdminClient {
  constructor(baseUrl, extraCookie) {
    this.baseUrl = baseUrl.replace(/\/$/, '')
    this.host = new URL(this.baseUrl).host
    this.cookies = new Map()
    this.csrf = null
    for (const pair of extraCookie ? extraCookie.split(';') : []) {
      const [name, ...rest] = pair.trim().split('=')
      if (name) {
        this.cookies.set(name, rest.join('='))
      }
    }
  }

  cookieHeader() {
    return [...this.cookies].map(([name, value]) => `${name}=${value}`).join('; ')
  }

  async request(method, url, { json, form, body, headers = {} } = {}) {
    const requestHeaders = {
      Origin: this.baseUrl,
      Referer: `${this.baseUrl}/admin/`,
      'Sec-Fetch-Site': 'same-origin',
      Accept: 'application/json, text/html;q=0.9',
      Cookie: this.cookieHeader(),
      ...headers,
    }
    let payload = body
    if (json !== undefined) {
      requestHeaders['Content-Type'] = 'application/json'
      payload = JSON.stringify(json)
    } else if (form !== undefined) {
      requestHeaders['Content-Type'] = 'application/x-www-form-urlencoded'
      payload = new URLSearchParams(form).toString()
    }
    if (this.csrf !== null && method !== 'GET') {
      requestHeaders['X-CSRF-Token'] = this.csrf
    }

    const response = await fetch(`${this.baseUrl}${url}`, { method, headers: requestHeaders, body: payload, redirect: 'manual' })
    for (const line of response.headers.getSetCookie?.() ?? []) {
      const [pair] = line.split(';')
      const [name, ...rest] = pair.split('=')
      this.cookies.set(name.trim(), rest.join('='))
    }

    return response
  }

  async api(method, url, options) {
    const response = await this.request(method, url, options)
    const text = await response.text()
    let data = null
    try {
      data = text === '' ? null : JSON.parse(text)
    } catch {
      // не JSON — разберём ниже по статусу
    }
    if (!response.ok) {
      throw new Error(`${method} ${url} → ${response.status}: ${(data && (data.error?.message ?? data.message)) ?? text.slice(0, 300)}`)
    }

    return data
  }

  async login(email, password) {
    // Beget подставляет cookie через JS-проверку; для остальных хостов запрос просто вернёт форму.
    await this.request('GET', '/admin/login')
    const response = await this.request('POST', '/admin/login', {
      form: { _username: email, _password: password, _csrf_token: 'csrf-token' },
    })
    const location = response.headers.get('location') ?? ''
    if (response.status !== 302 || location.includes('/admin/login')) {
      throw new Error('Не удалось войти в админку: проверьте email и пароль (ADMIN_EMAIL / ADMIN_PASSWORD).')
    }

    const dashboard = await this.request('GET', '/admin/dashboard')
    const html = await dashboard.text()
    const match = html.match(/<meta name="admin-csrf-token" content="([^"]+)"/)
    if (match === null) {
      throw new Error('Не удалось получить CSRF-токен админ-API после входа.')
    }
    this.csrf = match[1]
  }
}

function collectMedia(value, found = new Set()) {
  if (typeof value === 'string' && value.startsWith('media:')) {
    found.add(value.slice('media:'.length))
  } else if (Array.isArray(value)) {
    value.forEach((item) => collectMedia(item, found))
  } else if (value !== null && typeof value === 'object') {
    Object.values(value).forEach((item) => collectMedia(item, found))
  }

  return found
}

function resolveMedia(value, map) {
  if (typeof value === 'string' && value.startsWith('media:')) {
    const resolved = map.get(value.slice('media:'.length))
    if (resolved === undefined) {
      throw new Error(`Нет загруженной картинки для ${value}`)
    }

    return resolved
  }
  if (Array.isArray(value)) {
    return value.map((item) => resolveMedia(item, map))
  }
  if (value !== null && typeof value === 'object') {
    return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, resolveMedia(item, map)]))
  }

  return value
}

async function uploadMedia(client, mediaDir, names, dryRun) {
  const map = new Map()
  for (const name of [...names].sort()) {
    if (dryRun) {
      map.set(name, `/uploads/media/(dry-run)/${name}`)
      continue
    }
    const bytes = await readFile(path.join(mediaDir, name))
    const type = MIME[path.extname(name).toLowerCase()] ?? 'application/octet-stream'
    const form = new FormData()
    form.append('file', new Blob([bytes], { type }), name)
    form.append('folder', 'site')
    const asset = await client.api('POST', '/admin/api/media/assets', { body: form })
    map.set(name, asset.publicPath)
    console.log(`  медиа  ${name} → ${asset.publicPath}${asset.duplicate ? ' (уже была)' : ''}`)
  }

  return map
}

async function syncPage(client, definition, media, options) {
  const list = await client.api('GET', '/admin/api/content/pages')
  let existing = list.pages.find((page) => page.path === definition.path)
  const common = {
    type: definition.create.type,
    title: definition.title,
    slug: definition.create.slug,
    path: definition.path,
    h1: definition.h1,
    template: definition.create.template,
    sortOrder: 0,
    isIndexable: true,
    visibility: 'public',
  }

  if (options.dryRun) {
    console.log(`  страница ${definition.path}: ${existing ? 'обновить' : 'создать'}, блоков: ${definition.blocks.length}`)

    return
  }

  let page
  if (existing === undefined) {
    page = await client.api('POST', '/admin/api/content/pages', { json: common })
    console.log(`  страница ${definition.path}: создана (${page.id})`)
  } else {
    page = await client.api('PUT', `/admin/api/content/pages/${existing.id}`, {
      json: { ...common, type: existing.type ?? common.type, slug: existing.slug ?? common.slug, template: existing.template ?? common.template, parentId: existing.parentId ?? null },
    })
    console.log(`  страница ${definition.path}: обновлена (${page.id})`)
  }

  await client.api('PUT', `/admin/api/content/pages/${page.id}/seo`, {
    json: {
      metaTitle: definition.seo.metaTitle,
      metaDescription: definition.seo.metaDescription,
      ogTitle: definition.seo.metaTitle,
      ogDescription: definition.seo.metaDescription,
      // OG-картинка должна быть абсолютным адресом, поэтому добавляем адрес сайта.
      ogImage: new URL(resolveMedia(definition.seo.ogImage, media), client.baseUrl).toString(),
      ogType: definition.seo.ogType,
    },
  })

  const blocks = definition.blocks.map((block) => ({
    type: block.type,
    name: block.name,
    enabled: true,
    content: resolveMedia(block.content, media),
    settings: block.settings ?? {},
  }))
  const current = await client.api('GET', `/admin/api/content/pages/${page.id}/builder`)
  // Новые id блоков: повторная загрузка полностью заменяет содержимое страницы тем, что описано в site-content.mjs.
  const saved = await client.api('PUT', `/admin/api/content/pages/${page.id}/builder`, {
    json: { blocks, baseVersion: current.version ?? undefined },
  })
  console.log(`  блоки: ${saved.blocks?.length ?? blocks.length}`)

  if (options.publish) {
    await client.api('POST', `/admin/api/content/pages/${page.id}/publish`, { json: { comment: 'Перенос контента со старого сайта' } })
    console.log('  опубликована')
  }
}

async function syncMenu(client, dryRun) {
  const current = await client.api('GET', '/admin/api/menu/items')
  for (const item of menuItems) {
    const exists = current.items.some((row) => row.position === item.position && row.url === item.url)
    if (exists) {
      continue
    }
    console.log(`  меню ${item.position}: ${item.label} → ${item.url}`)
    if (!dryRun) {
      await client.api('POST', '/admin/api/menu/items', { json: { ...item, isActive: true } })
    }
  }
}

async function main() {
  const args = parseArgs(process.argv.slice(2))
  const baseUrl = typeof args['base-url'] === 'string' ? args['base-url'] : ''
  const email = process.env.ADMIN_EMAIL ?? ''
  const password = process.env.ADMIN_PASSWORD ?? ''
  if (baseUrl === '' || (!args['dry-run'] && (email === '' || password === ''))) {
    console.error('Нужны --base-url и переменные окружения ADMIN_EMAIL, ADMIN_PASSWORD (см. комментарий в начале файла).')
    process.exit(2)
  }

  const only = typeof args.only === 'string' ? args.only.split(',') : null
  const selected = pages.filter((page) => only === null || only.includes(page.key))
  const defaultCookie = new URL(baseUrl).hostname.endsWith('zaborprofil.ru') ? 'beget=begetok' : ''
  const client = new AdminClient(baseUrl, typeof args.cookie === 'string' ? args.cookie : defaultCookie)
  const dryRun = args['dry-run'] === true

  if (!dryRun) {
    await client.login(email, password)
    console.log(`Вход выполнен: ${baseUrl}`)
  }

  const names = new Set()
  selected.forEach((page) => collectMedia(page, names))
  if (names.size > 0 && typeof args['media-dir'] !== 'string') {
    console.error('В контенте есть картинки — укажите --media-dir с исходными файлами.')
    process.exit(2)
  }
  const media = await uploadMedia(client, args['media-dir'], names, dryRun)

  for (const page of selected) {
    await syncPage(client, page, media, { dryRun, publish: args['no-publish'] !== true })
  }
  if (args['no-menu'] !== true) {
    if (dryRun) {
      console.log('  меню: проверка пропущена в dry-run')
    } else {
      await syncMenu(client, dryRun)
    }
  }
  console.log('Готово.')
}

main().catch((error) => {
  console.error(error.message ?? error)
  process.exit(1)
})
