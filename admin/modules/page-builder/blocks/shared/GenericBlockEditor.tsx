import type { BuilderBlock } from '../../types'

interface GenericBlockEditorProps {
  block: BuilderBlock
  onChange?: (nextBlock: BuilderBlock) => void
}

export function GenericBlockEditor({ block }: GenericBlockEditorProps) {
  return (
    <div className="rounded-md border border-slate-200 p-3 text-xs dark:border-slate-700">
      Блок <span className="font-semibold">{block.type}</span> редактируется через JSON-панель ниже.
    </div>
  )
}
