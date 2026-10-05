import type { BlockCategoryDefinition } from '../types'

export const blockCategories: BlockCategoryDefinition[] = [
  { id: 'layout', title: 'Каркас страницы', order: 10 },
  { id: 'hero', title: 'Первый экран (Hero)', order: 20 },
  { id: 'content', title: 'Текстовый контент', order: 30 },
  { id: 'media', title: 'Медиа и интерактив', order: 40 },
  { id: 'conversion', title: 'Конверсия (CTA и формы)', order: 50 },
  { id: 'business', title: 'Каталог и продукт', order: 60 },
  { id: 'seo_system', title: 'SEO и системные блоки', order: 70 },
]
