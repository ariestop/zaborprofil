import { cleanup, render, screen, within } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { RevisionDiffView } from './RevisionDiffView'
import type { RevisionDiff } from './types'

afterEach(cleanup)

const side = { createdAt: '2026-10-01T10:00:00+00:00', createdBy: null, comment: null, action: null }

function makeDiff(overrides: Partial<RevisionDiff> = {}): RevisionDiff {
  return {
    pageId: 'page-1',
    from: { id: 'rev-1', version: 3, ...side },
    to: { id: 'current', version: null, ...side },
    hasChanges: true,
    summary: { fields: 1, seo: 1, settings: 0, blocksAdded: 1, blocksRemoved: 0, blocksChanged: 1 },
    fields: [{
      field: 'title',
      before: 'Забор из профнастила',
      after: 'Забор из штакетника',
      textDiff: [
        { op: 'equal', text: 'Забор из ' },
        { op: 'delete', text: 'профнастила' },
        { op: 'insert', text: 'штакетника' },
      ],
    }],
    seo: [{ field: 'isIndexable', before: true, after: false }],
    settings: [],
    blocks: [
      { status: 'added', type: 'hero', name: 'Главный экран', positionBefore: null, positionAfter: 0, changes: [] },
      { status: 'changed', type: 'text', name: 'Описание', positionBefore: 1, positionAfter: 1, changes: [{ field: 'content.text', before: 'a', after: 'b' }] },
      { status: 'unchanged', type: 'cta', name: 'Заявка', positionBefore: 2, positionAfter: 2, changes: [] },
    ],
    ...overrides,
  }
}

describe('RevisionDiffView', () => {
  it('renders word-level text diff and scalar changes', () => {
    const { container } = render(<RevisionDiffView diff={makeDiff()} />)

    expect(screen.getByText('Текущая версия (рабочая)', { exact: false })).toBeTruthy()
    expect(container.querySelector('del')?.textContent).toBe('профнастила')
    expect(container.querySelector('ins')?.textContent).toBe('штакетника')
    expect(screen.getByText('Индексация')).toBeTruthy()
    expect(screen.getByText('да')).toBeTruthy()
    expect(screen.getByText('нет')).toBeTruthy()
    expect(screen.getByTestId('diff-summary').textContent).toContain('+1 / −0 / ~1')
  })

  it('lists changed blocks and hides unchanged ones', () => {
    render(<RevisionDiffView diff={makeDiff()} />)

    const blocks = screen.getAllByTestId('diff-block')
    expect(blocks).toHaveLength(2)
    expect(within(blocks[0]).getByText('Добавлен')).toBeTruthy()
    expect(within(blocks[1]).getByText('Контент: text')).toBeTruthy()
    expect(screen.queryByText('Заявка')).toBeNull()
  })

  it('says there are no differences for identical revisions', () => {
    render(<RevisionDiffView diff={makeDiff({ hasChanges: false, fields: [], seo: [], blocks: [] })} />)

    expect(screen.getByText('Различий нет.')).toBeTruthy()
  })
})
