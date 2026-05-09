import type { BlockCategory, BlockCategoryDefinition } from '../types'

interface BlockCategoryListProps {
  categories: BlockCategoryDefinition[]
  activeCategory: BlockCategory | null
  onSelect: (category: BlockCategory | null) => void
}

export function BlockCategoryList({ categories, activeCategory, onSelect }: BlockCategoryListProps) {
  return (
    <div className="space-y-2">
      <button
        type="button"
        className="w-full rounded-md border border-slate-200 px-3 py-2 text-left text-sm dark:border-slate-700"
        onClick={() => onSelect(null)}
      >
        Все категории
      </button>
      {categories.map((category) => (
        <button
          key={category.id}
          type="button"
          className={[
            'w-full rounded-md border px-3 py-2 text-left text-sm',
            activeCategory === category.id ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20' : 'border-slate-200 dark:border-slate-700',
          ].join(' ')}
          onClick={() => onSelect(category.id)}
        >
          {category.title}
        </button>
      ))}
    </div>
  )
}
