import { crc32, deflateSync } from 'node:zlib'

export function buildSmokePagePayload(): Record<string, unknown> {
  const uniqueId = Date.now()

  return {
    type: 'landing',
    title: 'E2E Smoke Page',
    slug: `e2e-smoke-${uniqueId}`,
    path: `/e2e-smoke-${uniqueId}/`,
    h1: 'E2E Smoke Page',
    template: 'default',
    sortOrder: 0,
    isIndexable: true,
    visibility: 'public',
  }
}

export function buildDndPagePayload(): Record<string, unknown> {
  const uniqueId = Date.now()

  return {
    type: 'landing',
    title: 'E2E DnD Page',
    slug: `e2e-dnd-${uniqueId}`,
    path: `/e2e-dnd-${uniqueId}/`,
    h1: 'E2E DnD Page',
    template: 'default',
    sortOrder: 0,
    isIndexable: true,
    visibility: 'public',
  }
}

export function buildHeroBlockPayload(): Record<string, unknown> {
  return {
    type: 'hero',
    name: 'Hero block',
    position: 0,
    content: { title: 'Hero' },
    settings: {},
    isEnabled: true,
    visibility: 'public',
  }
}

export function buildTextBlockPayload(): Record<string, unknown> {
  return {
    type: 'text',
    name: 'Text block',
    position: 1,
    content: { richText: '<p>Initial text</p>' },
    settings: {},
    isEnabled: true,
    visibility: 'public',
  }
}

export function buildInvalidPagePayload(): Record<string, unknown> {
  return {
    type: 'landing',
    title: 'x',
    slug: '',
    path: '',
    h1: '',
    template: 'default',
    sortOrder: 0,
    isIndexable: true,
    visibility: 'public',
  }
}

/** PNG 2×2 со случайным цветом: медиатека дедуплицирует файлы по хешу, поэтому каждому запуску нужен уникальный файл. */
export function buildUniquePng(): Buffer {
  const color = [Math.floor(Math.random() * 256), Math.floor(Math.random() * 256), Math.floor(Math.random() * 256)]
  const row = Buffer.from([0, ...color, ...color])
  const raw = Buffer.concat([row, row])

  const chunk = (type: string, data: Buffer): Buffer => {
    const length = Buffer.alloc(4)
    length.writeUInt32BE(data.length)
    const body = Buffer.concat([Buffer.from(type, 'ascii'), data])
    const checksum = Buffer.alloc(4)
    checksum.writeUInt32BE(crc32(body))

    return Buffer.concat([length, body, checksum])
  }

  const header = Buffer.alloc(13)
  header.writeUInt32BE(2, 0)
  header.writeUInt32BE(2, 4)
  header.set([8, 2, 0, 0, 0], 8)

  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', header),
    chunk('IDAT', deflateSync(raw)),
    chunk('IEND', Buffer.alloc(0)),
  ])
}
