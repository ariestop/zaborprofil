import type { MediaAssetItem } from '../../../../types/api'

export type PathSegment = string | number

export interface MediaField {
  path: PathSegment[]
  altPath: PathSegment[] | null
  label: string
  value: string
}

const IMAGE_KEYS = ['image', 'src', 'imageUrl', 'poster', 'backgroundImage'] as const
const BEFORE_AFTER_TYPES = new Set(['before-after', 'before_after'])

const ITEM_TEMPLATES: Record<string, (asset: MediaAssetItem) => Record<string, unknown>> = {
  gallery: (asset) => ({ src: asset.publicPath, alt: asset.alt ?? '' }),
  'works-gallery': (asset) => ({ src: asset.publicPath, alt: asset.alt ?? '' }),
  slider: (asset) => ({ src: asset.publicPath, alt: asset.alt ?? '', title: '', text: '', buttonLabel: '', buttonHref: '' }),
  portfolio: (asset) => ({ title: asset.title ?? asset.originalName, image: asset.publicPath, href: '#' }),
  'fence-types': (asset) => ({ title: asset.title ?? asset.originalName, text: '', image: asset.publicPath }),
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function altPathFor(owner: PathSegment[], key: string, record: Record<string, unknown>): PathSegment[] | null {
  if (key === 'src') {
    return [...owner, 'alt']
  }

  if (key === 'image') {
    if ('imageAlt' in record) return [...owner, 'imageAlt']
    if ('alt' in record) return [...owner, 'alt']
  }

  return null
}

function fieldLabel(owner: PathSegment[], key: string, blockType: string): string {
  if (BEFORE_AFTER_TYPES.has(blockType)) {
    return key === 'before' ? 'Фото «До»' : 'Фото «После»'
  }

  const index = owner.findLast((segment): segment is number => typeof segment === 'number')
  const base = key === 'poster' ? 'Постер' : key === 'backgroundImage' ? 'Фоновое изображение' : 'Изображение'

  return index === undefined ? base : `${base} ${index + 1}`
}

function collect(node: unknown, owner: PathSegment[], blockType: string, fields: MediaField[]): void {
  if (Array.isArray(node)) {
    node.forEach((child, index) => collect(child, [...owner, index], blockType, fields))
    return
  }

  if (!isRecord(node)) {
    return
  }

  const keys: readonly string[] = BEFORE_AFTER_TYPES.has(blockType) && owner.length === 0 ? ['before', 'after'] : IMAGE_KEYS

  for (const [key, value] of Object.entries(node)) {
    if (keys.includes(key) && typeof value === 'string') {
      fields.push({
        path: [...owner, key],
        altPath: altPathFor(owner, key, node),
        label: fieldLabel(owner, key, blockType),
        value,
      })
      continue
    }

    if (typeof value === 'object' && value !== null) {
      collect(value, [...owner, key], blockType, fields)
    }
  }
}

export function findMediaFields(content: Record<string, unknown>, blockType: string): MediaField[] {
  const fields: MediaField[] = []
  collect(content, [], blockType, fields)

  return fields
}

export function getIn(root: unknown, path: PathSegment[]): unknown {
  let current: unknown = root
  for (const segment of path) {
    if (current === null || typeof current !== 'object') {
      return undefined
    }
    current = (current as Record<PathSegment, unknown>)[segment]
  }

  return current
}

export function setIn(root: Record<string, unknown>, path: PathSegment[], value: unknown): Record<string, unknown> {
  const [head, ...rest] = path
  if (head === undefined) {
    return root
  }

  if (rest.length === 0) {
    return { ...root, [head]: value }
  }

  const child = root[head as string]
  if (Array.isArray(child)) {
    const index = rest[0] as number
    const nextChild = [...child]
    const item = child[index]
    nextChild[index] = rest.length === 1 ? value : setIn(isRecord(item) ? item : {}, rest.slice(1), value)
    return { ...root, [head]: nextChild }
  }

  return { ...root, [head]: setIn(isRecord(child) ? child : {}, rest, value) }
}

export function applyMediaSelection(
  content: Record<string, unknown>,
  field: MediaField,
  value: string,
  asset?: MediaAssetItem,
): Record<string, unknown> {
  let next = setIn(content, field.path, value)

  if (asset !== undefined && field.altPath !== null && asset.alt !== null) {
    const currentAlt = getIn(next, field.altPath)
    if (typeof currentAlt !== 'string' || currentAlt.trim() === '') {
      next = setIn(next, field.altPath, asset.alt)
    }
  }

  return next
}

export function canAppendMediaItem(blockType: string): boolean {
  return blockType in ITEM_TEMPLATES
}

export function appendMediaItem(content: Record<string, unknown>, blockType: string, asset: MediaAssetItem): Record<string, unknown> {
  const template = ITEM_TEMPLATES[blockType]
  if (template === undefined) {
    return content
  }

  const items = Array.isArray(content.items) ? content.items : []

  return { ...content, items: [...items, template(asset)] }
}
