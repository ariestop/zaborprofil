import type { BuilderBlock } from '../../types'
import { GenericBlockPreview } from '../shared/GenericBlockPreview'

interface FaqPreviewProps {
  block: BuilderBlock
}

export function FaqPreview({ block }: FaqPreviewProps) {
  return <GenericBlockPreview block={block} />
}
