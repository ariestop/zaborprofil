import { z } from 'zod'

export const BLOCK_CATEGORIES = [
  'layout',
  'hero',
  'content',
  'media',
  'conversion',
  'business',
  'seo_system',
] as const

export type BlockCategory = (typeof BLOCK_CATEGORIES)[number]

export const BLOCK_TYPES = [
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
  'contacts-map',
  'partner-cta',
  'breadcrumbs',
  'sitemap-section',
  'related-pages',
  'internal-links',
  'schema-faq',
  'schema-local-business',
] as const

export type BuilderBlockType = (typeof BLOCK_TYPES)[number]

export interface BuilderBlockMetadata {
  createdAt: string
  updatedAt: string
}

export interface BuilderBlock {
  id: string
  type: BuilderBlockType
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
  description: string
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
