import { useState } from 'react'
import { SliderEditor } from '../../../modules/page-builder/blocks/slider/SliderEditor'
import type { BuilderBlock, BuilderValidationIssue } from '../../../modules/page-builder/types'
import { NavIcon } from '../../../layouts/nav-icons'
import { cn } from '../../../shared/lib/cn'
import { Button } from '../../../shared/ui'
import { BlockFields } from './BlockFields'
import { BlockIcon } from './BlockIcon'
import { blockHint, blockIcon, blockTitle, blockTypeLabel, isPlaceholderBlock } from './block-kinds'
import { fieldSpecsFor } from './field-specs'

interface BlockInspectorProps {
    block: BuilderBlock | null
    issues: BuilderValidationIssue[]
    showJson: boolean
    onChange: (block: BuilderBlock) => void
    onDuplicate: () => void
    onDelete: () => void
    onSaveAsSection?: () => void
}

function JsonEditor({
    block,
    onChange,
}: {
    block: BuilderBlock
    onChange: (block: BuilderBlock) => void
}) {
    const [content, setContent] = useState(() => JSON.stringify(block.content, null, 2))
    const [settings, setSettings] = useState(() => JSON.stringify(block.settings, null, 2))
    const [error, setError] = useState<string | null>(null)

    const apply = () => {
        try {
            const nextContent = JSON.parse(content) as unknown
            const nextSettings = JSON.parse(settings) as unknown
            if (
                typeof nextContent !== 'object' ||
                nextContent === null ||
                Array.isArray(nextContent) ||
                typeof nextSettings !== 'object' ||
                nextSettings === null ||
                Array.isArray(nextSettings)
            ) {
                throw new Error('not an object')
            }
            setError(null)
            onChange({
                ...block,
                content: nextContent as Record<string, unknown>,
                settings: nextSettings as Record<string, unknown>,
            })
        } catch {
            setError('Невалидный JSON: нужен объект в фигурных скобках.')
        }
    }

    return (
        <div className="flex flex-col gap-2 rounded-[10px] border border-dashed border-line-strong p-3 dark:border-slate-700">
            <p className="text-xs font-semibold uppercase tracking-wide text-graphite">
                Режим JSON для разработчика
            </p>
            <label
                className="text-xs font-medium text-graphite dark:text-slate-300"
                htmlFor={`${block.id}-content-json`}
            >
                Content JSON
            </label>
            <textarea
                id={`${block.id}-content-json`}
                value={content}
                onChange={(event) => setContent(event.target.value)}
                className="min-h-36 w-full rounded-md border border-slate-300 bg-white p-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-900"
            />
            <label
                className="text-xs font-medium text-graphite dark:text-slate-300"
                htmlFor={`${block.id}-settings-json`}
            >
                Settings JSON
            </label>
            <textarea
                id={`${block.id}-settings-json`}
                value={settings}
                onChange={(event) => setSettings(event.target.value)}
                className="min-h-24 w-full rounded-md border border-slate-300 bg-white p-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-900"
            />
            {error !== null ? (
                <p className="text-xs text-red-600" role="alert">
                    {error}
                </p>
            ) : null}
            <Button type="button" size="sm" onClick={apply}>
                Применить JSON
            </Button>
        </div>
    )
}

/** Правая колонка: поля выбранного блока вместо JSON, подсказка из шаблона, видимость и действия с блоком. */
export function BlockInspector({
    block,
    issues,
    showJson,
    onChange,
    onDuplicate,
    onDelete,
    onSaveAsSection,
}: BlockInspectorProps) {
    if (block === null) {
        return (
            <aside
                aria-label="Поля блока"
                className="rounded-[14px] border border-dashed border-line-strong p-8 text-center text-graphite dark:border-slate-700 dark:text-slate-400"
            >
                <p className="font-semibold text-ink dark:text-slate-100">Блок не выбран</p>
                <p className="mt-1 text-sm">
                    Выберите блок в структуре или кликните по секции на странице.
                </p>
            </aside>
        )
    }

    const title = blockTitle(block)
    const hint = blockHint(block)
    const specs = fieldSpecsFor(block.type, block.content)
    const errors = Object.fromEntries(
        issues
            .filter((issue) => issue.path.startsWith('content.'))
            .map((issue) => [issue.path.slice('content.'.length), issue.message]),
    )
    const otherIssues = issues.filter((issue) => !issue.path.startsWith('content.'))
    const touch = (next: Partial<BuilderBlock>) =>
        onChange({
            ...block,
            ...next,
            metadata: { ...block.metadata, updatedAt: new Date().toISOString() },
        })

    return (
        <aside aria-label="Поля блока" className="flex flex-col">
            <div className="flex items-center gap-2.5 border-b border-line pb-3.5 dark:border-slate-800">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[9px] bg-brand-50 text-brand-800 dark:bg-brand-950/50 dark:text-brand-300">
                    <BlockIcon name={blockIcon(block.type)} />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="truncate text-base font-bold">{title}</h2>
                    <p className="truncate text-xs text-graphite dark:text-slate-400">
                        {blockTypeLabel(block.type)}
                    </p>
                </div>
                {isPlaceholderBlock(block) ? (
                    <span className="rounded-md bg-orange-100 px-2 py-0.5 text-[11px] font-bold text-orange-800">
                        заготовка
                    </span>
                ) : null}
            </div>

            <div className="flex flex-col gap-4 pt-4">
                <div className="flex flex-col gap-1.5">
                    <label
                        htmlFor={`${block.id}-name`}
                        className="text-[13px] font-semibold text-graphite dark:text-slate-200"
                    >
                        Название в структуре
                    </label>
                    <input
                        id={`${block.id}-name`}
                        value={block.name === '' ? '' : title}
                        onChange={(event) => touch({ name: event.target.value })}
                        className="h-9 rounded-lg border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-900"
                    />
                    <p className="text-xs text-graphite dark:text-slate-400">
                        Видно только в редакторе, на сайт не выводится
                    </p>
                </div>

                {hint !== null ? (
                    <div className="flex gap-2.5 rounded-[10px] bg-brand-50 px-3 py-2.5 text-[13px] text-brand-800 dark:bg-brand-950/40 dark:text-brand-200">
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth={1.8}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            aria-hidden="true"
                            className="mt-px shrink-0"
                        >
                            <path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2V16h5v-.1c0-.8.4-1.5 1-2A6 6 0 0 0 12 3z" />
                        </svg>
                        <span>
                            <b>Что заполнить.</b> {hint}
                        </span>
                    </div>
                ) : null}

                {otherIssues.length > 0 ? (
                    <ul
                        className="rounded-[10px] border border-red-200 bg-red-50 p-3 text-[13px] text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
                        role="alert"
                    >
                        {otherIssues.map((issue) => (
                            <li key={`${issue.path}-${issue.message}`}>{issue.message}</li>
                        ))}
                    </ul>
                ) : null}

                {specs.length > 0 ? (
                    <BlockFields
                        specs={specs}
                        value={block.content}
                        errors={errors}
                        resetKey={block.id}
                        onChange={(content) => touch({ content })}
                    />
                ) : (
                    <p className="text-sm text-graphite dark:text-slate-400">
                        У этого блока нет полей для заполнения.
                    </p>
                )}

                {block.type === 'slider' ? (
                    <SliderEditor block={block} onChange={onChange} />
                ) : null}

                {showJson ? (
                    <JsonEditor
                        key={`${block.id}-${block.metadata.updatedAt}`}
                        block={block}
                        onChange={onChange}
                    />
                ) : null}

                <div className="flex items-center justify-between gap-3 border-t border-surface-strong pt-3.5 dark:border-slate-800">
                    <span className="flex flex-col">
                        <span className="font-semibold">Показывать на сайте</span>
                        <span className="text-xs text-graphite dark:text-slate-400">
                            Скрытый блок остаётся в странице, но посетители его не видят
                        </span>
                    </span>
                    <button
                        type="button"
                        role="switch"
                        aria-checked={block.enabled}
                        aria-label="Показывать на сайте"
                        onClick={() => touch({ enabled: !block.enabled })}
                        className={cn(
                            'flex h-[26px] w-11 shrink-0 rounded-full p-[3px] transition',
                            block.enabled
                                ? 'justify-end bg-brand-700'
                                : 'justify-start bg-line-strong dark:bg-slate-700',
                        )}
                    >
                        <span className="h-5 w-5 rounded-full bg-white" />
                    </button>
                </div>

                <div className="flex flex-col gap-1.5">
                    <label
                        htmlFor={`${block.id}-audience`}
                        className="text-[13px] font-semibold text-graphite dark:text-slate-200"
                    >
                        Кому показывать
                    </label>
                    <select
                        id={`${block.id}-audience`}
                        value={
                            typeof block.settings.audience === 'string'
                                ? block.settings.audience
                                : ''
                        }
                        onChange={(event) =>
                            touch({ settings: { ...block.settings, audience: event.target.value } })
                        }
                        className="h-9 rounded-lg border border-line-strong bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <option value="">Всем посетителям</option>
                        <option value="b2c">Только частным клиентам</option>
                        <option value="b2b">Только бизнесу</option>
                    </select>
                    <p className="text-xs text-graphite dark:text-slate-400">
                        Если на странице есть блоки «Только бизнесу», над ними появится
                        переключатель «Частным клиентам / Бизнесу»
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={onDuplicate}
                        className="inline-flex h-[38px] items-center gap-1.5 rounded-[9px] border border-line-strong bg-white px-3 text-[13px] font-semibold text-graphite hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth={1.8}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            aria-hidden="true"
                        >
                            <rect x="8.5" y="8.5" width="11" height="11" rx="2" />
                            <path d="M15.5 8.5V6a1.5 1.5 0 0 0-1.5-1.5H6A1.5 1.5 0 0 0 4.5 6v8A1.5 1.5 0 0 0 6 15.5h2.5" />
                        </svg>
                        Дублировать
                    </button>
                    {onSaveAsSection !== undefined ? (
                        <button
                            type="button"
                            onClick={onSaveAsSection}
                            className="inline-flex h-[38px] items-center rounded-[9px] border border-line-strong bg-white px-3 text-[13px] font-semibold text-graphite hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        >
                            Сохранить как шаблон секции
                        </button>
                    ) : null}
                    <button
                        type="button"
                        onClick={onDelete}
                        className="inline-flex h-[38px] items-center gap-1.5 rounded-[9px] px-3 text-[13px] font-semibold text-danger hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                    >
                        <NavIcon name="close" size={16} />
                        Удалить блок
                    </button>
                </div>
            </div>
        </aside>
    )
}
