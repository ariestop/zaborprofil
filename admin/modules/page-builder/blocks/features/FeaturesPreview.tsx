import type { BuilderBlock } from '../../types'
import { GenericBlockPreview } from '../shared/GenericBlockPreview'

interface FeaturesPreviewProps {
  block: BuilderBlock
}

export function FeaturesPreview({ block }: FeaturesPreviewProps) {
  return <GenericBlockPreview block={block} />
}
