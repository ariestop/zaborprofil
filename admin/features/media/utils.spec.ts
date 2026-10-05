import { describe, expect, it } from 'vitest'
import { ApiError } from '../../shared/api/client'
import type { MediaAssetItem } from '../../types/api'
import { describeMediaError, formatFileSize, formatUsageCount, thumbnailPath, validateUploadFile } from './utils'

function asset(overrides: Partial<MediaAssetItem> = {}): MediaAssetItem {
  return {
    id: '1',
    originalName: 'fence.jpg',
    filename: 'fence.jpg',
    publicPath: '/uploads/media/fence.jpg',
    mimeType: 'image/jpeg',
    size: 1000,
    width: 2000,
    height: 1000,
    variants: [],
    alt: null,
    title: null,
    createdAt: '2026-01-01T00:00:00+00:00',
    ...overrides,
  }
}

describe('media utils', () => {
  it('formats file sizes in Russian units', () => {
    expect(formatFileSize(512)).toBe('512 Б')
    expect(formatFileSize(1536)).toBe('1,5 КБ')
    expect(formatFileSize(5 * 1024 * 1024)).toBe('5,0 МБ')
  })

  it('validates files before upload', () => {
    expect(validateUploadFile({ name: 'a.png', size: 100, type: 'image/png' })).toBeNull()
    expect(validateUploadFile({ name: 'a.svg', size: 100, type: 'image/svg+xml' })).toContain('Недопустимый тип')
    expect(validateUploadFile({ name: 'a.png', size: 0, type: 'image/png' })).toBe('Файл пустой.')
    expect(validateUploadFile({ name: 'a.png', size: 11 * 1024 * 1024, type: 'image/png' })).toContain('Файл больше')
  })

  it('prefers a webp variant of at least 320px as thumbnail', () => {
    const variants = [
      { type: 'webp', publicPath: '/v/320.webp', width: 320, height: 160, mimeType: 'image/webp', size: 1 },
      { type: 'webp', publicPath: '/v/768.webp', width: 768, height: 384, mimeType: 'image/webp', size: 1 },
    ]

    expect(thumbnailPath(asset({ variants }))).toBe('/v/320.webp')
    expect(thumbnailPath(asset())).toBe('/uploads/media/fence.jpg')
    expect(thumbnailPath(asset({ mimeType: 'application/pdf' }))).toBeNull()
  })

  it('translates known server errors and falls back to the message', () => {
    expect(describeMediaError(new ApiError('Uploaded file extension is not allowed.', 422, null))).toContain('Недопустимый тип файла')
    expect(describeMediaError(new ApiError('Access denied.', 403, null))).toBe('Недостаточно прав для этого действия.')
    expect(describeMediaError(new ApiError('too big', 413, null))).toContain('лимита сервера')
    expect(describeMediaError(new ApiError('Something custom', 422, null))).toBe('Something custom')
    expect(describeMediaError(new Error('boom'))).toBe('Не удалось выполнить операцию.')
  })
})

describe('formatUsageCount', () => {
  it('declines "место" for the "используется в …" phrase', () => {
    expect([1, 2, 5, 11, 21, 22].map(formatUsageCount)).toEqual(['1 месте', '2 местах', '5 местах', '11 местах', '21 месте', '22 местах'])
  })
})
