import type { BuilderBlock } from '../types'
import { blockModules } from '../blocks'

interface BlockPreviewProps {
  block: BuilderBlock | null
}

export function BlockPreview({ block }: BlockPreviewProps) {
  if (block === null) {
    return (
      <div className="rounded-xl border border-slate-200 p-4 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
        Предпросмотр блока появится после выбора элемента.
      </div>
    )
  }

  const ModulePreview = blockModules[block.type]?.Preview

  return (
    <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
      <div className="mb-2 text-xs uppercase text-slate-500">{block.type}</div>
      {ModulePreview !== undefined ? <ModulePreview block={block} /> : (
        <pre className="max-h-56 overflow-auto rounded bg-slate-50 p-3 text-xs dark:bg-slate-900">
          {JSON.stringify(block.content, null, 2)}
        </pre>
      )}
    </div>
  )
}
