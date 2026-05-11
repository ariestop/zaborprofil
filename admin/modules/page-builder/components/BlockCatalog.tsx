import type { BlockCategory, BlockDefinition } from '../types'
import { blockCategories } from '../registry/blockCategories'

interface BlockCatalogProps {
  definitions: BlockDefinition[]
  activeCategory: BlockCategory | null
  onAdd: (type: BlockDefinition['type']) => void
}

export function BlockCatalog({ definitions, activeCategory, onAdd }: BlockCatalogProps) {
  const collator = new Intl.Collator('ru', { sensitivity: 'base', numeric: true })
  const categoryOrder = new Map(blockCategories.map((category) => [category.id, category.order]))
  const sorted = [...definitions].sort((left, right) => {
    const leftOrder = categoryOrder.get(left.category) ?? Number.MAX_SAFE_INTEGER
    const rightOrder = categoryOrder.get(right.category) ?? Number.MAX_SAFE_INTEGER

    if (leftOrder !== rightOrder) {
      return leftOrder - rightOrder
    }

    if (left.sortOrder !== right.sortOrder) {
      return left.sortOrder - right.sortOrder
    }

    return collator.compare(left.title, right.title)
  })

  const visible = activeCategory === null
    ? sorted
    : sorted.filter((definition) => definition.category === activeCategory)
  const grouped = activeCategory === null
    ? blockCategories
      .map((category) => ({
        category,
        definitions: visible.filter((definition) => definition.category === category.id),
      }))
      .filter((group) => group.definitions.length > 0)
    : []

  return (
    <div className="space-y-2">
      {activeCategory === null && grouped.map((group) => (
        <div key={group.category.id} className="space-y-2">
          <div className="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            {group.category.title}
          </div>
          {group.definitions.map((definition) => (
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
      ))}
      {activeCategory !== null && visible.map((definition) => (
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
