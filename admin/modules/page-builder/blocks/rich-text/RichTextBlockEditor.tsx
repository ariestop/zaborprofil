import type { BuilderBlock } from '../../types'
import { GenericBlockEditor } from '../shared/GenericBlockEditor'

interface RichTextBlockEditorProps {
  block: BuilderBlock
}

export function RichTextBlockEditor({ block }: RichTextBlockEditorProps) {
  return <GenericBlockEditor block={block} />
}
