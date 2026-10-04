import { describe, expect, it } from 'vitest'
import type { MediaAssetItem } from '../../../../types/api'
import { appendMediaItem, applyMediaSelection, canAppendMediaItem, findMediaFields } from './mediaFields'

const asset: MediaAssetItem = {
  id: '1',
  originalName: 'gate.jpg',
  filename: 'gate.jpg',
  publicPath: '/uploads/media/gate.jpg',
  mimeType: 'image/jpeg',
  size: 10,
  width: 100,
  height: 100,
  variants: [],
  alt: 'Ворота',
  title: 'Фото ворот',
  createdAt: '2026-01-01T00:00:00+00:00',
}

describe('block media fields', () => {
  it('finds top-level image fields and their alt companion', () => {
    const fields = findMediaFields({ title: 'T', image: '', imageAlt: '' }, 'hero.with-image')

    expect(fields).toHaveLength(1)
    expect(fields[0]).toMatchObject({ path: ['image'], altPath: ['imageAlt'], label: 'Изображение' })
  })

  it('finds images inside item lists with numbered labels', () => {
    const fields = findMediaFields({ items: [{ src: '/a.jpg', alt: '' }, { src: '/b.jpg', alt: 'b' }] }, 'gallery')

    expect(fields.map((field) => field.label)).toEqual(['Изображение 1', 'Изображение 2'])
    expect(fields[1]?.path).toEqual(['items', 1, 'src'])
    expect(fields[1]?.altPath).toEqual(['items', 1, 'alt'])
  })

  it('treats before/after strings as images only for before-after blocks', () => {
    expect(findMediaFields({ before: '/a.jpg', after: '' }, 'before-after').map((field) => field.label)).toEqual(['Фото «До»', 'Фото «После»'])
    expect(findMediaFields({ before: 'x', after: 'y' }, 'text')).toEqual([])
  })

  it('does not treat unrelated string fields as images', () => {
    expect(findMediaFields({ title: 'x', href: '/y', html: '<p>z</p>' }, 'cta')).toEqual([])
  })

  it('writes the selected path without mutating content and fills empty alt only', () => {
    const content = { items: [{ src: '', alt: '' }, { src: '', alt: 'Существующий alt' }] }
    const [first, second] = findMediaFields(content, 'gallery')

    const next = applyMediaSelection(content, first!, asset.publicPath, asset)
    expect(next.items).toEqual([{ src: asset.publicPath, alt: 'Ворота' }, { src: '', alt: 'Существующий alt' }])
    expect(content.items[0]?.src).toBe('')

    const kept = applyMediaSelection(content, second!, asset.publicPath, asset)
    expect((kept.items as Array<{ alt: string }>)[1]?.alt).toBe('Существующий alt')
  })

  it('appends items for gallery-like blocks', () => {
    expect(canAppendMediaItem('gallery')).toBe(true)
    expect(canAppendMediaItem('cta')).toBe(false)

    const next = appendMediaItem({ items: [{ src: '/a.jpg', alt: '' }] }, 'gallery', asset)
    expect(next.items).toEqual([{ src: '/a.jpg', alt: '' }, { src: asset.publicPath, alt: 'Ворота' }])

    const portfolio = appendMediaItem({}, 'portfolio', asset)
    expect(portfolio.items).toEqual([{ title: 'Фото ворот', image: asset.publicPath, href: '#' }])
  })
})
