import type { BuilderBlock } from '../../types'
import { GenericBlockEditor } from '../shared/GenericBlockEditor'

interface FeaturesEditorProps {
  block: BuilderBlock
}

export function FeaturesEditor({ block }: FeaturesEditorProps) {
  return <GenericBlockEditor block={block} />
}
