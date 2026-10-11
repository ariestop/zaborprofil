import { act, cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { notifySessionExpired } from '../shared/api/session'
import { ADMIN_LOGIN_URL, SessionExpiredNotice } from './SessionExpiredNotice'

describe('SessionExpiredNotice', () => {
  afterEach(() => {
    cleanup()
  })

  it('появляется после сигнала об истёкшей сессии и ведёт на вход', () => {
    render(<SessionExpiredNotice />)
    expect(screen.queryByRole('alert')).toBeNull()

    act(() => notifySessionExpired())

    expect(screen.getByRole('alert').textContent).toContain('Сессия истекла')
    expect(screen.getByRole('link', { name: 'Войти снова' }).getAttribute('href')).toBe(ADMIN_LOGIN_URL)
  })

  it('закрывается и снова появляется при следующем сигнале', () => {
    render(<SessionExpiredNotice />)
    act(() => notifySessionExpired())

    fireEvent.click(screen.getByRole('button', { name: 'Закрыть' }))
    expect(screen.queryByRole('alert')).toBeNull()

    act(() => notifySessionExpired())
    expect(screen.getByRole('alert')).not.toBeNull()
  })

  it('отписывается при размонтировании', () => {
    const { unmount } = render(<SessionExpiredNotice />)
    unmount()

    expect(() => act(() => notifySessionExpired())).not.toThrow()
  })
})
