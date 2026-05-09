import type { BuilderBlock } from '../../types'

interface GenericBlockPreviewProps {
  block: BuilderBlock
}

export function GenericBlockPreview({ block }: GenericBlockPreviewProps) {
  return (
    <pre className="max-h-56 overflow-auto rounded bg-slate-50 p-3 text-xs dark:bg-slate-900">
      {JSON.stringify(block.content, null, 2)}
    </pre>
  )
}
