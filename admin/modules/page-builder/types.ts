import { z } from 'zod'

export const BLOCK_CATEGORIES = [
  'layout',
  'hero',
  'content',
  'media',
  'conversion',
  'business',
  'seo_system',
  'legacy',
] as const

export type BlockCategory = (typeof BLOCK_CATEGORIES)[number]

export const STRUCTURED_BLOCK_TYPES = [
  'section',
  'container',
  'grid',
  'columns',
  'spacer',
  'divider',
  'tabs',
  'accordion',
  'hero.classic',
  'hero.centered',
  'hero.split',
  'hero.with-image',
  'hero.cta',
  'hero.minimal',
  'rich-text',
  'text-with-image',
  'article-section',
  'quote',
  'faq',
  'steps',
  'benefits',
  'features',
  'icons-list',
  'image',
  'gallery',
  'before-after',
  'video',
  'slider',
  'cta',
  'contact-form',
  'lead-form',
  'callback-form',
  'calculator-placeholder',
  'pricing',
  'reviews',
  'trust-badges',
  'fence-types',
  'materials',
  'portfolio',
  'works-gallery',
  'service-cards',
  'advantages',
  'installation-steps',
  'price-table',
  'price-matrix',
  'fence-configurator',
  'contacts-map',
  'partner-cta',
  'breadcrumbs',
  'sitemap-section',
  'related-pages',
  'internal-links',
  'schema-faq',
  'schema-local-business',
] as const

export const LEGACY_BLOCK_TYPES = [
  'hero',
  'text',
  'text_image',
  'feature_grid',
  'price_cards',
  'cta_form',
  'telegram_cta',
  'contacts',
  'map',
  'portfolio_grid',
  'seo_text',
  'html_embed',
  'table',
  'before_after',
  'calculator_placeholder',
  'review_cards',
  'documents',
] as const

export type LegacyBlockType = (typeof LEGACY_BLOCK_TYPES)[number]

export const BLOCK_TYPES = [...STRUCTURED_BLOCK_TYPES, ...LEGACY_BLOCK_TYPES] as const

export type BuilderBlockType = (typeof BLOCK_TYPES)[number]

export const LEGACY_BLOCK_TYPE_ALIASES: Record<LegacyBlockType, (typeof STRUCTURED_BLOCK_TYPES)[number] | null> = {
  hero: 'hero.classic',
  text: 'rich-text',
  text_image: 'text-with-image',
  feature_grid: 'features',
  price_cards: 'pricing',
  cta_form: 'cta',
  telegram_cta: 'cta',
  contacts: 'contacts-map',
  map: 'contacts-map',
  portfolio_grid: 'portfolio',
  seo_text: 'rich-text',
  html_embed: null,
  table: 'price-table',
  before_after: 'before-after',
  calculator_placeholder: 'calculator-placeholder',
  review_cards: 'reviews',
  documents: null,
}

export function isLegacyBlockType(type: string): type is LegacyBlockType {
  return (LEGACY_BLOCK_TYPES as readonly string[]).includes(type)
}

export interface BuilderBlockMetadata {
  createdAt: string
  updatedAt: string
}

export interface BuilderBlock {
  id: string
  type: BuilderBlockType
  /** Название блока для редактора («Первый экран», «SEO-текст»). Пустое — подставляется название вида блока. */
  name?: string
  enabled: boolean
  position: number
  content: Record<string, unknown>
  settings: Record<string, unknown>
  metadata: BuilderBlockMetadata
}

export interface BuilderDocument {
  pageId: string
  blocks: BuilderBlock[]
  updatedAt: string | null
}

export interface BuilderValidationIssue {
  blockId: string
  path: string
  message: string
}

export interface BuilderValidationResult {
  isValid: boolean
  issues: BuilderValidationIssue[]
}

export interface BuilderVersionRecord {
  id: string
  createdAt: string
  author: string
  comment: string
}

export interface BlockCategoryDefinition {
  id: BlockCategory
  title: string
  order: number
}

export interface BlockDefinition {
  type: BuilderBlockType
  title: string
  category: BlockCategory
  sortOrder: number
  description: string
  legacy?: boolean
  canonicalType?: BuilderBlockType
  defaults: {
    content: Record<string, unknown>
    settings: Record<string, unknown>
  }
  contentSchema: z.ZodType<Record<string, unknown>>
  settingsSchema: z.ZodType<Record<string, unknown>>
}

export const blockMetadataSchema = z.object({
  createdAt: z.string().datetime({ offset: true }).or(z.string().min(1)),
  updatedAt: z.string().datetime({ offset: true }).or(z.string().min(1)),
})

export const builderBlockSchema = z.object({
  id: z.string().min(1),
  type: z.enum(BLOCK_TYPES),
  enabled: z.boolean(),
  position: z.number().int().min(0),
  content: z.record(z.string(), z.unknown()),
  settings: z.record(z.string(), z.unknown()),
  metadata: blockMetadataSchema,
})

export const builderDocumentSchema = z.object({
  pageId: z.string().min(1),
  blocks: z.array(builderBlockSchema),
  updatedAt: z.string().nullable(),
})
