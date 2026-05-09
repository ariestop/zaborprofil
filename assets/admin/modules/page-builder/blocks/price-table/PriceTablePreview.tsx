import type { BuilderBlock } from '../../types'
import { GenericBlockPreview } from '../shared/GenericBlockPreview'

interface PriceTablePreviewProps {
  block: BuilderBlock
}

export function PriceTablePreview({ block }: PriceTablePreviewProps) {
  return <GenericBlockPreview block={block} />
}
