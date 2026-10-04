import { cleanup, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it } from 'vitest'
import { loginAs } from '../shared/testing/roles'
import { RouteGuard } from './RouteGuard'

afterEach(cleanup)

function renderGuard(routeKey: string) {
  return render(
    <MemoryRouter>
      <RouteGuard routeKey={routeKey}>
        <p>Содержимое раздела</p>
      </RouteGuard>
    </MemoryRouter>,
  )
}

describe('RouteGuard', () => {
  it('renders the section for a role that has the permission', () => {
    loginAs('ROLE_MANAGER')

    renderGuard('crm')

    expect(screen.getByText('Содержимое раздела')).toBeTruthy()
    expect(screen.queryByRole('alert')).toBeNull()
  })

  it('shows the 403 page instead of a section the role cannot open', () => {
    loginAs('ROLE_MANAGER')

    renderGuard('pages')

    expect(screen.queryByText('Содержимое раздела')).toBeNull()
    expect(screen.getByRole('alert').textContent).toContain('Нет доступа к этому разделу')
    expect(screen.getByRole('link', { name: 'На сводку' }).getAttribute('href')).toBe('/admin/dashboard')
  })

  it('keeps the system center closed for an editor and open for an administrator', () => {
    loginAs('ROLE_EDITOR')
    renderGuard('systemLogs')
    expect(screen.queryByText('Содержимое раздела')).toBeNull()
    cleanup()

    loginAs('ROLE_ADMIN')
    renderGuard('systemLogs')
    expect(screen.getByText('Содержимое раздела')).toBeTruthy()
  })

  it('denies access to an unknown route key', () => {
    loginAs('ROLE_ADMIN')

    renderGuard('unknown')

    expect(screen.getByRole('alert')).toBeTruthy()
  })
})
