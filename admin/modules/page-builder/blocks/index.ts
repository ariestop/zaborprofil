import { z } from 'zod'
import { blockRegistry } from '../registry/blockRegistry'
import type { BuilderBlockType } from '../types'
import { GenericBlockEditor } from './shared/GenericBlockEditor'
import { GenericBlockPreview } from './shared/GenericBlockPreview'
import { heroClassicSchema, heroClassicDefaults, HeroClassicEditor, HeroClassicPreview } from './hero-classic'
import { richTextBlockSchema, richTextBlockDefaults, RichTextBlockEditor, RichTextBlockPreview } from './rich-text'
import { featuresBlockSchema, featuresBlockDefaults, FeaturesEditor, FeaturesPreview } from './features'
import { faqBlockSchema, faqBlockDefaults, FaqEditor, FaqPreview } from './faq'
import { galleryBlockSchema, galleryBlockDefaults, GalleryEditor, GalleryPreview } from './gallery'
import { ctaBlockSchema, ctaBlockDefaults, CtaEditor, CtaPreview } from './cta'
import { contactFormBlockSchema, contactFormBlockDefaults, ContactFormEditor, ContactFormPreview } from './contact-form'
import { priceTableBlockSchema, priceTableBlockDefaults, PriceTableEditor, PriceTablePreview } from './price-table'
import { portfolioBlockSchema, portfolioBlockDefaults, PortfolioEditor, PortfolioPreview } from './portfolio'

export interface BlockModuleDefinition {
  schema: z.ZodType<Record<string, unknown>>
  defaults: {
    content: Record<string, unknown>
    settings: Record<string, unknown>
  }
  Editor: typeof GenericBlockEditor
  Preview: typeof GenericBlockPreview
}

const fallbackModules: Record<BuilderBlockType, BlockModuleDefinition> = blockRegistry.reduce((accumulator, definition) => {
  accumulator[definition.type] = {
    schema: definition.contentSchema as z.ZodType<Record<string, unknown>>,
    defaults: definition.defaults,
    Editor: GenericBlockEditor,
    Preview: GenericBlockPreview,
  }
  return accumulator
}, {} as Record<BuilderBlockType, BlockModuleDefinition>)

export const blockModules: Record<BuilderBlockType, BlockModuleDefinition> = {
  ...fallbackModules,
  'hero.classic': {
    schema: heroClassicSchema as z.ZodType<Record<string, unknown>>,
    defaults: heroClassicDefaults,
    Editor: HeroClassicEditor,
    Preview: HeroClassicPreview,
  },
  'rich-text': {
    schema: richTextBlockSchema as z.ZodType<Record<string, unknown>>,
    defaults: richTextBlockDefaults,
    Editor: RichTextBlockEditor,
    Preview: RichTextBlockPreview,
  },
  features: {
    schema: featuresBlockSchema as z.ZodType<Record<string, unknown>>,
    defaults: featuresBlockDefaults,
    Editor: FeaturesEditor,
    Preview: FeaturesPreview,
  },
  faq: {
    schema: faqBlockSchema as z.ZodType<Record<string, unknown>>,
    defaults: faqBlockDefaults,
    Editor: FaqEditor,
    Preview: FaqPreview,
  },
  gallery: {
    schema: galleryBlockSchema as z.ZodType<Record<string, unknown>>,
    defaults: galleryBlockDefaults,
    Editor: GalleryEditor,
    Preview: GalleryPreview,
  },
  cta: {
    schema: ctaBlockSchema as z.ZodType<Record<string, unknown>>,
    defaults: ctaBlockDefaults,
    Editor: CtaEditor,
    Preview: CtaPreview,
  },
  'contact-form': {
    schema: contactFormBlockSchema as z.ZodType<Record<string, unknown>>,
    defaults: contactFormBlockDefaults,
    Editor: ContactFormEditor,
    Preview: ContactFormPreview,
  },
  'price-table': {
    schema: priceTableBlockSchema as z.ZodType<Record<string, unknown>>,
    defaults: priceTableBlockDefaults,
    Editor: PriceTableEditor,
    Preview: PriceTablePreview,
  },
  portfolio: {
    schema: portfolioBlockSchema as z.ZodType<Record<string, unknown>>,
    defaults: portfolioBlockDefaults,
    Editor: PortfolioEditor,
    Preview: PortfolioPreview,
  },
}
