import type { BuilderBlock } from '../../types'
import { GenericBlockPreview } from '../shared/GenericBlockPreview'

interface PortfolioPreviewProps {
  block: BuilderBlock
}

export function PortfolioPreview({ block }: PortfolioPreviewProps) {
  return <GenericBlockPreview block={block} />
}
