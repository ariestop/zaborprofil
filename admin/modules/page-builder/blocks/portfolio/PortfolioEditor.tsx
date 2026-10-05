import type { BuilderBlock } from '../../types'
import { GenericBlockEditor } from '../shared/GenericBlockEditor'

interface PortfolioEditorProps {
  block: BuilderBlock
}

export function PortfolioEditor({ block }: PortfolioEditorProps) {
  return <GenericBlockEditor block={block} />
}
