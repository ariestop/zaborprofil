import type { CollectionConfig } from 'payload'
import { HeroBlock } from '../blocks/Hero'
import { TextBlock } from '../blocks/Text'
import { FAQBlock } from '../blocks/FAQ'
import { CTABlock } from '../blocks/CTA'
import { GridBlock } from '../blocks/Grid'

export const Pages: CollectionConfig = {
  slug: 'pages',
  admin: {
    useAsTitle: 'title',
  },
  fields: [
    {
      name: 'title',
      type: 'text',
      required: true,
    },
    {
      name: 'slug',
      type: 'text',
      required: true,
      unique: true,
    },
    {
      name: 'layout',
      type: 'blocks',
      blocks: [HeroBlock, TextBlock, FAQBlock, CTABlock, GridBlock],
    },
    {
      name: 'meta',
      type: 'group',
      fields: [
        { name: 'metaTitle', type: 'text' },
        { name: 'metaDesc', type: 'textarea' },
        { name: 'ogImage', type: 'upload', relationTo: 'media' },
      ],
    },
  ],
}
