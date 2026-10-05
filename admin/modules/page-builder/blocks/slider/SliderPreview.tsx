import type { BuilderBlock } from '../../types'
import { GenericBlockPreview } from '../shared/GenericBlockPreview'

interface SliderPreviewProps {
  block: BuilderBlock
}

export function SliderPreview({ block }: SliderPreviewProps) {
  return <GenericBlockPreview block={block} />
}
