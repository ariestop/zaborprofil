import type { ContentPageDetail, ContentPageItem } from '../../types/api'

export function makePage(overrides: Partial<ContentPageDetail> = {}): ContentPageDetail {
  return {
    id: 'page-1',
    type: 'landing',
    title: 'Заборы',
    slug: 'zabory',
    path: '/zabory/',
    h1: 'Заборы',
    status: 'draft',
    template: 'default',
    sortOrder: 0,
    parentId: null,
    isIndexable: true,
    visibility: 'public',
    publishedAt: null,
    scheduledPublishAt: null,
    scheduledUnpublishAt: null,
    seo: {
      metaTitle: null,
      metaDescription: null,
      canonicalUrl: null,
      ogTitle: null,
      ogDescription: null,
      ogImage: null,
      ogType: null,
      jsonLd: null,
    },
    blocks: [],
    ...overrides,
  }
}

export function makePageItem(overrides: Partial<ContentPageItem> = {}): ContentPageItem {
  return makePage(overrides)
}
