import type { BuilderBlock } from '../../types'
import { GenericBlockEditor } from '../shared/GenericBlockEditor'

interface ContactFormEditorProps {
  block: BuilderBlock
}

export function ContactFormEditor({ block }: ContactFormEditorProps) {
  return <GenericBlockEditor block={block} />
}
