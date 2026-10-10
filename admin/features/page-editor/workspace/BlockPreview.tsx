import type { BuilderBlock } from '../../../modules/page-builder/types'
import { asItems, asString, canonicalType, isRecord, plainText } from './block-kinds'
import { fenceSummary, formatMeters, formatRub } from './fence-summary'
import { sanitizeRichTextHtml } from '../../../shared/lib/sanitize-html'

/**
 * Предпросмотр блока в редакторе: повторяет вёрстку templates/public/blocks/*.html.twig,
 * но без запросов к серверу, поэтому обновляется сразу при вводе.
 */

function EmptyMedia({ title, text }: { title: string; text: string }) {
    return (
        <span className="m-6 flex flex-col items-center gap-1 rounded-xl border-[1.5px] border-dashed border-line-strong px-4 py-7 text-center text-graphite">
            <span className="font-semibold text-graphite">{title}</span>
            <span className="text-[13px]">{text}</span>
        </span>
    )
}

function Heading({
    text,
    className = 'text-2xl font-semibold text-ink dark:text-slate-950',
}: {
    text: string
    className?: string
}) {
    return text === '' ? null : <span className={`mb-5 block ${className}`}>{text}</span>
}

function Hero({ content }: { content: Record<string, unknown> }) {
    const cta = isRecord(content.cta) ? content.cta : {}
    const image = asString(content.image)

    return (
        <span className="relative isolate block overflow-hidden bg-brand-900 px-8 py-12 text-white">
            {image !== '' ? (
                <>
                    <img
                        src={image}
                        alt=""
                        className="absolute inset-0 -z-10 h-full w-full object-cover"
                    />
                    <span className="absolute inset-0 -z-10 bg-brand-950/60" />
                </>
            ) : null}
            {asString(content.title) !== '' ? (
                <span className="block text-3xl font-bold leading-tight">
                    {asString(content.title)}
                </span>
            ) : (
                <span className="block text-3xl font-bold text-brand-200/70">Без заголовка</span>
            )}
            {asString(content.subtitle) !== '' ? (
                <span className="mt-3 block max-w-2xl text-lg text-brand-50">
                    {asString(content.subtitle)}
                </span>
            ) : null}
            {asString(content.text) !== '' ? (
                <span className="mt-3 block max-w-2xl text-brand-50">{asString(content.text)}</span>
            ) : null}
            {asString(cta.label) !== '' ? (
                <span className="mt-6 inline-flex rounded-xl bg-white px-5 py-2.5 font-semibold text-brand-900">
                    {asString(cta.label)}
                </span>
            ) : null}
        </span>
    )
}

function RichText({ html, title }: { html: string; title: string }) {
    return (
        <span className="block px-8 py-7">
            <Heading text={title} />
            {/* Сервер очищает HTML при сохранении, но в канве бывает и ранее сохранённый, и ещё не отправленный HTML. */}
            <span
                className="prose block max-w-none"
                dangerouslySetInnerHTML={{
                    __html:
                        html === ''
                            ? '<p class="text-graphite dark:text-slate-400">Пустой текст</p>'
                            : sanitizeRichTextHtml(html),
                }}
            />
        </span>
    )
}

function Cards({ content, dark }: { content: Record<string, unknown>; dark: boolean }) {
    const items = asItems(content.items)
    if (items.length === 0) {
        return <EmptyMedia title="Нет пунктов" text="Пустой блок на сайт не выводится" />
    }

    return (
        <span
            className={`block px-8 py-7 ${dark ? 'bg-night text-white' : 'bg-surface dark:bg-slate-50'}`}
        >
            <Heading
                text={asString(content.title)}
                className={`text-2xl font-semibold ${dark ? 'text-white' : 'text-ink dark:text-slate-950'}`}
            />
            <span
                className="grid gap-3"
                style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))' }}
            >
                {items.map((item, index) => (
                    <span
                        key={index}
                        className={`block rounded-xl p-4 ${dark ? 'bg-white/10' : 'bg-white shadow-xs'}`}
                    >
                        {dark ? (
                            <span className="block text-xs font-semibold text-brand-200">
                                Шаг {index + 1}
                            </span>
                        ) : null}
                        <span
                            className={`block font-semibold ${dark ? 'mt-1' : 'text-ink dark:text-slate-950'}`}
                        >
                            {asString(item.title)}
                        </span>
                        {asString(item.text) !== '' ? (
                            <span
                                className={`mt-1.5 block text-[13px] leading-5 ${dark ? 'text-line dark:text-slate-200' : 'text-graphite dark:text-slate-600'}`}
                            >
                                {asString(item.text)}
                            </span>
                        ) : null}
                    </span>
                ))}
            </span>
        </span>
    )
}

function Faq({ content }: { content: Record<string, unknown> }) {
    const items = asItems(content.items)
    if (items.length === 0) {
        return <EmptyMedia title="Нет вопросов" text="Пустой блок на сайт не выводится" />
    }

    return (
        <span className="block px-8 py-7">
            <Heading text={asString(content.title) || 'Вопросы и ответы'} />
            <span className="flex flex-col gap-2">
                {items.map((item, index) => (
                    <span
                        key={index}
                        className="flex items-center justify-between gap-3 rounded-xl border border-line dark:border-slate-200 px-4 py-3 font-semibold text-ink dark:text-slate-950"
                    >
                        {asString(item.question) || asString(item.title) || 'Вопрос'}
                        <span aria-hidden="true" className="text-graphite dark:text-slate-400">
                            +
                        </span>
                    </span>
                ))}
            </span>
        </span>
    )
}

function Gallery({ content, portfolio }: { content: Record<string, unknown>; portfolio: boolean }) {
    const items = asItems(content.items).filter(
        (item) =>
            asString(item.src ?? item.image) !== '' || (portfolio && asString(item.title) !== ''),
    )
    if (items.length === 0) {
        return portfolio ? (
            <EmptyMedia
                title="Работы не выбраны"
                text="Видно только в редакторе: пустой блок на сайт не выводится"
            />
        ) : (
            <EmptyMedia
                title="Фото не добавлены"
                text="Видно только в редакторе: пустая галерея на сайт не выводится"
            />
        )
    }

    return (
        <span className="block px-8 py-7">
            <Heading text={asString(content.title) || (portfolio ? 'Наши работы' : '')} />
            <span className="grid grid-cols-3 gap-3">
                {items.map((item, index) => {
                    const src = asString(item.src) || asString(item.image)
                    return (
                        <span
                            key={index}
                            className="block overflow-hidden rounded-xl border border-line dark:border-slate-200"
                        >
                            {src !== '' ? (
                                <img
                                    src={src}
                                    alt={asString(item.alt)}
                                    className="aspect-4/3 w-full object-cover"
                                />
                            ) : (
                                <span className="block aspect-4/3 bg-line dark:bg-slate-200" />
                            )}
                            {portfolio && asString(item.title) !== '' ? (
                                <span className="block p-3 text-sm font-semibold">
                                    {asString(item.title)}
                                </span>
                            ) : null}
                        </span>
                    )
                })}
            </span>
        </span>
    )
}

function Slider({ content }: { content: Record<string, unknown> }) {
    const first = asItems(content.items)[0]
    if (first === undefined) {
        return (
            <EmptyMedia
                title="Нет слайдов"
                text="Видно только в редакторе: пустой слайдер на сайт не выводится"
            />
        )
    }
    const src = asString(first.src)

    return (
        <span className="relative isolate flex min-h-52 flex-col justify-end gap-2 overflow-hidden bg-night dark:bg-slate-700 px-8 py-7 text-white">
            {src !== '' ? (
                <>
                    <img
                        src={src}
                        alt=""
                        className="absolute inset-0 -z-10 h-full w-full object-cover"
                    />
                    <span className="absolute inset-0 -z-10 bg-night/50" />
                </>
            ) : null}
            {asString(first.title) !== '' ? (
                <span className="block text-2xl font-bold">{asString(first.title)}</span>
            ) : null}
            {asString(first.text) !== '' ? (
                <span className="block text-surface dark:text-slate-100">
                    {asString(first.text)}
                </span>
            ) : null}
            <span className="flex gap-1.5" aria-hidden="true">
                {asItems(content.items).map((_, index) => (
                    <span
                        key={index}
                        className={`h-2 w-2 rounded-full ${index === 0 ? 'bg-white' : 'bg-white/50'}`}
                    />
                ))}
            </span>
        </span>
    )
}

function PriceTable({ content }: { content: Record<string, unknown> }) {
    const columns = Array.isArray(content.columns)
        ? content.columns.map((column) =>
              isRecord(column) ? asString(column.label) : asString(column),
          )
        : []
    const rows = Array.isArray(content.rows)
        ? (content.rows.filter(Array.isArray) as unknown[][])
        : []
    if (columns.length === 0 || rows.length === 0) {
        return <EmptyMedia title="Таблица пустая" text="Пустая таблица на сайт не выводится" />
    }

    return (
        <span className="block px-8 py-7">
            <Heading text={asString(content.title)} />
            <span
                className="grid overflow-hidden rounded-xl border border-line dark:border-slate-200 text-sm"
                style={{ gridTemplateColumns: `repeat(${columns.length}, minmax(0, 1fr))` }}
            >
                {columns.map((column, index) => (
                    <span
                        key={`h${index}`}
                        className="bg-surface dark:bg-slate-50 px-3 py-2.5 font-semibold text-ink dark:text-slate-700"
                    >
                        {column}
                    </span>
                ))}
                {rows.map((row, rowIndex) =>
                    columns.map((_, cellIndex) => (
                        <span
                            key={`${rowIndex}-${cellIndex}`}
                            className="border-t border-surface-strong dark:border-slate-100 px-3 py-2.5 text-ink dark:text-slate-700"
                        >
                            {asString(row[cellIndex])}
                        </span>
                    )),
                )}
            </span>
        </span>
    )
}

function Cta({ content }: { content: Record<string, unknown> }) {
    const cta = isRecord(content.cta) ? content.cta : {}

    return (
        <span className="flex flex-wrap items-center justify-between gap-4 bg-brand-800 px-8 py-7 text-white">
            <span className="block">
                <span className="block text-2xl font-bold">
                    {asString(content.title) || 'Без заголовка'}
                </span>
                {asString(content.subtitle) !== '' ? (
                    <span className="mt-1.5 block text-brand-50">{asString(content.subtitle)}</span>
                ) : null}
            </span>
            {asString(cta.label) !== '' ? (
                <span className="inline-flex rounded-xl bg-white px-5 py-2.5 font-semibold text-brand-900">
                    {asString(cta.label)}
                </span>
            ) : null}
        </span>
    )
}

export function LeadFormPreview({ title }: { title: string }) {
    return (
        <span className="block px-8 py-7">
            <span className="block rounded-2xl border border-line dark:border-slate-200 bg-white p-5">
                <span className="block text-xl font-bold text-ink dark:text-slate-950">
                    {title === '' ? 'Получить консультацию' : title}
                </span>
                <span className="mt-4 flex flex-wrap gap-2.5">
                    <span className="flex h-10 min-w-36 flex-1 items-center rounded-lg border border-line-strong dark:border-slate-300 px-3 text-graphite dark:text-slate-400">
                        Имя
                    </span>
                    <span className="flex h-10 min-w-36 flex-1 items-center rounded-lg border border-line-strong dark:border-slate-300 px-3 text-graphite dark:text-slate-400">
                        Телефон
                    </span>
                    <span className="flex h-10 items-center rounded-lg bg-brand-700 px-4 font-semibold text-white">
                        Отправить заявку
                    </span>
                </span>
            </span>
        </span>
    )
}

/** Конфигуратор забора: не картинка, а сводка цен — то, что редактор правит в полях и должен перепроверить. */
export function FenceConfiguratorPreview({ content }: { content: Record<string, unknown> }) {
    const summary = fenceSummary(content)
    const grades = summary.series.flatMap((group) => group.grades)
    if (grades.length === 0) {
        return (
            <EmptyMedia
                title="В конфигураторе нет покрытий"
                text={
                    summary.warnings[0] ??
                    'Добавьте покрытие с ценой — без него блок на сайт не выводится'
                }
            />
        )
    }
    const { example } = summary

    return (
        <span className="block px-8 py-7">
            <Heading text={asString(content.title)} />
            {asString(content.subtitle) !== '' ? (
                <span className="-mt-3 mb-5 block text-sm text-graphite dark:text-slate-600">
                    {asString(content.subtitle)}
                </span>
            ) : null}
            <span className="flex flex-col gap-4">
                {summary.series.map((group, groupIndex) => (
                    <span
                        key={`${group.title}-${groupIndex}`}
                        className="block overflow-hidden rounded-xl border border-line text-sm dark:border-slate-200"
                    >
                        <span className="flex flex-wrap items-baseline gap-x-3 gap-y-1 bg-surface px-3 py-2.5 dark:bg-slate-50">
                            {group.title !== '' ? (
                                <span className="font-semibold text-ink dark:text-slate-950">
                                    {group.title}
                                </span>
                            ) : null}
                            {group.hint !== '' ? (
                                <span className="text-graphite dark:text-slate-600">
                                    {group.hint}
                                </span>
                            ) : null}
                            <span className="ml-auto text-graphite dark:text-slate-600">
                                {group.montage > 0
                                    ? `монтаж ${formatRub(group.montage)}/м²`
                                    : 'монтаж не задан'}
                            </span>
                        </span>
                        <span className="grid grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,1fr))]">
                            {['Покрытие', 'Материал', 'Монтаж', 'Итого за м²'].map((label) => (
                                <span
                                    key={label}
                                    className="border-t border-surface-strong px-3 py-1.5 text-xs font-semibold text-graphite dark:border-slate-100 dark:text-slate-600"
                                >
                                    {label}
                                </span>
                            ))}
                            {group.grades.map((grade, index) => (
                                <span key={`${grade.title}-${index}`} className="contents">
                                    <span className="border-t border-surface-strong px-3 py-2 dark:border-slate-100">
                                        <span className="block font-semibold text-ink dark:text-slate-950">
                                            {grade.title}
                                        </span>
                                        {grade.details !== '' ? (
                                            <span className="block text-xs text-graphite dark:text-slate-600">
                                                {grade.details}
                                            </span>
                                        ) : null}
                                    </span>
                                    <span className="border-t border-surface-strong px-3 py-2 text-ink dark:border-slate-100 dark:text-slate-700">
                                        {grade.price > 0 ? formatRub(grade.price) : '—'}
                                    </span>
                                    <span className="border-t border-surface-strong px-3 py-2 text-ink dark:border-slate-100 dark:text-slate-700">
                                        {group.montage > 0 ? formatRub(group.montage) : '—'}
                                    </span>
                                    <span className="border-t border-surface-strong px-3 py-2 font-semibold text-ink dark:border-slate-100 dark:text-slate-950">
                                        {grade.total > 0 ? formatRub(grade.total) : 'по запросу'}
                                    </span>
                                </span>
                            ))}
                        </span>
                    </span>
                ))}
            </span>
            <span className="mt-4 grid gap-2 text-sm text-graphite dark:text-slate-600">
                <span>
                    <b className="font-semibold text-ink dark:text-slate-950">Высоты: </b>
                    {summary.heights.length > 0
                        ? summary.heights.map(formatMeters).join(' · ')
                        : '1,8 м (не заданы — используется по умолчанию)'}
                    {' · '}
                    <b className="font-semibold text-ink dark:text-slate-950">Длина: </b>
                    {`${summary.length.min}–${summary.length.max} м, по умолчанию ${summary.length.default} м`}
                </span>
                <span className="flex flex-wrap items-center gap-2">
                    <b className="font-semibold text-ink dark:text-slate-950">Цвета: </b>
                    {summary.colors.length === 0 ? 'не заданы' : null}
                    {summary.colors.map((color) => (
                        <span
                            key={color.hex + color.label}
                            className="inline-flex items-center gap-1.5"
                        >
                            <span
                                className="size-4 rounded border border-black/15"
                                style={{ background: color.hex }}
                                aria-hidden="true"
                            />
                            {color.label}
                        </span>
                    ))}
                </span>
                <span>
                    <b className="font-semibold text-ink dark:text-slate-950">Ворота: </b>
                    {summary.gateEnabled
                        ? 'пункт показывается, в смете «по замеру»'
                        : 'не показываются'}
                    {summary.freeItems.length > 0 ? (
                        <>
                            {' · '}
                            <b className="font-semibold text-ink dark:text-slate-950">
                                Бесплатно:{' '}
                            </b>
                            {summary.freeItems.join(', ')}
                        </>
                    ) : null}
                </span>
            </span>
            {example !== null ? (
                <span className="mt-4 block rounded-xl bg-brand-50 px-4 py-3 text-sm text-ink dark:text-slate-950">
                    Пример сметы: {example.title}, {formatMeters(example.height)} × {example.length}{' '}
                    м = {example.area.toLocaleString('ru-RU')} м² →{' '}
                    <b className="font-semibold">≈ {formatRub(example.total)}</b>
                </span>
            ) : null}
            {summary.warnings.length > 0 ? (
                <span className="mt-4 block rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <b className="font-semibold">Проверьте:</b>
                    <span className="mt-1 block">
                        {summary.warnings.map((warning) => (
                            <span key={warning} className="block">
                                • {warning}
                            </span>
                        ))}
                    </span>
                </span>
            ) : null}
        </span>
    )
}

function Generic({ block }: { block: BuilderBlock }) {
    const texts = Object.values(block.content)
        .filter((value): value is string => typeof value === 'string')
        .map(plainText)
        .filter((text) => text !== '')

    return (
        <span className="block px-8 py-6">
            <span className="block rounded-2xl border border-line dark:border-slate-200 bg-white p-5 shadow-xs">
                <span className="block text-lg font-semibold text-ink dark:text-slate-950">
                    {texts[0] ?? block.name ?? block.type}
                </span>
                {texts.slice(1, 3).map((text, index) => (
                    <span
                        key={index}
                        className="mt-2 block text-sm text-graphite dark:text-slate-600"
                    >
                        {text}
                    </span>
                ))}
            </span>
        </span>
    )
}

export function BlockPreview({ block }: { block: BuilderBlock }) {
    const content = block.content
    switch (canonicalType(block.type)) {
        case 'hero.classic':
            return <Hero content={content} />
        case 'rich-text':
            return (
                <RichText
                    html={
                        asString(content.html) ||
                        asString(content.richText) ||
                        asString(content.text)
                    }
                    title={asString(content.title)}
                />
            )
        case 'features':
            return <Cards content={content} dark={false} />
        case 'steps':
            return <Cards content={content} dark />
        case 'faq':
            return <Faq content={content} />
        case 'gallery':
            return <Gallery content={content} portfolio={false} />
        case 'portfolio':
            return <Gallery content={content} portfolio />
        case 'slider':
            return <Slider content={content} />
        case 'price-table':
            return <PriceTable content={content} />
        case 'cta':
            return <Cta content={content} />
        case 'fence-configurator':
            return <FenceConfiguratorPreview content={content} />
        case 'contact-form':
            return <LeadFormPreview title={asString(content.title)} />
        default:
            return <Generic block={block} />
    }
}
