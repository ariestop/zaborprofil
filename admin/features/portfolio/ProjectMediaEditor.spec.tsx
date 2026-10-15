import { cleanup, fireEvent, screen } from '@testing-library/react'
import { useState } from 'react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { PortfolioMedia } from '../../entities/portfolio/api'
import { renderWithProviders } from '../seo/test-utils'
import { ProjectMediaEditor } from './ProjectMediaEditor'

vi.mock('../media/MediaPickerDialog', () => ({ MediaPickerDialog: () => null }))

afterEach(cleanup)

const initial: PortfolioMedia[] = [
  { id: 'a', kind: 'photo', src: '/uploads/media/a.jpg', poster: null, caption: null, isCover: false },
  { id: 'v', kind: 'video', src: '/uploads/media/v.mp4', poster: null, caption: null, isCover: false },
  { id: 'b', kind: 'photo', src: '/uploads/media/b.jpg', poster: null, caption: null, isCover: false },
]

function Harness({ onChange }: { onChange: (media: PortfolioMedia[]) => void }) {
  const [media, setMedia] = useState(initial)

  return (
    <ProjectMediaEditor
      media={media}
      onChange={(next) => {
        setMedia(next)
        onChange(next)
      }}
    />
  )
}

const srcs = () => screen.getAllByTestId('portfolio-media-tile').map((tile) => tile.querySelector('img,video')?.getAttribute('src') ?? 'embed')

describe('ProjectMediaEditor', () => {
  it('marks the first photo as cover until another one is starred', () => {
    const onChange = vi.fn()
    renderWithProviders(<Harness onChange={onChange} />)

    expect(screen.getAllByText('★ Главное фото')).toHaveLength(1)
    expect(screen.getByRole('button', { name: 'Это главное фото' })).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Сделать главным фото' }))

    expect(onChange.mock.lastCall?.[0].map((item: PortfolioMedia) => item.isCover)).toEqual([false, false, true])
  })

  it('moves tiles with arrows, keeps captions and removes items', () => {
    renderWithProviders(<Harness onChange={() => undefined} />)

    fireEvent.click(screen.getByRole('button', { name: 'Сдвинуть вправо: Фото 1 из 3' }))
    expect(srcs()).toEqual(['/uploads/media/v.mp4#t=0.5', '/uploads/media/a.jpg', '/uploads/media/b.jpg'])

    const caption = screen.getByLabelText('Подпись: Фото 2 из 3')
    fireEvent.change(caption, { target: { value: 'Вид с улицы' } })
    expect((screen.getByLabelText('Подпись: Фото 2 из 3') as HTMLInputElement).value).toBe('Вид с улицы')

    fireEvent.click(screen.getByRole('button', { name: 'Убрать: Видео 1 из 3' }))
    expect(srcs()).toEqual(['/uploads/media/a.jpg', '/uploads/media/b.jpg'])
  })

  it('adds a Rutube link as embed and rejects other sites', () => {
    const onChange = vi.fn()
    renderWithProviders(<Harness onChange={onChange} />)
    const input = screen.getByLabelText('Видео с VK или Rutube')

    fireEvent.change(input, { target: { value: 'https://youtube.com/watch?v=1' } })
    fireEvent.click(screen.getByRole('button', { name: 'Добавить видео' }))
    expect(screen.getByRole('alert').textContent).toContain('VK или Rutube')

    fireEvent.change(input, { target: { value: 'https://rutube.ru/video/0123456789abcdef0123456789abcdef/' } })
    expect(screen.getByText(/Распознано: Rutube/)).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: 'Добавить видео' }))

    const added = onChange.mock.lastCall?.[0] as PortfolioMedia[]
    expect(added).toHaveLength(4)
    expect(added[3]).toMatchObject({ kind: 'embed', src: 'https://rutube.ru/play/embed/0123456789abcdef0123456789abcdef/' })
    expect(screen.getAllByTestId('portfolio-media-tile')).toHaveLength(4)
  })
})
