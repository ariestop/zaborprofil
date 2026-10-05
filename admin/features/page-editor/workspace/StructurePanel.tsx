import {
    DndContext,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core'
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import type { BuilderBlock } from '../../../modules/page-builder/types'
import { NavIcon } from '../../../layouts/nav-icons'
import { cn } from '../../../shared/lib/cn'
import { BlockIcon } from './BlockIcon'
import { blockIcon, blockSummary, blockTitle, isPlaceholderBlock, plural } from './block-kinds'

export interface ReadinessItem {
    id: string
    ok: boolean
    text: string
    action?: { label: string; onClick: () => void }
}

interface StructurePanelProps {
    blocks: BuilderBlock[]
    selectedBlockId: string | null
    invalidBlockIds: Set<string>
    readiness: ReadinessItem[]
    onSelect: (blockId: string) => void
    onReorder: (sourceIndex: number, targetIndex: number) => void
    onAdd: () => void
    onShowDrafts: () => void
}

function StructureRow({
    block,
    selected,
    invalid,
    onSelect,
}: {
    block: BuilderBlock
    selected: boolean
    invalid: boolean
    onSelect: () => void
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: block.id,
    })
    const title = blockTitle(block)
    const draft = isPlaceholderBlock(block)

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn(
                'flex items-stretch rounded-[10px] border bg-white dark:bg-slate-900',
                selected
                    ? 'border-brand-200 bg-brand-50 dark:border-brand-800 dark:bg-brand-950/40'
                    : 'border-line dark:border-slate-700',
                isDragging && 'relative z-10 shadow-lg',
            )}
            data-testid="structure-row"
        >
            <span
                aria-hidden="true"
                className="flex cursor-grab items-center pl-2 pr-0.5 text-graphite/60"
            >
                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2.8}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                >
                    <path d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01" />
                </svg>
            </span>
            <button
                type="button"
                onClick={onSelect}
                aria-current={selected ? 'true' : undefined}
                className={cn(
                    'flex min-w-0 flex-1 items-center gap-2.5 py-2 pl-1 pr-2.5 text-left',
                    !block.enabled && 'opacity-50',
                )}
                {...attributes}
                {...listeners}
            >
                <span
                    className={cn(
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
                        selected
                            ? 'bg-white text-brand-700 dark:bg-slate-900'
                            : 'bg-surface-strong text-graphite dark:bg-slate-800 dark:text-slate-300',
                    )}
                >
                    <BlockIcon name={blockIcon(block.type)} />
                </span>
                <span className="flex min-w-0 flex-1 flex-col">
                    <span className="flex min-w-0 items-center gap-1.5">
                        <span className="truncate font-semibold">{title}</span>
                        {invalid ? (
                            <span className="shrink-0 rounded-[5px] bg-danger-50 px-1.5 text-[11px] font-bold text-danger">
                                ошибка
                            </span>
                        ) : null}
                        {!invalid && draft ? (
                            <span className="shrink-0 rounded-[5px] bg-orange-100 px-1.5 text-[11px] font-bold text-orange-800">
                                заготовка
                            </span>
                        ) : null}
                        {!block.enabled ? (
                            <span className="shrink-0 rounded-[5px] bg-surface-strong px-1.5 text-[11px] font-bold text-graphite dark:bg-slate-800 dark:text-slate-300">
                                скрыт
                            </span>
                        ) : null}
                    </span>
                    <span className="truncate text-xs text-graphite dark:text-slate-400">
                        {blockSummary(block)}
                    </span>
                </span>
            </button>
        </li>
    )
}

/** Левая колонка редактора: порядок блоков (перетаскиванием), заготовки и готовность к публикации. */
export function StructurePanel({
    blocks,
    selectedBlockId,
    invalidBlockIds,
    readiness,
    onSelect,
    onReorder,
    onAdd,
    onShowDrafts,
}: StructurePanelProps) {
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }))
    const drafts = blocks.filter((block) => block.enabled && isPlaceholderBlock(block)).length
    const readyCount = readiness.filter((item) => item.ok).length

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event
        if (over === null || active.id === over.id) {
            return
        }
        const sourceIndex = blocks.findIndex((block) => block.id === active.id)
        const targetIndex = blocks.findIndex((block) => block.id === over.id)
        if (sourceIndex !== -1 && targetIndex !== -1) {
            onReorder(sourceIndex, targetIndex)
        }
    }

    return (
        <aside aria-label="Структура страницы" className="flex flex-col gap-3">
            <div className="flex items-baseline justify-between gap-3">
                <h2 className="text-base font-bold">Структура</h2>
                <span className="text-[13px] text-graphite dark:text-slate-400">
                    {blocks.length} {plural(blocks.length, 'блок', 'блока', 'блоков')}
                </span>
            </div>

            {drafts > 0 ? (
                <button
                    type="button"
                    onClick={onShowDrafts}
                    className="rounded-[10px] border border-orange-200 bg-orange-50 px-3 py-2.5 text-left text-[13px] text-orange-800 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-200"
                >
                    <span className="block font-semibold">
                        Заготовки в {drafts} {plural(drafts, 'блоке', 'блоках', 'блоках')}
                    </span>
                    <span className="mt-0.5 block">
                        Тексты из шаблона ещё не заменены. Нажмите, чтобы перейти к первому.
                    </span>
                </button>
            ) : null}

            {blocks.length === 0 ? (
                <p className="rounded-[10px] border border-dashed border-line-strong p-4 text-center text-sm text-graphite dark:border-slate-700 dark:text-slate-400">
                    На странице пока нет блоков. Добавьте первый — например, «Первый экран».
                </p>
            ) : (
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={handleDragEnd}
                >
                    <SortableContext
                        items={blocks.map((block) => block.id)}
                        strategy={verticalListSortingStrategy}
                    >
                        <ol aria-label="Блоки страницы" className="flex flex-col gap-1.5">
                            {blocks.map((block) => (
                                <StructureRow
                                    key={block.id}
                                    block={block}
                                    selected={block.id === selectedBlockId}
                                    invalid={invalidBlockIds.has(block.id)}
                                    onSelect={() => onSelect(block.id)}
                                />
                            ))}
                        </ol>
                    </SortableContext>
                </DndContext>
            )}

            <button
                type="button"
                onClick={onAdd}
                className="flex h-[42px] items-center justify-center gap-1.5 rounded-[10px] border border-dashed border-graphite/60 bg-white font-semibold text-brand-700 transition hover:bg-brand-50 dark:border-slate-600 dark:bg-slate-900 dark:text-brand-400"
            >
                <NavIcon name="plus" size={16} strokeWidth={2} />
                Добавить блок
            </button>

            <section
                aria-label="Готовность к публикации"
                className="mt-2 rounded-xl border border-line bg-surface p-3.5 dark:border-slate-800 dark:bg-slate-900/60"
            >
                <div className="flex items-baseline justify-between gap-2">
                    <h3 className="text-sm font-bold">Перед публикацией</h3>
                    <span className="text-[13px] font-semibold text-graphite dark:text-slate-400">
                        {readyCount} из {readiness.length}
                    </span>
                </div>
                <ul className="mt-2.5 flex flex-col gap-2 text-[13px]">
                    {readiness.map((item) => (
                        <li key={item.id} className="flex items-start gap-2">
                            <span
                                className={cn(
                                    'flex pt-0.5',
                                    item.ok ? 'text-brand-700' : 'text-danger',
                                )}
                                aria-label={item.ok ? 'Готово' : 'Нужно исправить'}
                            >
                                {item.ok ? (
                                    <svg
                                        width="14"
                                        height="14"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth={2.4}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path d="M5.5 12.5l4 4 9-9" />
                                    </svg>
                                ) : (
                                    <svg
                                        width="14"
                                        height="14"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth={2.4}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path d="M6.5 6.5l11 11M17.5 6.5l-11 11" />
                                    </svg>
                                )}
                            </span>
                            <span
                                className={cn(
                                    'flex-1',
                                    item.ok && 'text-graphite dark:text-slate-300',
                                )}
                            >
                                {item.text}
                            </span>
                            {item.action !== undefined && !item.ok ? (
                                <button
                                    type="button"
                                    onClick={item.action.onClick}
                                    className="font-semibold text-brand-700 hover:underline dark:text-brand-400"
                                >
                                    {item.action.label}
                                </button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            </section>
        </aside>
    )
}
