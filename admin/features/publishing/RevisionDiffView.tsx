import { formatDateTime } from './datetime'
import { blockStatusLabels, fieldLabel } from './labels'
import type { BlockDiff, DiffChange, DiffScalar, RevisionDiff, RevisionDiffSide } from './types'

function sideLabel(side: RevisionDiffSide): string {
    if (side.id === 'current') return 'Текущая версия (рабочая)'

    return `v${side.version ?? '?'} · ${formatDateTime(side.createdAt)}`
}

function scalarText(value: DiffScalar): string {
    if (value === null || value === '') return '∅'
    if (typeof value === 'boolean') return value ? 'да' : 'нет'

    return String(value)
}

function ChangeRow({ change }: { change: DiffChange }) {
    return (
        <li className="rounded-lg border border-line px-3 py-2 text-sm" data-testid="diff-change">
            <p className="text-xs font-semibold uppercase tracking-wide text-graphite">
                {fieldLabel(change.field)}
            </p>
            {change.textDiff ? (
                <p className="mt-1 whitespace-pre-wrap break-words text-ink">
                    {change.textDiff.map((part, index) => {
                        if (part.op === 'insert') {
                            return (
                                <ins
                                    key={index}
                                    className="bg-brand-100 text-brand-900 no-underline"
                                >
                                    {part.text}
                                </ins>
                            )
                        }
                        if (part.op === 'delete') {
                            return (
                                <del key={index} className="bg-red-100 text-red-800">
                                    {part.text}
                                </del>
                            )
                        }

                        return <span key={index}>{part.text}</span>
                    })}
                </p>
            ) : (
                <p className="mt-1 break-words text-ink">
                    <del className="bg-red-100 text-red-800">{scalarText(change.before)}</del>
                    {' → '}
                    <ins className="bg-brand-100 text-brand-900 no-underline">
                        {scalarText(change.after)}
                    </ins>
                </p>
            )}
        </li>
    )
}

function ChangeSection({ title, changes }: { title: string; changes: DiffChange[] }) {
    if (changes.length === 0) return null

    return (
        <section className="space-y-2">
            <h4 className="text-sm font-semibold text-ink">{title}</h4>
            <ul className="space-y-2">
                {changes.map((change) => (
                    <ChangeRow key={change.field} change={change} />
                ))}
            </ul>
        </section>
    )
}

function BlockSection({ blocks }: { blocks: BlockDiff[] }) {
    const visible = blocks.filter((block) => block.status !== 'unchanged')
    if (visible.length === 0) return null

    return (
        <section className="space-y-2">
            <h4 className="text-sm font-semibold text-ink">Блоки</h4>
            <ul className="space-y-3">
                {visible.map((block, index) => (
                    <li
                        key={`${block.type}-${index}`}
                        className="rounded-xl border border-line p-3"
                        data-testid="diff-block"
                    >
                        <p className="text-sm font-medium text-ink">
                            {block.name || block.type}{' '}
                            <span className="text-xs font-normal text-graphite">
                                ({block.type})
                            </span>
                            <span className="ml-2 rounded-full bg-surface-strong px-2 py-0.5 text-xs font-medium text-ink">
                                {blockStatusLabels[block.status]}
                            </span>
                        </p>
                        {block.changes.length > 0 && (
                            <ul className="mt-2 space-y-2">
                                {block.changes.map((change) => (
                                    <ChangeRow key={change.field} change={change} />
                                ))}
                            </ul>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    )
}

export function RevisionDiffView({ diff }: { diff: RevisionDiff }) {
    const { summary } = diff

    return (
        <div className="space-y-4">
            <p className="text-sm text-graphite">
                {sideLabel(diff.from)} → {sideLabel(diff.to)}
            </p>
            {!diff.hasChanges ? (
                <p className="rounded-lg bg-surface px-3 py-2 text-sm text-graphite">
                    Различий нет.
                </p>
            ) : (
                <>
                    <p className="text-sm text-graphite" data-testid="diff-summary">
                        Поля: {summary.fields} · SEO: {summary.seo} · Настройки: {summary.settings}{' '}
                        · Блоки: +{summary.blocksAdded} / −{summary.blocksRemoved} / ~
                        {summary.blocksChanged}
                    </p>
                    <ChangeSection title="Страница" changes={diff.fields} />
                    <ChangeSection title="SEO" changes={diff.seo} />
                    <ChangeSection title="Настройки" changes={diff.settings} />
                    <BlockSection blocks={diff.blocks} />
                </>
            )}
        </div>
    )
}
