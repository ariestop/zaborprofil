import type { BlockCategory, BlockDefinition } from '../types'

interface BlockCatalogProps {
  definitions: BlockDefinition[]
  activeCategory: BlockCategory | null
  onAdd: (type: BlockDefinition['type']) => void
}

export function BlockCatalog({ definitions, activeCategory, onAdd }: BlockCatalogProps) {
  const visible = activeCategory === null
    ? definitions
    : definitions.filter((definition) => definition.category === activeCategory)

  return (
    <div className="space-y-2">
      {visible.map((definition) => (
        <button
          key={definition.type}
          type="button"
          onClick={() => onAdd(definition.type)}
          className="w-full rounded-md border border-slate-200 px-3 py-2 text-left dark:border-slate-700"
        >
          <div className="text-sm font-medium">{definition.title}</div>
          <div className="text-xs text-slate-500 dark:text-slate-400">{definition.description}</div>
        </button>
      ))}
    </div>
  )
}
