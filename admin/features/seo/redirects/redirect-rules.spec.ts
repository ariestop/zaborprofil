import { describe, expect, it } from 'vitest'
import { ApiError } from '../../../shared/api/client'
import {
  buildImportErrorsCsv,
  decodeForDisplay,
  describeApiError,
  normalizeSourceInput,
  redirectFormSchema,
} from './redirect-rules'

const valid = { sourcePath: '/old-page/', targetPath: '/new-page/', statusCode: '301', isActive: true }

function messages(values: Record<string, unknown>): string[] {
  const result = redirectFormSchema.safeParse(values)

  return result.success ? [] : result.error.issues.map((issue) => issue.message)
}

describe('redirectFormSchema', () => {
  it('accepts a regular internal redirect and an external target', () => {
    expect(messages(valid)).toEqual([])
    expect(messages({ ...valid, targetPath: 'https://example.com/page/?a=1' })).toEqual([])
    expect(messages({ ...valid, sourcePath: 'без-слэша/' })).toEqual([])
  })

  it('rejects empty and malformed source URLs', () => {
    expect(messages({ ...valid, sourcePath: '  ' })).not.toEqual([])
    expect(messages({ ...valid, sourcePath: '/old/?a=1' })).toEqual([expect.stringContaining('параметров запроса')])
    expect(messages({ ...valid, sourcePath: 'https://zaborprofil.ru/old/' })).toEqual([expect.stringContaining('без домена')])
    expect(messages({ ...valid, sourcePath: '/a//b/' })).toEqual([expect.stringContaining('Двойной слэш')])
    expect(messages({ ...valid, sourcePath: '/admin/pages' })).toEqual([expect.stringContaining('Служебные разделы')])
  })

  it('rejects dangerous or self-referencing targets', () => {
    expect(messages({ ...valid, targetPath: '//evil.example/' })).toEqual([expect.stringContaining('//')])
    expect(messages({ ...valid, targetPath: 'javascript:alert(1)' })).toEqual([expect.stringContaining('http://')])
    expect(messages({ ...valid, targetPath: '/admin/dashboard' })).toEqual([expect.stringContaining('админ-панель')])
    expect(messages({ ...valid, targetPath: '/old-page/?x=1' })).toEqual([expect.stringContaining('совпадают')])
  })

  it('rejects unsupported status codes', () => {
    expect(messages({ ...valid, statusCode: '200' })).toEqual(['Недопустимый код'])
  })
})

describe('helpers', () => {
  it('normalizes source input', () => {
    expect(normalizeSourceInput(' old/ ')).toBe('/old/')
    expect(normalizeSourceInput('/old/')).toBe('/old/')
    expect(normalizeSourceInput('')).toBe('')
  })

  it('decodes percent-encoded paths for display and keeps broken input', () => {
    expect(decodeForDisplay('/%D0%B7%D0%B0%D0%B1%D0%BE%D1%80/')).toBe('/забор/')
    expect(decodeForDisplay('/%E0%A4%A/')).toBe('/%E0%A4%A/')
  })

  it('builds a safe error report CSV', () => {
    const csv = buildImportErrorsCsv([
      { line: 3, source: '/a,b/', message: 'Ошибка "кавычки"' },
      { line: 4, source: '=HYPERLINK("x")', message: 'x' },
    ])

    expect(csv).toBe('line,source,error\n3,"/a,b/","Ошибка ""кавычки"""\n4,"\'=HYPERLINK(""x"")",x\n')
  })

  it('extracts API error text with fallback', () => {
    expect(describeApiError(new ApiError('x', 422, { error: 'Цикл', code: 'REDIRECT_LOOP' }), 'fallback')).toBe('Цикл')
    expect(describeApiError(new ApiError('x', 500, 'oops'), 'fallback')).toBe('fallback')
    expect(describeApiError(new Error('boom'), 'fallback')).toBe('fallback')
  })
})
