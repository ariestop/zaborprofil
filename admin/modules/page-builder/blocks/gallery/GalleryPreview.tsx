import type { BuilderBlock } from '../../types'
import { GenericBlockPreview } from '../shared/GenericBlockPreview'

interface GalleryPreviewProps {
  block: BuilderBlock
}

export function GalleryPreview({ block }: GalleryPreviewProps) {
  return <GenericBlockPreview block={block} />
}
