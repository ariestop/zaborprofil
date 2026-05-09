import { useState } from 'react'
import { blockCategories } from '../registry/blockCategories'
import { blockRegistry } from '../registry/blockRegistry'
import type { BlockCategory, BuilderBlockType } from '../types'
import { BlockCatalog } from './BlockCatalog'
import { BlockCategoryList } from './BlockCategoryList'

interface BuilderSidebarProps {
  onAddBlock: (type: BuilderBlockType) => void
}

export function BuilderSidebar({ onAddBlock }: BuilderSidebarProps) {
  const [activeCategory, setActiveCategory] = useState<BlockCategory | null>(null)

  return (
    <aside className="space-y-4 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
      <h3 className="text-sm font-semibold">Каталог блоков</h3>
      <BlockCategoryList categories={blockCategories} activeCategory={activeCategory} onSelect={setActiveCategory} />
      <BlockCatalog definitions={blockRegistry} activeCategory={activeCategory} onAdd={onAddBlock} />
    </aside>
  )
}
