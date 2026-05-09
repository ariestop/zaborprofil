import type { BuilderBlock } from '../../types'

interface GenericBlockEditorProps {
  block: BuilderBlock
}

export function GenericBlockEditor({ block }: GenericBlockEditorProps) {
  return (
    <div className="rounded-md border border-slate-200 p-3 text-xs dark:border-slate-700">
      Редактор блока <span className="font-semibold">{block.type}</span> использует JSON-панель справа.
    </div>
  )
}
