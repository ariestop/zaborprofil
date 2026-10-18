import { cleanup, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { SimpleEditor } from './simple-editor'

describe('SimpleEditor', () => {
  beforeEach(() => {
    // jsdom не реализует matchMedia, а редактор по нему выбирает мобильный тулбар.
    vi.stubGlobal('matchMedia', (query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addEventListener: () => undefined,
      removeEventListener: () => undefined,
      addListener: () => undefined,
      removeListener: () => undefined,
      dispatchEvent: () => false,
    }))
  })

  afterEach(() => {
    cleanup()
    vi.unstubAllGlobals()
  })

  it('does not offer formatting that the block sanitizer removes', () => {
    render(<SimpleEditor content="<p>Текст</p>" onChange={vi.fn()} />)

    const labels = screen.getAllByRole('button').map((button) => button.getAttribute('aria-label') ?? button.textContent ?? '')
    const text = labels.join(' | ')

    expect(text).not.toMatch(/strike|code|highlight|image|загрузить|медиатека/i)
    expect(text).toMatch(/superscript/i)
    expect(text).toMatch(/subscript/i)
  })

  it('keeps superscript and drops unsupported markup when content is loaded', async () => {
    const { container } = render(
      <SimpleEditor content={'<h1>Заголовок</h1><p>1 м<sup>2</sup> <mark>важно</mark> <s>старое</s></p><hr><img src="/uploads/media/a.png" alt="a">'} onChange={vi.fn()} />,
    )

    await waitFor(() => expect(container.querySelector('.simple-editor')).not.toBeNull())
    const editor = container.querySelector('.simple-editor') as HTMLElement

    expect(editor.querySelector('sup')?.textContent).toBe('2')
    expect(editor.querySelector('h1, mark, s, hr, img')).toBeNull()
    expect(editor.textContent).toContain('Заголовок')
    expect(editor.textContent).toContain('важно')
  })
})
