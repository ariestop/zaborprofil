import type { BuilderBlock } from '../../types'
import { GenericBlockPreview } from '../shared/GenericBlockPreview'

interface CtaPreviewProps {
  block: BuilderBlock
}

export function CtaPreview({ block }: CtaPreviewProps) {
  return <GenericBlockPreview block={block} />
}
