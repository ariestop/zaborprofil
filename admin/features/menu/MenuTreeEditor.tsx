import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import { SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import * as DropdownMenu from '@radix-ui/react-dropdown-menu'
import type { ReactNode } from 'react'
import type { MenuItem, MenuPosition, MenuPositionOption } from '../../entities/menu/api'
import { cn } from '../../shared/lib/cn'
import { displayUrl, findParent, moveBy, moveOver, nestInto, nestTargets, unnest, type MenuNode } from './menu-tree'

export interface MenuTreeActions {
  /** Новое дерево позиции после перестановки, вложения или выноса. */
  onReorder: (tree: MenuNode[]) => void
  onEdit: (item: MenuItem) => void
  onAddChild: (section: MenuNode) => void
  onToggleActive: (item: MenuItem) => void
  onMoveToPosition: (item: MenuItem, position: MenuPosition) => void
  onDelete: (item: MenuItem, childrenCount: number) => void
}

interface MenuTreeEditorProps extends MenuTreeActions {
  tree: MenuNode[]
  positions: MenuPositionOption[]
  disabled: boolean
}

const iconButton =
  'flex size-10 shrink-0 items-center justify-center rounded-lg text-graphite transition hover:bg-surface-strong hover:text-ink focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 disabled:pointer-events-none disabled:opacity-30 dark:text-slate-400 dark:hover:bg-slate-800'

const menuItemClass =
  'flex min-h-10 cursor-pointer items-center gap-2 rounded-lg px-2.5 text-sm text-ink outline-hidden data-[highlighted]:bg-surface-strong data-[disabled]:cursor-default data-[disabled]:opacity-40 dark:text-slate-200 dark:data-[highlighted]:bg-slate-800'

const menuContentClass =
  'z-50 min-w-56 rounded-xl border border-line bg-white p-1.5 shadow-[0_16px_32px_rgba(16,24,40,0.14)] dark:border-slate-700 dark:bg-slate-900'

function Icon({ d, size = 18 }: { d: ReactNode; size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.9} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {d}
    </svg>
  )
}

function RowMenu({ item, tree, positions, isSection, actions }: { item: MenuItem; tree: MenuNode[]; positions: MenuPositionOption[]; isSection: boolean; actions: MenuTreeActions }) {
  const node = tree.find((candidate) => candidate.id === item.id)
  const childrenCount = node?.children.length ?? 0
  const targets = nestTargets(tree, item.id)
  const parent = findParent(tree, item.id)
  const otherPositions = positions.filter((option) => option.value !== item.position)

  return (
    <DropdownMenu.Root modal={false}>
      <DropdownMenu.Trigger className={iconButton} aria-label={`Действия с пунктом «${item.label}»`}>
        <Icon d={<><circle cx="5" cy="12" r="1.2" fill="currentColor" /><circle cx="12" cy="12" r="1.2" fill="currentColor" /><circle cx="19" cy="12" r="1.2" fill="currentColor" /></>} />
      </DropdownMenu.Trigger>
      <DropdownMenu.Portal>
        <DropdownMenu.Content align="end" sideOffset={4} className={menuContentClass}>
          <DropdownMenu.Item className={menuItemClass} onSelect={() => actions.onEdit(item)}>Редактировать</DropdownMenu.Item>
          {isSection && node !== undefined ? (
            <DropdownMenu.Item className={menuItemClass} onSelect={() => actions.onAddChild(node)}>Добавить подпункт</DropdownMenu.Item>
          ) : null}
          <DropdownMenu.Item className={menuItemClass} onSelect={() => actions.onToggleActive(item)}>
            {item.isActive ? 'Скрыть на сайте' : 'Показать на сайте'}
          </DropdownMenu.Item>
          <DropdownMenu.Separator className="mx-1 my-1.5 h-px bg-surface-strong dark:bg-slate-800" />
          {parent !== null ? (
            <DropdownMenu.Item className={menuItemClass} onSelect={() => actions.onReorder(unnest(tree, item.id))}>Сделать отдельным пунктом</DropdownMenu.Item>
          ) : null}
          {targets.length > 0 ? (
            <DropdownMenu.Sub>
              <DropdownMenu.SubTrigger className={menuItemClass}>Вложить в раздел…</DropdownMenu.SubTrigger>
              <DropdownMenu.Portal>
                <DropdownMenu.SubContent className={menuContentClass} sideOffset={6}>
                  {targets.map((target) => (
                    <DropdownMenu.Item key={target.id} className={menuItemClass} onSelect={() => actions.onReorder(nestInto(tree, item.id, target.id))}>
                      {target.label}
                    </DropdownMenu.Item>
                  ))}
                </DropdownMenu.SubContent>
              </DropdownMenu.Portal>
            </DropdownMenu.Sub>
          ) : null}
          {otherPositions.length > 0 ? (
            <DropdownMenu.Sub>
              <DropdownMenu.SubTrigger className={menuItemClass}>Перенести в другое меню…</DropdownMenu.SubTrigger>
              <DropdownMenu.Portal>
                <DropdownMenu.SubContent className={menuContentClass} sideOffset={6}>
                  {otherPositions.map((option) => (
                    <DropdownMenu.Item key={option.value} className={menuItemClass} onSelect={() => actions.onMoveToPosition(item, option.value)}>
                      {option.label}
                    </DropdownMenu.Item>
                  ))}
                </DropdownMenu.SubContent>
              </DropdownMenu.Portal>
            </DropdownMenu.Sub>
          ) : null}
          <DropdownMenu.Separator className="mx-1 my-1.5 h-px bg-surface-strong dark:bg-slate-800" />
          <DropdownMenu.Item className={cn(menuItemClass, 'text-danger dark:text-red-300')} onSelect={() => actions.onDelete(item, childrenCount)}>
            Удалить
          </DropdownMenu.Item>
        </DropdownMenu.Content>
      </DropdownMenu.Portal>
    </DropdownMenu.Root>
  )
}

function Row({ item, tree, positions, isSection, childrenCount, disabled, actions }: { item: MenuItem; tree: MenuNode[]; positions: MenuPositionOption[]; isSection: boolean; childrenCount: number; disabled: boolean; actions: MenuTreeActions }) {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({ id: item.id, disabled })
  const up = moveBy(tree, item.id, -1)
  const down = moveBy(tree, item.id, 1)

  return (
    <div
      ref={setNodeRef}
      style={{ transform: CSS.Translate.toString(transform), transition }}
      className={cn(
        'flex items-center gap-1 rounded-xl border bg-white py-1.5 pr-1.5 pl-1 dark:bg-slate-900',
        isSection ? 'border-line dark:border-slate-700' : 'border-transparent bg-surface/70 dark:bg-slate-800/40',
        isDragging && 'relative z-10 border-brand-300 shadow-lg',
      )}
      data-testid="menu-row"
    >
      <button
        ref={setActivatorNodeRef}
        type="button"
        className={cn(iconButton, 'cursor-grab active:cursor-grabbing')}
        aria-label={`Перетащить «${item.label}»`}
        disabled={disabled}
        {...attributes}
        {...listeners}
      >
        <Icon size={16} d={<path d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01" strokeWidth={2.8} />} />
      </button>
      <button type="button" onClick={() => actions.onEdit(item)} className={cn('flex min-w-0 flex-1 flex-col rounded-lg px-1.5 py-1 text-left hover:bg-surface/80 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 dark:hover:bg-slate-800', !item.isActive && 'opacity-55')}>
        <span className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5">
          <span className={cn('min-w-0 text-[15px] break-words sm:truncate text-ink dark:text-slate-100', isSection ? 'font-semibold' : 'font-medium')}>{item.label}</span>
          {!item.isActive ? <span className="shrink-0 rounded-md bg-surface-strong px-1.5 py-0.5 text-[11px] font-bold text-graphite dark:bg-slate-800 dark:text-slate-300">скрыт</span> : null}
          {isSection && childrenCount > 0 ? <span className="shrink-0 rounded-md bg-brand-50 px-1.5 py-0.5 text-[11px] font-bold text-brand-800 dark:bg-brand-950/50 dark:text-brand-200">{childrenCount} в разделе</span> : null}
        </span>
        <span className="truncate text-[13px] text-graphite dark:text-slate-400">
          <span className="font-mono">{displayUrl(item.url)}</span>
          {item.hint !== null ? <span> · {item.hint}</span> : null}
        </span>
      </button>
      <div className="flex shrink-0 flex-col sm:flex-row">
      <button type="button" className={iconButton} aria-label={`Выше: «${item.label}»`} disabled={disabled || up === null} onClick={() => up !== null && actions.onReorder(up)}>
        <Icon d={<path d="M12 19V5M6 11l6-6 6 6" />} />
      </button>
      <button type="button" className={iconButton} aria-label={`Ниже: «${item.label}»`} disabled={disabled || down === null} onClick={() => down !== null && actions.onReorder(down)}>
        <Icon d={<path d="M12 5v14M6 13l6 6 6-6" />} />
      </button>
      </div>
      <RowMenu item={item} tree={tree} positions={positions} isSection={isSection} actions={actions} />
    </div>
  )
}

/**
 * Дерево одной позиции меню. Перетаскивание (мышь, палец, клавиатура: пробел и стрелки) меняет порядок среди соседей,
 * между уровнями пункт переносится из меню «⋯»: «Вложить в раздел…» и «Сделать отдельным пунктом».
 */
export function MenuTreeEditor({ tree, positions, disabled, ...actions }: MenuTreeEditorProps) {
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  const onDragEnd = (event: DragEndEvent): void => {
    if (event.over === null) {
      return
    }
    const next = moveOver(tree, String(event.active.id), String(event.over.id))
    if (next !== null) {
      actions.onReorder(next)
    }
  }

  return (
    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
      <SortableContext items={tree.map((node) => node.id)} strategy={verticalListSortingStrategy}>
        <ol className="grid grid-cols-1 gap-2" aria-label="Пункты меню">
          {tree.map((node) => (
            <li key={node.id} className="grid min-w-0 grid-cols-1 gap-1.5">
              <Row item={node} tree={tree} positions={positions} isSection childrenCount={node.children.length} disabled={disabled} actions={actions} />
              {node.children.length > 0 ? (
                <SortableContext items={node.children.map((child) => child.id)} strategy={verticalListSortingStrategy}>
                  <ol className="ml-3 grid grid-cols-1 gap-1.5 border-l-2 border-line pl-2 sm:ml-6 sm:pl-3 dark:border-slate-700" aria-label={`Подпункты раздела «${node.label}»`}>
                    {node.children.map((child) => (
                      <li key={child.id} className="min-w-0">
                        <Row item={child} tree={tree} positions={positions} isSection={false} childrenCount={0} disabled={disabled} actions={actions} />
                      </li>
                    ))}
                  </ol>
                </SortableContext>
              ) : null}
            </li>
          ))}
        </ol>
      </SortableContext>
    </DndContext>
  )
}
