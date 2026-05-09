import type { BuilderBlock } from '../../types'
import { GenericBlockPreview } from '../shared/GenericBlockPreview'

interface ContactFormPreviewProps {
  block: BuilderBlock
}

export function ContactFormPreview({ block }: ContactFormPreviewProps) {
  return <GenericBlockPreview block={block} />
}
