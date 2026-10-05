import type { BuilderBlock } from '../../types'
import { GenericBlockEditor } from '../shared/GenericBlockEditor'

interface CtaEditorProps {
  block: BuilderBlock
}

export function CtaEditor({ block }: CtaEditorProps) {
  return <GenericBlockEditor block={block} />
}
