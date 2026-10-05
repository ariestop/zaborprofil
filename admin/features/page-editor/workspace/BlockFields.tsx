import { useId, useState, type ReactNode } from 'react'
import { MediaPicker } from '../../media/MediaPicker'
import { MediaPickerDialog } from '../../media/MediaPickerDialog'
import { RichTextEditor } from '../../rich-text/RichTextEditor'
import { NavIcon } from '../../../layouts/nav-icons'
import { cn } from '../../../shared/lib/cn'
import { Input, Textarea } from '../../../shared/ui'
import { asItems, asString, isPlaceholderText, isRecord } from './block-kinds'
import { LEAD_FORM_ANCHOR, isLeadFormLink, type FieldSpec } from './field-specs'

type Content = Record<string, unknown>

interface BlockFieldsProps {
    specs: FieldSpec[]
    value: Content
    onChange: (next: Content) => void
    /** Ошибки валидации: путь внутри content («title», «items.0.question») → сообщение. */
    errors: Record<string, string>
    /** Меняется при выборе другого блока: сбрасывает неконтролируемый редактор текста. */
    resetKey: string
}

const fieldLabelClass = 'text-[13px] font-semibold text-graphite dark:text-slate-200'
const helpClass = 'text-xs text-graphite dark:text-slate-400'
const placeholderBorder = 'border-amber-400 focus:ring-amber-400 dark:border-amber-500'
const errorBorder = 'border-red-500 focus:ring-red-500'

function FieldShell({
    id,
    label,
    required,
    help,
    error,
    draft,
    children,
}: {
    id?: string
    label: string
    required?: boolean
    help?: string
    error?: string
    draft?: boolean
    children: ReactNode
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <label htmlFor={id} className={fieldLabelClass}>
                {label}
                {required === true ? (
                    <span className="text-red-600" aria-hidden="true">
                        {' '}
                        *
                    </span>
                ) : null}
            </label>
            {children}
            {error !== undefined ? (
                <p className="text-xs text-red-600" role="alert">
                    {error}
                </p>
            ) : null}
            {error === undefined && draft === true ? (
                <p className="text-xs text-amber-700 dark:text-amber-300">
                    Текст из шаблона — замените на свой
                </p>
            ) : null}
            {error === undefined && draft !== true && help !== undefined ? (
                <p className={helpClass}>{help}</p>
            ) : null}
        </div>
    )
}

function TextField({
    spec,
    value,
    onChange,
    error,
}: {
    spec: Extract<FieldSpec, { kind: 'text' }>
    value: string
    onChange: (next: string) => void
    error?: string
}) {
    const id = useId()
    const draft = isPlaceholderText(value)
    const help =
        spec.maxLength !== undefined && spec.help !== undefined
            ? `${spec.help} · ${value.length} / ${spec.maxLength}`
            : spec.help

    return (
        <FieldShell
            id={id}
            label={spec.label}
            required={spec.required}
            help={help}
            error={error}
            draft={draft}
        >
            <Input
                id={id}
                value={value}
                placeholder={spec.placeholder}
                aria-invalid={error !== undefined}
                className={cn(draft && placeholderBorder, error !== undefined && errorBorder)}
                onChange={(event) => onChange(event.target.value)}
            />
        </FieldShell>
    )
}

function TextareaField({
    spec,
    value,
    onChange,
    error,
}: {
    spec: Extract<FieldSpec, { kind: 'textarea' }>
    value: string
    onChange: (next: string) => void
    error?: string
}) {
    const id = useId()
    const draft = isPlaceholderText(value)

    return (
        <FieldShell
            id={id}
            label={spec.label}
            required={spec.required}
            help={spec.help}
            error={error}
            draft={draft}
        >
            <Textarea
                id={id}
                value={value}
                rows={spec.rows ?? 3}
                aria-invalid={error !== undefined}
                className={cn(
                    'min-h-0',
                    draft && placeholderBorder,
                    error !== undefined && errorBorder,
                )}
                onChange={(event) => onChange(event.target.value)}
            />
        </FieldShell>
    )
}

function SelectField({
    spec,
    value,
    onChange,
}: {
    spec: Extract<FieldSpec, { kind: 'select' }>
    value: string
    onChange: (next: string) => void
}) {
    const id = useId()

    return (
        <FieldShell id={id} label={spec.label} help={spec.help}>
            <select
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="h-10 w-full rounded-lg border border-line-strong bg-white px-3 text-sm text-ink dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            >
                {spec.options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </FieldShell>
    )
}

function LinkField({
    label,
    value,
    onChange,
}: {
    label: string
    value: string
    onChange: (next: string) => void
}) {
    const selectId = useId()
    const inputId = useId()
    const lead = isLeadFormLink(value)

    return (
        <div className="flex flex-col gap-1.5">
            <label htmlFor={selectId} className={fieldLabelClass}>
                {label}
            </label>
            <select
                id={selectId}
                value={lead ? 'lead' : 'custom'}
                onChange={(event) =>
                    onChange(event.target.value === 'lead' ? LEAD_FORM_ANCHOR : '')
                }
                className="h-10 w-full rounded-lg border border-line-strong bg-white px-3 text-sm text-ink dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            >
                <option value="lead">К форме заявки на этой странице</option>
                <option value="custom">На другую страницу или адрес</option>
            </select>
            {lead ? null : (
                <Input
                    id={inputId}
                    aria-label={`${label}: адрес`}
                    value={value}
                    placeholder="/ceny/ или https://…"
                    onChange={(event) => onChange(event.target.value)}
                />
            )}
        </div>
    )
}

function ImageField({
    spec,
    value,
    onChange,
}: {
    spec: Extract<FieldSpec, { kind: 'image' }>
    value: Content
    onChange: (next: Content) => void
}) {
    const altId = useId()
    const src = asString(value[spec.key])
    const altKey = spec.altKey

    return (
        <div className="flex flex-col gap-2">
            <MediaPicker
                label={spec.label}
                value={src}
                onChange={(nextSrc, asset) => {
                    const next: Content = { ...value, [spec.key]: nextSrc }
                    if (altKey !== undefined && asString(value[altKey]) === '' && asset?.alt) {
                        next[altKey] = asset.alt
                    }
                    onChange(next)
                }}
            />
            {altKey !== undefined && src !== '' ? (
                <div className="flex flex-col gap-1.5">
                    <label htmlFor={altId} className={fieldLabelClass}>
                        Подпись к фото (alt)
                    </label>
                    <Input
                        id={altId}
                        value={asString(value[altKey])}
                        placeholder="Что на фото: «Забор из профнастила, 1,8 м»"
                        onChange={(event) => onChange({ ...value, [altKey]: event.target.value })}
                    />
                </div>
            ) : null}
        </div>
    )
}

const iconButton =
    'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-graphite transition hover:bg-surface-strong disabled:opacity-30 dark:text-slate-400 dark:hover:bg-slate-800'
const addButton =
    'inline-flex h-9 items-center justify-center gap-1.5 rounded-[9px] bg-brand-50 px-3 text-[13px] font-semibold text-brand-800 transition hover:bg-brand-100 dark:bg-brand-900/40 dark:text-brand-200'

function move<T>(list: T[], from: number, to: number): T[] {
    if (to < 0 || to >= list.length) {
        return list
    }
    const next = list.slice()
    const [item] = next.splice(from, 1)
    if (item !== undefined) {
        next.splice(to, 0, item)
    }

    return next
}

function ItemsField({
    spec,
    value,
    onChange,
    errors,
    path,
    resetKey,
}: {
    spec: Extract<FieldSpec, { kind: 'items' }>
    value: unknown
    onChange: (next: Content[]) => void
    errors: Record<string, string>
    path: string
    resetKey: string
}) {
    const [pickerOpen, setPickerOpen] = useState(false)
    const items = asItems(value)
    const media = spec.media

    return (
        <div className="flex flex-col gap-2">
            <p className={fieldLabelClass}>{spec.label}</p>
            {items.length === 0 ? <p className={helpClass}>Пока пусто.</p> : null}
            <ol className="flex flex-col gap-2">
                {items.map((item, index) => (
                    // Порядок — единственный идентификатор элемента: у пунктов нет собственных id.
                    <li
                        key={index}
                        className="rounded-[10px] border border-line p-2.5 dark:border-slate-700"
                    >
                        <div className="mb-2 flex items-center gap-1">
                            <span className="flex-1 text-xs font-semibold text-graphite dark:text-slate-400">
                                {spec.itemLabel} {index + 1}
                            </span>
                            <button
                                type="button"
                                className={iconButton}
                                aria-label={`${spec.itemLabel} ${index + 1}: выше`}
                                disabled={index === 0}
                                onClick={() => onChange(move(items, index, index - 1))}
                            >
                                <NavIcon
                                    name="chevron"
                                    size={16}
                                    style={{ transform: 'rotate(180deg)' }}
                                />
                            </button>
                            <button
                                type="button"
                                className={iconButton}
                                aria-label={`${spec.itemLabel} ${index + 1}: ниже`}
                                disabled={index === items.length - 1}
                                onClick={() => onChange(move(items, index, index + 1))}
                            >
                                <NavIcon name="chevron" size={16} />
                            </button>
                            <button
                                type="button"
                                className={cn(iconButton, 'hover:text-red-600')}
                                aria-label={`Удалить: ${spec.itemLabel.toLowerCase()} ${index + 1}`}
                                onClick={() =>
                                    onChange(items.filter((_, position) => position !== index))
                                }
                            >
                                <NavIcon name="close" size={16} />
                            </button>
                        </div>
                        <BlockFields
                            specs={spec.fields}
                            value={item}
                            resetKey={`${resetKey}:${index}`}
                            errors={Object.fromEntries(
                                Object.entries(errors)
                                    .filter(([key]) => key.startsWith(`${path}.${index}.`))
                                    .map(([key, message]) => [
                                        key.slice(`${path}.${index}.`.length),
                                        message,
                                    ]),
                            )}
                            onChange={(nextItem) =>
                                onChange(
                                    items.map((current, position) =>
                                        position === index ? nextItem : current,
                                    ),
                                )
                            }
                        />
                    </li>
                ))}
            </ol>
            <button
                type="button"
                className={addButton}
                onClick={() =>
                    media === undefined
                        ? onChange([...items, { ...spec.newItem }])
                        : setPickerOpen(true)
                }
            >
                <NavIcon name="plus" size={16} strokeWidth={2} />
                {spec.addLabel}
            </button>
            {media !== undefined ? (
                <MediaPickerDialog
                    open={pickerOpen}
                    onOpenChange={setPickerOpen}
                    onSelect={(asset) => {
                        const item: Content = { ...spec.newItem, [media]: asset.publicPath }
                        if ('alt' in item) item.alt = asset.alt ?? ''
                        if ('title' in item && media === 'image') item.title = asset.title ?? ''
                        onChange([...items, item])
                    }}
                />
            ) : null}
        </div>
    )
}

function rowsOf(value: unknown): string[][] {
    return Array.isArray(value)
        ? value.filter(Array.isArray).map((row) => (row as unknown[]).map((cell) => asString(cell)))
        : []
}

function columnsOf(value: unknown): string[] {
    return Array.isArray(value)
        ? value.map((column) => (isRecord(column) ? asString(column.label) : asString(column)))
        : []
}

function TableField({
    label,
    value,
    onChange,
}: {
    label: string
    value: Content
    onChange: (next: Content) => void
}) {
    const [editColumns, setEditColumns] = useState(false)
    const columns = columnsOf(value.columns)
    const rows = rowsOf(value.rows)
    const width = Math.max(columns.length, 1)
    const grid = { gridTemplateColumns: `repeat(${width}, minmax(0, 1fr)) 32px` }

    const setRows = (next: string[][]) => onChange({ ...value, rows: next })
    const setColumns = (next: string[]) =>
        onChange({
            ...value,
            columns: next,
            rows: rows.map((row) => next.map((_, index) => row[index] ?? '')),
        })

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-baseline justify-between gap-2">
                <p className={fieldLabelClass}>{label}</p>
                <button
                    type="button"
                    className="text-xs font-semibold text-brand-700 hover:underline dark:text-brand-400"
                    aria-expanded={editColumns}
                    onClick={() => setEditColumns(!editColumns)}
                >
                    {editColumns ? 'Готово' : `Колонки: ${columns.join(' · ') || 'не заданы'}`}
                </button>
            </div>
            {editColumns ? (
                <div className="flex flex-col gap-1.5 rounded-[10px] bg-surface p-2.5 dark:bg-slate-800/60">
                    {columns.map((column, index) => (
                        <div key={index} className="flex items-center gap-1.5">
                            <Input
                                aria-label={`Колонка ${index + 1}`}
                                value={column}
                                onChange={(event) =>
                                    setColumns(
                                        columns.map((current, position) =>
                                            position === index ? event.target.value : current,
                                        ),
                                    )
                                }
                            />
                            <button
                                type="button"
                                className={iconButton}
                                aria-label={`Удалить колонку ${index + 1}`}
                                disabled={columns.length <= 1}
                                onClick={() =>
                                    setColumns(columns.filter((_, position) => position !== index))
                                }
                            >
                                <NavIcon name="close" size={16} />
                            </button>
                        </div>
                    ))}
                    <button
                        type="button"
                        className={addButton}
                        onClick={() => setColumns([...columns, ''])}
                    >
                        <NavIcon name="plus" size={16} strokeWidth={2} />
                        Добавить колонку
                    </button>
                </div>
            ) : null}
            <div
                className="grid gap-1.5 text-xs font-semibold text-graphite dark:text-slate-400"
                style={grid}
            >
                {columns.map((column, index) => (
                    <span key={`${column}-${index}`} className="truncate">
                        {column}
                    </span>
                ))}
                <span />
            </div>
            {rows.map((row, rowIndex) => (
                <div key={rowIndex} className="grid items-center gap-1.5" style={grid}>
                    {Array.from({ length: width }, (_, cellIndex) => {
                        const cell = row[cellIndex] ?? ''
                        return (
                            <Input
                                key={cellIndex}
                                aria-label={`${columns[cellIndex] ?? 'Ячейка'}, строка ${rowIndex + 1}`}
                                value={cell}
                                className={cn('px-2', isPlaceholderText(cell) && placeholderBorder)}
                                onChange={(event) =>
                                    setRows(
                                        rows.map((current, position) =>
                                            position === rowIndex
                                                ? Array.from({ length: width }, (__, index) =>
                                                      index === cellIndex
                                                          ? event.target.value
                                                          : (current[index] ?? ''),
                                                  )
                                                : current,
                                        ),
                                    )
                                }
                            />
                        )
                    })}
                    <button
                        type="button"
                        className={cn(iconButton, 'hover:text-red-600')}
                        aria-label={`Удалить строку ${rowIndex + 1}`}
                        onClick={() => setRows(rows.filter((_, position) => position !== rowIndex))}
                    >
                        <NavIcon name="close" size={16} />
                    </button>
                </div>
            ))}
            <button
                type="button"
                className={addButton}
                onClick={() => setRows([...rows, Array.from({ length: width }, () => '')])}
            >
                <NavIcon name="plus" size={16} strokeWidth={2} />
                Добавить строку
            </button>
            {rows.some((row) => row.some(isPlaceholderText)) ? (
                <p className="text-xs text-amber-700 dark:text-amber-300">
                    Жёлтая рамка — текст из шаблона, его нужно заменить
                </p>
            ) : null}
        </div>
    )
}

function StringsField({
    spec,
    value,
    onChange,
}: {
    spec: Extract<FieldSpec, { kind: 'strings' }>
    value: unknown
    onChange: (next: string[]) => void
}) {
    const list = Array.isArray(value) ? value.map((item) => asString(item)) : []

    return (
        <div className="flex flex-col gap-1.5">
            <p className={fieldLabelClass}>{spec.label}</p>
            {list.map((item, index) => (
                <div key={index} className="flex items-center gap-1.5">
                    <Input
                        aria-label={`${spec.label}, ${index + 1}`}
                        value={item}
                        onChange={(event) =>
                            onChange(
                                list.map((current, position) =>
                                    position === index ? event.target.value : current,
                                ),
                            )
                        }
                    />
                    <button
                        type="button"
                        className={iconButton}
                        aria-label={`Удалить: ${spec.label.toLowerCase()} ${index + 1}`}
                        onClick={() => onChange(list.filter((_, position) => position !== index))}
                    >
                        <NavIcon name="close" size={16} />
                    </button>
                </div>
            ))}
            <button type="button" className={addButton} onClick={() => onChange([...list, ''])}>
                <NavIcon name="plus" size={16} strokeWidth={2} />
                {spec.addLabel}
            </button>
        </div>
    )
}

/** Форма полей блока по описанию FieldSpec. Рекурсивна: группы и элементы списков рендерятся той же формой. */
export function BlockFields({ specs, value, onChange, errors, resetKey }: BlockFieldsProps) {
    const set = (key: string, next: unknown) => onChange({ ...value, [key]: next })

    return (
        <div className="flex flex-col gap-4">
            {specs.map((spec, index) => {
                switch (spec.kind) {
                    case 'text':
                        return (
                            <TextField
                                key={spec.key}
                                spec={spec}
                                value={asString(value[spec.key])}
                                error={errors[spec.key]}
                                onChange={(next) => set(spec.key, next)}
                            />
                        )
                    case 'textarea':
                        return (
                            <TextareaField
                                key={spec.key}
                                spec={spec}
                                value={asString(value[spec.key])}
                                error={errors[spec.key]}
                                onChange={(next) => set(spec.key, next)}
                            />
                        )
                    case 'richtext':
                        return (
                            <div key={spec.key} className="flex flex-col gap-1.5">
                                <p className={fieldLabelClass}>{spec.label}</p>
                                <div className="overflow-hidden rounded-[10px] border border-line-strong dark:border-slate-700">
                                    <RichTextEditor
                                        key={resetKey}
                                        initialValue={asString(value[spec.key])}
                                        onChange={(next) => set(spec.key, next)}
                                    />
                                </div>
                                {errors[spec.key] !== undefined ? (
                                    <p className="text-xs text-red-600" role="alert">
                                        {errors[spec.key]}
                                    </p>
                                ) : null}
                                {isPlaceholderText(asString(value[spec.key])) ? (
                                    <p className="text-xs text-amber-700 dark:text-amber-300">
                                        В тексте остались подсказки из шаблона — замените их
                                    </p>
                                ) : null}
                            </div>
                        )
                    case 'image':
                        return (
                            <ImageField
                                key={spec.key}
                                spec={spec}
                                value={value}
                                onChange={onChange}
                            />
                        )
                    case 'link':
                        return (
                            <LinkField
                                key={spec.key}
                                label={spec.label}
                                value={asString(value[spec.key])}
                                onChange={(next) => set(spec.key, next)}
                            />
                        )
                    case 'group': {
                        const group = isRecord(value[spec.key]) ? (value[spec.key] as Content) : {}
                        return (
                            <fieldset
                                key={spec.key}
                                className="flex flex-col gap-3 rounded-[10px] border border-line p-3 dark:border-slate-700"
                            >
                                {spec.label !== undefined ? (
                                    <legend className={cn(fieldLabelClass, 'px-1')}>
                                        {spec.label}
                                    </legend>
                                ) : null}
                                <BlockFields
                                    specs={spec.fields}
                                    value={group}
                                    resetKey={`${resetKey}:${spec.key}`}
                                    errors={Object.fromEntries(
                                        Object.entries(errors)
                                            .filter(([key]) => key.startsWith(`${spec.key}.`))
                                            .map(([key, message]) => [
                                                key.slice(spec.key.length + 1),
                                                message,
                                            ]),
                                    )}
                                    onChange={(next) => set(spec.key, next)}
                                />
                            </fieldset>
                        )
                    }
                    case 'items':
                        return (
                            <ItemsField
                                key={spec.key}
                                spec={spec}
                                value={value[spec.key]}
                                errors={errors}
                                path={spec.key}
                                resetKey={resetKey}
                                onChange={(next) => set(spec.key, next)}
                            />
                        )
                    case 'table':
                        return (
                            <TableField
                                key={`table-${index}`}
                                label={spec.label}
                                value={value}
                                onChange={onChange}
                            />
                        )
                    case 'strings':
                        return (
                            <StringsField
                                key={spec.key}
                                spec={spec}
                                value={value[spec.key]}
                                onChange={(next) => set(spec.key, next)}
                            />
                        )
                    case 'checkbox':
                        return (
                            <label key={spec.key} className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={value[spec.key] === true}
                                    onChange={(event) => set(spec.key, event.target.checked)}
                                    className="h-4 w-4 accent-brand-700"
                                />
                                {spec.label}
                            </label>
                        )
                    case 'number':
                        return (
                            <FieldShell key={spec.key} label={spec.label} help={spec.help}>
                                <Input
                                    type="number"
                                    step={spec.step}
                                    aria-label={spec.label}
                                    value={
                                        typeof value[spec.key] === 'number'
                                            ? String(value[spec.key])
                                            : ''
                                    }
                                    onChange={(event) =>
                                        set(
                                            spec.key,
                                            event.target.value === ''
                                                ? 0
                                                : Number(event.target.value),
                                        )
                                    }
                                />
                            </FieldShell>
                        )
                    case 'select':
                        return (
                            <SelectField
                                key={spec.key}
                                spec={spec}
                                value={asString(value[spec.key])}
                                onChange={(next) => set(spec.key, next)}
                            />
                        )
                    case 'note':
                        return (
                            <p
                                key={`note-${index}`}
                                className="rounded-[10px] bg-surface p-3 text-[13px] text-graphite dark:bg-slate-800/60 dark:text-slate-300"
                            >
                                {spec.text}
                            </p>
                        )
                    default:
                        return null
                }
            })}
        </div>
    )
}
