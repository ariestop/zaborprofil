import { describe, expect, it } from 'vitest'
import { ApiError } from './client'

describe('ApiError', () => {
  it('keeps the server error code next to the status-based code', () => {
    const error = new ApiError('Media asset is used on the site.', 409, { error: 'Media asset is used on the site.', code: 'MEDIA_IN_USE' })

    expect(error.code).toBe('UNKNOWN')
    expect(error.serverCode).toBe('MEDIA_IN_USE')
  })

  it('has no server code when the payload is not an error object', () => {
    expect(new ApiError('Bad gateway', 502, '<html>').serverCode).toBeNull()
    expect(new ApiError('Network error', 0, null).serverCode).toBeNull()
    expect(new ApiError('Odd', 422, { error: 'Odd', code: 42 }).serverCode).toBeNull()
  })
})
