import type { BuilderBlock } from '../../types'
import { GenericBlockPreview } from '../shared/GenericBlockPreview'

interface HeroClassicPreviewProps {
  block: BuilderBlock
}

export function HeroClassicPreview({ block }: HeroClassicPreviewProps) {
  return <GenericBlockPreview block={block} />
}
