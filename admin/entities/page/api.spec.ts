import { describe, expect, it } from 'vitest'
import { createBlock, duplicateBlock, normalizePageBlocks, reorderBlocks, validatePageBlocks } from '../../modules/page-builder/utils/pageBlocks'

describe('page builder utils', () => {
  it('creates blocks with defaults', () => {
    const block = createBlock('hero.classic', 0)
    expect(block.type).toBe('hero.classic')
    expect(block.enabled).toBe(true)
  })

  it('duplicates block with new id', () => {
    const base = createBlock('cta', 1)
    const copy = duplicateBlock(base, 2)
    expect(copy.id).not.toBe(base.id)
    expect(copy.position).toBe(2)
  })

  it('reorders block list', () => {
    const first = createBlock('cta', 0)
    const second = createBlock('gallery', 1)
    const next = reorderBlocks([first, second], 0, 1)
    expect(next[0]?.id).toBe(second.id)
    expect(next[1]?.id).toBe(first.id)
  })

  it('normalizes and validates blocks', () => {
    const block = createBlock('pricing', 10)
    const normalized = normalizePageBlocks([block])
    expect(normalized[0]?.position).toBe(0)
    expect(validatePageBlocks(normalized).isValid).toBe(true)
  })

  it('creates slider block with extended defaults', () => {
    const block = createBlock('slider', 0)
    const firstSlide = (block.content.items as Array<Record<string, unknown>>)[0]

    expect(firstSlide).toMatchObject({
      src: '',
      alt: 'Слайд 1',
      title: 'Заголовок слайда',
      text: 'Короткое описание слайда.',
      buttonLabel: 'Подробнее',
      buttonHref: '#',
    })
    expect(block.settings).toMatchObject({
      autoplay: true,
      loop: true,
      pagination: true,
      navigation: true,
      delayMs: 4500,
      effect: 'slide',
      pauseOnHover: true,
      disableOnInteraction: false,
      speedMs: 500,
      breakpoints: {
        mobileSlidesPerView: 1,
        tabletSlidesPerView: 1,
        desktopSlidesPerView: 1,
        mobileSpaceBetween: 8,
        tabletSpaceBetween: 16,
        desktopSpaceBetween: 24,
      },
      a11yLabels: {
        prevSlide: 'Предыдущий слайд',
        nextSlide: 'Следующий слайд',
        paginationBullet: 'Перейти к слайду {{index}}',
      },
      cardsEffect: {
        perSlideOffset: 6,
        perSlideRotate: 1,
        rotate: true,
        slideShadows: false,
      },
      coverflowEffect: {
        rotate: 18,
        stretch: 0,
        depth: 90,
        modifier: 1,
        slideShadows: false,
      },
    })
  })
})
