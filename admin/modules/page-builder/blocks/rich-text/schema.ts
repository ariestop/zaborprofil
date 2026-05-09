import { blockRegistryByType } from '../../registry/blockRegistry'

export const richTextBlockSchema = blockRegistryByType.get('rich-text')?.contentSchema
