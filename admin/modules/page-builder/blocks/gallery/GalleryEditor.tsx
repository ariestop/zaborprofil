import type { BuilderBlock } from '../../types'
import { GenericBlockEditor } from '../shared/GenericBlockEditor'

interface GalleryEditorProps {
  block: BuilderBlock
}

export function GalleryEditor({ block }: GalleryEditorProps) {
  return <GenericBlockEditor block={block} />
}
