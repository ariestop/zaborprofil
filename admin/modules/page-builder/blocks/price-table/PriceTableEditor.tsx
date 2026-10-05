import type { BuilderBlock } from '../../types'
import { GenericBlockEditor } from '../shared/GenericBlockEditor'

interface PriceTableEditorProps {
  block: BuilderBlock
}

export function PriceTableEditor({ block }: PriceTableEditorProps) {
  return <GenericBlockEditor block={block} />
}
