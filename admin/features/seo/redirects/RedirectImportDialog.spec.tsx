import { cleanup, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../../shared/api/client'
import { renderWithProviders } from '../test-utils'
import { RedirectImportDialog } from './RedirectImportDialog'

const apiRequest = vi.fn()
const downloadTextFile = vi.fn()

vi.mock('../../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

vi.mock('../../../shared/lib/download', () => ({
  downloadTextFile: (...args: unknown[]) => downloadTextFile(...args),
}))

const report = {
  dryRun: true,
  totalRows: 3,
  created: 1,
  updated: 0,
  skipped: 0,
  failed: 2,
  errors: [
    { line: 3, source: '/self/', message: 'Редирект не может вести на тот же адрес, что и источник.' },
    { line: 4, source: '/admin/x', message: 'Адреса, начинающиеся с /admin, обрабатываются приложением напрямую.' },
  ],
  warnings: [{ line: 2, source: '/a/', message: 'Цепочка редиректов: /a/ → /b/ → /c/.' }],
  preview: [{ line: 2, source: '/a/', target: '/b/', status: 301, action: 'create' }],
}

beforeEach(() => {
  apiRequest.mockReset()
  downloadTextFile.mockReset()
})

afterEach(cleanup)

function paste(csv: string) {
  fireEvent.change(screen.getByLabelText('Содержимое CSV'), { target: { value: csv } })
}

describe('RedirectImportDialog', () => {
  it('keeps check and import disabled until CSV is provided', () => {
    renderWithProviders(<RedirectImportDialog open onClose={() => {}} />)

    expect((screen.getByRole('button', { name: 'Проверить' }) as HTMLButtonElement).disabled).toBe(true)
    expect((screen.getByRole('button', { name: 'Применить импорт' }) as HTMLButtonElement).disabled).toBe(true)
  })

  it('shows a dry-run report with errors and warnings without writing', async () => {
    apiRequest.mockResolvedValue(report)
    renderWithProviders(<RedirectImportDialog open onClose={() => {}} />)

    paste('/a/,/b/\n/self/,/self/\n/admin/x,/y/')
    fireEvent.click(screen.getByRole('button', { name: 'Проверить' }))

    const errors = await screen.findByLabelText('Ошибки импорта')
    expect(within(errors).getAllByRole('listitem')).toHaveLength(2)
    expect(within(errors).getByText(/строка 3/)).toBeTruthy()
    expect(within(screen.getByLabelText('Предупреждения импорта')).getByText(/Цепочка редиректов/)).toBeTruthy()
    expect(screen.getByText('Предпросмотр (ничего не записано)')).toBeTruthy()
    expect(apiRequest).toHaveBeenCalledWith('/admin/api/seo/redirects/import', {
      method: 'POST',
      body: { csv: '/a/,/b/\n/self/,/self/\n/admin/x,/y/', dryRun: true, updateExisting: false },
    })
  })

  it('downloads the error report as CSV', async () => {
    apiRequest.mockResolvedValue(report)
    renderWithProviders(<RedirectImportDialog open onClose={() => {}} />)
    paste('x,y')
    fireEvent.click(screen.getByRole('button', { name: 'Проверить' }))

    fireEvent.click(await screen.findByRole('button', { name: 'Скачать отчёт об ошибках' }))

    expect(downloadTextFile).toHaveBeenCalledTimes(1)
    const [filename, content] = downloadTextFile.mock.calls[0] as [string, string]
    expect(filename).toBe('redirects-import-errors.csv')
    expect(content).toContain('line,source,error')
    expect(content).toContain('3,/self/,')
  })

  it('applies the import after a successful check and reports the result', async () => {
    apiRequest
      .mockResolvedValueOnce(report)
      .mockResolvedValueOnce({ ...report, dryRun: false })
    renderWithProviders(<RedirectImportDialog open onClose={() => {}} />)
    paste('/a/,/b/')
    fireEvent.click(screen.getByRole('button', { name: 'Проверить' }))
    await screen.findByLabelText('Ошибки импорта')

    fireEvent.click(screen.getByRole('button', { name: 'Применить импорт' }))

    await waitFor(() => {
      expect(apiRequest).toHaveBeenLastCalledWith('/admin/api/seo/redirects/import', {
        method: 'POST',
        body: { csv: '/a/,/b/', dryRun: false, updateExisting: false },
      })
    })
    expect(await screen.findByText('Результат импорта')).toBeTruthy()
    expect(await screen.findByText('Импорт выполнен')).toBeTruthy()
  })

  it('resets the report when the CSV changes', async () => {
    apiRequest.mockResolvedValue(report)
    renderWithProviders(<RedirectImportDialog open onClose={() => {}} />)
    paste('/a/,/b/')
    fireEvent.click(screen.getByRole('button', { name: 'Проверить' }))
    await screen.findByLabelText('Ошибки импорта')

    paste('/a/,/c/')

    expect(screen.queryByLabelText('Ошибки импорта')).toBeNull()
    expect((screen.getByRole('button', { name: 'Применить импорт' }) as HTMLButtonElement).disabled).toBe(true)
  })

  it('reports a malformed file returned by the API', async () => {
    apiRequest.mockRejectedValue(new ApiError('В файле нет ни одной строки с редиректом.', 422, {
      error: 'В файле нет ни одной строки с редиректом.',
      code: 'VALIDATION',
    }))
    renderWithProviders(<RedirectImportDialog open onClose={() => {}} />)
    paste('# nothing')
    fireEvent.click(screen.getByRole('button', { name: 'Проверить' }))

    expect(await screen.findByText('CSV не прошёл проверку')).toBeTruthy()
    expect(await screen.findByText('В файле нет ни одной строки с редиректом.')).toBeTruthy()
  })
})
