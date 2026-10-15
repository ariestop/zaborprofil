import { cleanup, fireEvent, screen } from '@testing-library/react'
import { useState } from 'react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { PortfolioOverview, PortfolioProjectSummary } from '../../entities/portfolio/api'
import { renderWithProviders } from '../seo/test-utils'
import { PortfolioBlockField } from './PortfolioBlockField'
import { projectsForBlock } from './portfolio-utils'

const apiRequest = vi.fn()

vi.mock('../../shared/api/client', async (importOriginal) => {
  const original = await importOriginal<typeof import('../../shared/api/client')>()

  return { ...original, apiRequest: (...args: unknown[]) => apiRequest(...args) }
})

function project(id: string, categoryId: string, publishedAt: string | null, status: 'published' | 'draft' = 'published'): PortfolioProjectSummary {
  return {
    id, categoryId, slug: id, title: `Работа ${id}`, place: null, year: 2025, summary: null, specs: [], status, sortOrder: 10,
    seoTitle: null, seoDescription: null, path: `/portfolio/${categoryId}/${id}/`, cover: null, photoCount: 1, videoCount: 0, publishedAt, updatedAt: '',
  }
}

const overview: PortfolioOverview = {
  categories: [
    { id: 'c1', slug: 'jaluzi', title: 'Жалюзи', shortTitle: null, description: null, sortOrder: 10, path: '/portfolio/jaluzi/', updatedAt: '' },
    { id: 'c2', slug: 'profnastil', title: 'Профнастил', shortTitle: null, description: null, sortOrder: 20, path: '/portfolio/profnastil/', updatedAt: '' },
  ],
  projects: [
    project('a', 'c1', '2026-01-01T00:00:00+00:00'),
    project('b', 'c1', '2026-03-01T00:00:00+00:00'),
    project('c', 'c2', '2026-02-01T00:00:00+00:00'),
    project('d', 'c2', null, 'draft'),
  ],
}

beforeEach(() => {
  apiRequest.mockReset()
  apiRequest.mockResolvedValue(overview)
})

afterEach(cleanup)

function Harness({ initial, onChange }: { initial: Record<string, unknown>; onChange: (content: Record<string, unknown>) => void }) {
  const [content, setContent] = useState(initial)

  return (
    <PortfolioBlockField
      content={content}
      onChange={(patch) => {
        const next = { ...content, ...patch }
        setContent(next)
        onChange(next)
      }}
    />
  )
}

describe('projectsForBlock', () => {
  it('keeps the chosen order and skips drafts, otherwise takes the newest of a section', () => {
    expect(projectsForBlock(overview, { projectIds: ['c', 'd', 'a'], category: '', limit: 6 }).map((p) => p.id)).toEqual(['c', 'a'])
    expect(projectsForBlock(overview, { projectIds: [], category: '', limit: 2 }).map((p) => p.id)).toEqual(['b', 'c'])
    expect(projectsForBlock(overview, { projectIds: [], category: 'profnastil', limit: 6 }).map((p) => p.id)).toEqual(['c'])
    expect(projectsForBlock(overview, { projectIds: [], category: 'missing', limit: 6 })).toEqual([])
  })
})

describe('PortfolioBlockField', () => {
  it('adds chosen works, reorders and removes them', async () => {
    const onChange = vi.fn()
    renderWithProviders(<Harness initial={{ projectIds: ['a'], category: '', limit: 6 }} onChange={onChange} />)

    const add = await screen.findByLabelText('Добавить работу')
    expect(screen.queryByRole('option', { name: 'Работа d' })).toBeNull()
    fireEvent.change(add, { target: { value: 'c' } })
    expect(onChange).toHaveBeenLastCalledWith(expect.objectContaining({ projectIds: ['a', 'c'] }))

    fireEvent.click(screen.getByRole('button', { name: 'Выше: Работа c' }))
    expect(onChange).toHaveBeenLastCalledWith(expect.objectContaining({ projectIds: ['c', 'a'] }))

    fireEvent.click(screen.getByRole('button', { name: 'Убрать: Работа a' }))
    expect(onChange).toHaveBeenLastCalledWith(expect.objectContaining({ projectIds: ['c'] }))
    expect(screen.getByText(/На сайте: 1 работа/)).toBeTruthy()
  })

  it('switches to newest works of a section and clears the choice', async () => {
    const onChange = vi.fn()
    renderWithProviders(<Harness initial={{ projectIds: ['a'], category: '', limit: 6 }} onChange={onChange} />)
    await screen.findByLabelText('Добавить работу')

    fireEvent.click(screen.getByRole('radio', { name: /Новые работы/ }))
    expect(onChange).toHaveBeenLastCalledWith(expect.objectContaining({ projectIds: [] }))

    fireEvent.change(screen.getByLabelText('Раздел'), { target: { value: 'profnastil' } })
    fireEvent.change(screen.getByLabelText('Сколько'), { target: { value: '40' } })
    expect(onChange).toHaveBeenLastCalledWith(expect.objectContaining({ category: 'profnastil', limit: 24 }))
  })
})
