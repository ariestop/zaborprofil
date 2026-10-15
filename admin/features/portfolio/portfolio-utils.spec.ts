import { describe, expect, it } from 'vitest'
import type { PortfolioMedia } from '../../entities/portfolio/api'
import {
  coverIndex,
  draftErrors,
  draftToPayload,
  emptyProjectDraft,
  mediaCountLabel,
  moveMedia,
  newMedia,
  parseVideoLink,
  projectsCountLabel,
  setCover,
} from './portfolio-utils'

const photo = (src: string, isCover = false): PortfolioMedia => ({ kind: 'photo', src, poster: null, caption: null, isCover })

describe('portfolio utils', () => {
  it('turns ordinary Rutube and VK links into player addresses like the server does', () => {
    expect(parseVideoLink('https://rutube.ru/video/0123456789ABCDEF0123456789abcdef/')).toEqual({
      provider: 'rutube',
      embedUrl: 'https://rutube.ru/play/embed/0123456789abcdef0123456789abcdef/',
    })
    expect(parseVideoLink('rutube.ru/video/private/0123456789abcdef0123456789abcdef/?p=Ab_1')?.embedUrl).toBe(
      'https://rutube.ru/play/embed/0123456789abcdef0123456789abcdef/?p=Ab_1',
    )
    expect(parseVideoLink('https://vk.com/video-123_456')?.embedUrl).toBe('https://vkvideo.ru/video_ext.php?oid=-123&id=456&hd=2')
    expect(parseVideoLink('https://m.vkvideo.ru/clip-1_2?hash=ab12')?.embedUrl).toBe('https://vkvideo.ru/video_ext.php?oid=-1&id=2&hash=ab12&hd=2')
    expect(parseVideoLink('https://vk.com/club1?z=video-5_6%2Fpl_cat')?.embedUrl).toBe('https://vkvideo.ru/video_ext.php?oid=-5&id=6&hd=2')
    expect(parseVideoLink('https://vkvideo.ru/video_ext.php?oid=7&id=8&hash=ff')?.embedUrl).toBe('https://vkvideo.ru/video_ext.php?oid=7&id=8&hash=ff&hd=2')
    expect(parseVideoLink('https://youtube.com/watch?v=1')).toBeNull()
    expect(parseVideoLink('https://rutube.ru/video/short/')).toBeNull()
    expect(parseVideoLink('')).toBeNull()
  })

  it('takes the marked photo as cover, otherwise the first photo, never a video', () => {
    const video: PortfolioMedia = { kind: 'video', src: '/uploads/media/a.mp4', poster: null, caption: null, isCover: false }
    expect(coverIndex([video, photo('/a.jpg'), photo('/b.jpg')])).toBe(1)
    expect(coverIndex([photo('/a.jpg'), photo('/b.jpg', true)])).toBe(1)
    expect(coverIndex([video])).toBe(-1)
    expect(setCover([photo('/a.jpg', true), video, photo('/b.jpg')], 2).map((item) => item.isCover)).toEqual([false, false, true])
  })

  it('moves items and ignores moves outside the list', () => {
    expect(moveMedia(['a', 'b', 'c', 'd'], 3, 0)).toEqual(['d', 'a', 'b', 'c'])
    expect(moveMedia(['a', 'b'], 0, 5)).toEqual(['a', 'b'])
  })

  it('builds the payload: slug from the title, trimmed fields, empty specs dropped, client keys not sent', () => {
    const draft = {
      ...emptyProjectDraft('cat-1'),
      title: ' Забор в Энгельсе ',
      place: '  ',
      year: '2025',
      description: '<p></p>',
      specs: [{ label: 'Длина', value: ' 42 м ' }, { label: ' ', value: '' }],
      media: [{ ...newMedia('photo', '/uploads/media/a.jpg'), caption: '  ' }, { ...newMedia('embed', 'https://rutube.ru/play/embed/x/'), poster: '/p.jpg' }],
    }
    const payload = draftToPayload(draft)

    expect(payload).toMatchObject({ categoryId: 'cat-1', title: 'Забор в Энгельсе', slug: 'zabor-v-engelse', place: null, year: 2025, description: '' })
    expect(payload.specs).toEqual([{ label: 'Длина', value: '42 м' }])
    expect(payload.media).toEqual([
      { kind: 'photo', src: '/uploads/media/a.jpg', poster: null, caption: null, isCover: false },
      { kind: 'embed', src: 'https://rutube.ru/play/embed/x/', poster: '/p.jpg', caption: null, isCover: false },
    ])
  })

  it('reports form errors before sending', () => {
    expect(draftErrors({ ...emptyProjectDraft(), year: '1800' })).toEqual({
      categoryId: 'Выберите раздел портфолио',
      title: 'Укажите название работы',
      year: 'Год — число от 1990 до 2100',
    })
    expect(draftErrors({ ...emptyProjectDraft('c'), title: 'Забор', slug: 'Bad Slug' }).slug).toContain('латиница')
    expect(draftErrors({ ...emptyProjectDraft('c'), title: 'Забор' })).toEqual({})
  })

  it('pluralizes counters', () => {
    expect(projectsCountLabel(1)).toBe('1 работа')
    expect(projectsCountLabel(3)).toBe('3 работы')
    expect(projectsCountLabel(11)).toBe('11 работ')
    expect(mediaCountLabel(6, 2)).toBe('6 фото · 2 видео')
    expect(mediaCountLabel(3, 0)).toBe('3 фото')
  })
})
