import { describe, expect, it } from 'vitest'
import { buildNotFoundUrl, buildRedirectsUrl } from './api'

describe('seo api urls', () => {
  it('builds the redirects list url and skips an empty query', () => {
    const url = buildRedirectsUrl({ q: '  ', status: 'all', sort: 'source', direction: 'asc', page: 1, perPage: 25 })

    expect(url).toBe('/admin/api/seo/redirects?page=1&perPage=25&sort=source&direction=asc&status=all')
  })

  it('encodes search text for redirects and the 404 journal', () => {
    expect(buildRedirectsUrl({ q: 'забор & ворота', status: 'active', sort: 'hits', direction: 'desc', page: 2, perPage: 10 }))
      .toContain('q=%D0%B7%D0%B0%D0%B1%D0%BE%D1%80+%26+%D0%B2%D0%BE%D1%80%D0%BE%D1%82%D0%B0')
    expect(buildNotFoundUrl({ q: '/old', sort: 'lastSeen', page: 3, perPage: 25 }))
      .toBe('/admin/api/seo/not-found?page=3&perPage=25&sort=lastSeen&q=%2Fold')
  })
})
