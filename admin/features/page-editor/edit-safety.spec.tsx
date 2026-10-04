import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../shared/api/client'
import { clearDraft, draftDiffersFromServer, readDraft, writeDraft } from './draft-storage'
import { parseEditConflict } from './edit-conflict'
import { DraftRestoreBanner, EditConflictDialog, EditLockBanner } from './EditSafetyPanels'
import { emptyFormValues } from './form'

beforeEach(() => {
  const data = new Map<string, string>()
  Object.defineProperty(window, 'localStorage', {
    configurable: true,
    value: {
      getItem: (key: string) => data.get(key) ?? null,
      setItem: (key: string, value: string) => void data.set(key, String(value)),
      removeItem: (key: string) => void data.delete(key),
    },
  })
})

afterEach(cleanup)

describe('parseEditConflict', () => {
  it('recognises only 409 EDIT_CONFLICT responses', () => {
    expect(parseEditConflict(new ApiError('x', 409, { code: 'EDIT_CONFLICT', version: 'abc', updatedAt: '2026-10-04T10:00:00+00:00' }))).toEqual({
      serverVersion: 'abc',
      serverUpdatedAt: '2026-10-04T10:00:00+00:00',
    })
    expect(parseEditConflict(new ApiError('x', 409, { code: 'CONFLICT' }))).toBeNull()
    expect(parseEditConflict(new ApiError('x', 422, { code: 'EDIT_CONFLICT', version: 'abc' }))).toBeNull()
    expect(parseEditConflict(new Error('x'))).toBeNull()
  })
})

describe('draft storage', () => {
  it('round-trips, rejects broken payloads and clears drafts', () => {
    const values = emptyFormValues()
    writeDraft('p1', { baseVersion: 'v1', blocks: [], values })
    expect(readDraft('p1')?.baseVersion).toBe('v1')

    window.localStorage.setItem('zaborprofil:admin:page-draft:p2', '{broken')
    expect(readDraft('p2')).toBeNull()
    window.localStorage.setItem('zaborprofil:admin:page-draft:p3', JSON.stringify({ version: 99 }))
    expect(readDraft('p3')).toBeNull()

    clearDraft('p1')
    expect(readDraft('p1')).toBeNull()
  })

  it('compares drafts independently of key order', () => {
    const values = emptyFormValues()
    const reordered = Object.fromEntries(Object.entries(values).reverse()) as typeof values
    writeDraft('p1', { baseVersion: null, blocks: [], values: reordered })
    const draft = readDraft('p1')

    expect(draft).not.toBeNull()
    expect(draftDiffersFromServer(draft!, { blocks: [], values })).toBe(false)
    expect(draftDiffersFromServer(draft!, { blocks: [], values: { ...values, title: 'Другое' } })).toBe(true)
  })

  it('does not throw when storage is unavailable', () => {
    Object.defineProperty(window, 'localStorage', { configurable: true, value: undefined })

    expect(() => writeDraft('p1', { baseVersion: null, blocks: [], values: emptyFormValues() })).not.toThrow()
    expect(readDraft('p1')).toBeNull()
  })
})

describe('edit safety panels', () => {
  it('shows who edits the page and offers to take over', () => {
    const takeOver = vi.fn().mockResolvedValue(undefined)
    render(<EditLockBanner lock={{ locked: true, holderLabel: 'anna@example.test', holderIsSelf: false, since: null, takeOver }} />)

    expect(screen.getByTestId('edit-lock-banner').textContent).toContain('anna@example.test')
    fireEvent.click(screen.getByRole('button', { name: 'Редактировать здесь' }))
    expect(takeOver).toHaveBeenCalled()
  })

  it('renders nothing when the page is free', () => {
    render(<EditLockBanner lock={{ locked: false, holderLabel: null, holderIsSelf: false, since: null, takeOver: vi.fn() }} />)

    expect(screen.queryByTestId('edit-lock-banner')).toBeNull()
  })

  it('restores or discards a stored draft', () => {
    const onRestore = vi.fn()
    const onDiscard = vi.fn()
    render(<DraftRestoreBanner draft={{ savedAt: '2026-10-04T10:00:00+00:00', serverChanged: true }} onRestore={onRestore} onDiscard={onDiscard} />)

    expect(screen.getByTestId('draft-restore-banner').textContent).toContain('конфликт')
    fireEvent.click(screen.getByRole('button', { name: 'Восстановить' }))
    fireEvent.click(screen.getByRole('button', { name: 'Отбросить' }))
    expect(onRestore).toHaveBeenCalled()
    expect(onDiscard).toHaveBeenCalled()
  })

  it('offers three ways out of an edit conflict', () => {
    const handlers = { onOverwrite: vi.fn(), onReload: vi.fn(), onDismiss: vi.fn() }
    render(<EditConflictDialog conflict={{ serverVersion: 'abc', serverUpdatedAt: null }} {...handlers} />)

    expect(screen.getByText('Страница изменена другим пользователем')).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Перезаписать моими правками' }))
    fireEvent.click(screen.getByRole('button', { name: 'Загрузить с сервера' }))
    fireEvent.click(screen.getByRole('button', { name: 'Остаться в редакторе' }))
    expect(handlers.onOverwrite).toHaveBeenCalled()
    expect(handlers.onReload).toHaveBeenCalled()
    expect(handlers.onDismiss).toHaveBeenCalled()
  })
})
