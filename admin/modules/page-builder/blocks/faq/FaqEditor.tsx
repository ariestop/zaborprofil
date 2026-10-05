import type { BuilderBlock } from '../../types'
import { GenericBlockEditor } from '../shared/GenericBlockEditor'

interface FaqEditorProps {
  block: BuilderBlock
}

export function FaqEditor({ block }: FaqEditorProps) {
  return <GenericBlockEditor block={block} />
}
