import type { BuilderBlock } from '../../types'
import { GenericBlockEditor } from '../shared/GenericBlockEditor'

interface HeroClassicEditorProps {
  block: BuilderBlock
}

export function HeroClassicEditor({ block }: HeroClassicEditorProps) {
  return <GenericBlockEditor block={block} />
}
