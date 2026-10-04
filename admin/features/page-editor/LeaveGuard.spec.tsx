import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { createMemoryRouter, Link, RouterProvider } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { LeaveGuard } from './LeaveGuard'
import { stubRequestWithoutSignal } from './test-utils'

beforeEach(stubRequestWithoutSignal)

afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
})

function renderGuard(when: boolean, isAllowed?: (location: { pathname: string }) => boolean) {
  const router = createMemoryRouter(
    [
      {
        path: '/a',
        element: (
          <div>
            <LeaveGuard when={when} isAllowed={isAllowed} />
            <Link to="/b">go-b</Link>
            <Link to="/a/seo">go-seo</Link>
          </div>
        ),
      },
      { path: '/a/seo', element: <p>seo-screen</p> },
      { path: '/b', element: <p>screen-b</p> },
    ],
    { initialEntries: ['/a'] },
  )

  render(<RouterProvider router={router} />)
  return router
}

describe('LeaveGuard', () => {
  it('lets navigation through when there are no unsaved changes', async () => {
    const router = renderGuard(false)

    fireEvent.click(screen.getByText('go-b'))

    expect(await screen.findByText('screen-b')).toBeTruthy()
    expect(router.state.location.pathname).toBe('/b')
  })

  it('asks for confirmation and stays on the page after "Остаться"', async () => {
    const router = renderGuard(true)

    fireEvent.click(screen.getByText('go-b'))
    expect(await screen.findByText('Есть несохранённые изменения')).toBeTruthy()
    expect(router.state.location.pathname).toBe('/a')

    fireEvent.click(screen.getByText('Остаться'))
    expect(router.state.location.pathname).toBe('/a')
  })

  it('leaves after confirmation', async () => {
    renderGuard(true)

    fireEvent.click(screen.getByText('go-b'))
    fireEvent.click(await screen.findByText('Уйти без сохранения'))

    expect(await screen.findByText('screen-b')).toBeTruthy()
  })

  it('does not warn for navigations the caller explicitly allows (tab switch)', async () => {
    renderGuard(true, (location) => location.pathname.startsWith('/a'))

    fireEvent.click(screen.getByText('go-seo'))

    expect(await screen.findByText('seo-screen')).toBeTruthy()
    expect(screen.queryByText('Есть несохранённые изменения')).toBeNull()
  })

  it('registers beforeunload only while there are unsaved changes', () => {
    renderGuard(true)

    const event = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(event)

    expect(event.defaultPrevented).toBe(true)
  })
})
