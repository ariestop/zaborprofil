import type { BuilderBlock } from '../../types'
import { GenericBlockEditor } from '../shared/GenericBlockEditor'

interface SliderEditorProps {
  block: BuilderBlock
}

export function SliderEditor({ block }: SliderEditorProps) {
  return <GenericBlockEditor block={block} />
}
