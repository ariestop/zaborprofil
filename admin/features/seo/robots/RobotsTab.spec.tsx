import { cleanup, fireEvent, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '../test-utils'
import { RobotsTab } from './RobotsTab'

const apiRequest = vi.fn()

vi.mock('../../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

const defaultBody = 'User-agent: *\nAllow: /\nDisallow: /admin/\n'

const settings = {
  body: '',
  effectiveBody: 'User-agent: *\nDisallow: /\n',
  defaultBody,
  environment: 'staging',
  overriddenByEnvironment: true,
}

function previewFor(body: string) {
  const invalid = body.includes('broken')

  return {
    normalizedBody: body.trim() === '' ? null : `${body.trim()}\n`,
    effectiveBody: 'User-agent: *\nDisallow: /\n',
    usesDefault: body.trim() === '',
    overriddenByEnvironment: true,
    valid: !invalid,
    issues: invalid
      ? [{ severity: 'error', line: 2, message: 'Ожидается формат «Директива: значение».' }]
      : [{ severity: 'warning', line: null, message: 'Не указан Sitemap.' }],
  }
}

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockImplementation((url: string, options?: { method?: string, body?: { body: string | null } }) => {
    if (url === '/admin/api/seo/robots/preview') {
      return Promise.resolve(previewFor(options?.body?.body ?? ''))
    }
    if (url === '/admin/api/seo/robots' && options?.method === 'PUT') {
      return Promise.resolve({ ...settings, body: options.body?.body ?? '' })
    }
    return Promise.resolve(settings)
  })
})

afterEach(cleanup)

describe('RobotsTab', () => {
  it('shows the environment override notice and the default-body preview', async () => {
    renderWithProviders(<RobotsTab />)

    expect(await screen.findByRole('note')).toBeTruthy()
    expect((await screen.findByRole('note')).textContent).toContain('staging')
    await waitFor(() => expect(screen.getByTestId('robots-preview').textContent).toBe(defaultBody))
    expect(screen.getByText('Содержимое пустое — используется стандартный файл.')).toBeTruthy()
  })

  it('validates edits on the server, shows issues with line numbers and blocks saving on errors', async () => {
    renderWithProviders(<RobotsTab />)
    const editor = await screen.findByLabelText('Содержимое robots.txt')

    fireEvent.change(editor, { target: { value: 'User-agent: *\nbroken line' } })

    await waitFor(() => expect(screen.getByLabelText('Замечания к robots.txt').textContent).toContain('строка 2'))
    await waitFor(() => expect((screen.getByRole('button', { name: 'Сохранить' }) as HTMLButtonElement).disabled).toBe(true))
  })

  it('saves valid content', async () => {
    renderWithProviders(<RobotsTab />)
    const editor = await screen.findByLabelText('Содержимое robots.txt')

    fireEvent.change(editor, { target: { value: 'User-agent: *\nDisallow: /private/' } })
    await waitFor(() => expect((screen.getByRole('button', { name: 'Сохранить' }) as HTMLButtonElement).disabled).toBe(false))
    fireEvent.click(screen.getByRole('button', { name: 'Сохранить' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenCalledWith('/admin/api/seo/robots', {
        method: 'PUT',
        body: { body: 'User-agent: *\nDisallow: /private/' },
      })
    })
    expect(await screen.findByText('robots.txt сохранён')).toBeTruthy()
  })

  it('inserts the default template into the editor', async () => {
    renderWithProviders(<RobotsTab />)
    const editor = await screen.findByLabelText('Содержимое robots.txt') as HTMLTextAreaElement

    fireEvent.click(screen.getByRole('button', { name: 'Подставить стандартный шаблон' }))

    expect(editor.value).toBe(defaultBody)
  })
})
