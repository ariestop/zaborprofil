import { blockRegistryByType } from '../../registry/blockRegistry'

export const portfolioBlockSchema = blockRegistryByType.get('portfolio')?.contentSchema
