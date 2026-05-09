import type { BuilderBlock } from '../../types'
import { GenericBlockPreview } from '../shared/GenericBlockPreview'

interface RichTextBlockPreviewProps {
  block: BuilderBlock
}

export function RichTextBlockPreview({ block }: RichTextBlockPreviewProps) {
  return <GenericBlockPreview block={block} />
}
