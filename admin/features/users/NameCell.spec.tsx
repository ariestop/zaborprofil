import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { NameCell } from './NameCell'

afterEach(cleanup)

describe('NameCell', () => {
  it('saves a trimmed name on Enter and skips saving when nothing changed', async () => {
    const onSave = vi.fn().mockResolvedValue(undefined)
    render(<NameCell name={null} email="a@example.test" onSave={onSave} />)
    const input = screen.getByLabelText('Имя пользователя a@example.test')

    fireEvent.blur(input)
    expect(onSave).not.toHaveBeenCalled()

    fireEvent.change(input, { target: { value: '  Игорь ' } })
    fireEvent.keyDown(input, { key: 'Enter' })
    fireEvent.blur(input)

    await waitFor(() => expect(onSave).toHaveBeenCalledWith('Игорь'))
  })

  it('returns to the saved name when saving fails', async () => {
    const onSave = vi.fn().mockRejectedValue(new Error('fail'))
    render(<NameCell name="Анна" email="a@example.test" onSave={onSave} />)
    const input = screen.getByLabelText('Имя пользователя a@example.test') as HTMLInputElement

    fireEvent.change(input, { target: { value: 'Борис' } })
    fireEvent.blur(input)

    await waitFor(() => expect(input.value).toBe('Анна'))
  })
})
