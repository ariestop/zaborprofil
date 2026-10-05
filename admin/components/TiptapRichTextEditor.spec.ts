import { createElement } from 'react'
import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import TiptapRichTextEditor from './TiptapRichTextEditor'

const simpleEditorMock = vi.fn((props: { content: string, onChange: (value: string) => void }) => (
  createElement('div', { 'data-testid': 'simple-editor', 'data-content': props.content })
))

vi.mock('./tiptap-templates/tiptap-templates/simple/simple-editor', () => ({
  SimpleEditor: (props: { content: string, onChange: (value: string) => void }) => simpleEditorMock(props),
}))

describe('TiptapRichTextEditor', () => {
  it('passes model value and onChange to SimpleEditor template', () => {
    const onChange = vi.fn()
    render(createElement(TiptapRichTextEditor, { modelValue: '<p>text</p>', onChange }))

    expect(simpleEditorMock).toHaveBeenCalledWith({ content: '<p>text</p>', onChange })
    expect(screen.getByTestId('simple-editor').getAttribute('data-content')).toBe('<p>text</p>')
  })
})
