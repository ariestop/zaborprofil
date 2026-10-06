import type { BuilderBlockType } from '../../../modules/page-builder/types'
import { canonicalType, isRecord } from './block-kinds'

/**
 * Описание полей блока для формы редактора. Для основных видов поля заданы вручную (порядок, подписи, подсказки),
 * для остальных — выводятся из текущего содержимого блока, чтобы любой блок редактировался без JSON.
 */
export type FieldSpec =
    | {
          kind: 'text'
          key: string
          label: string
          required?: boolean
          help?: string
          placeholder?: string
          maxLength?: number
      }
    | {
          kind: 'textarea'
          key: string
          label: string
          required?: boolean
          help?: string
          rows?: number
      }
    | { kind: 'richtext'; key: string; label: string }
    | { kind: 'image'; key: string; label: string; altKey?: string }
    | { kind: 'link'; key: string; label: string }
    | { kind: 'group'; key: string; label?: string; fields: FieldSpec[] }
    | {
          kind: 'items'
          key: string
          label: string
          itemLabel: string
          addLabel: string
          fields: FieldSpec[]
          newItem: Record<string, unknown>
          media?: 'src' | 'image'
      }
    | { kind: 'table'; label: string }
    | { kind: 'strings'; key: string; label: string; addLabel: string }
    | { kind: 'checkbox'; key: string; label: string }
    | { kind: 'number'; key: string; label: string; help?: string; step?: number }
    | {
          kind: 'select'
          key: string
          label: string
          options: Array<{ value: string; label: string }>
          help?: string
      }
    | { kind: 'note'; text: string }

const STEP_ICONS = [
    { value: '', label: 'Без иконки (номер)' },
    { value: 'measure', label: 'Замер' },
    { value: 'truck', label: 'Доставка' },
    { value: 'fence', label: 'Монтаж' },
    { value: 'shield', label: 'Гарантия' },
]

const FENCE_PATTERNS = [
    { value: 'profnastil', label: 'Профнастил' },
    { value: 'profnastil-wide', label: 'Профнастил с широкой волной' },
    { value: 'shtaketnik', label: 'Евроштакетник' },
    { value: 'jaluzi', label: 'Жалюзи' },
    { value: 'setka', label: '3D сетка' },
]

const optionalTitle: FieldSpec = {
    kind: 'text',
    key: 'title',
    label: 'Заголовок блока',
    help: 'Необязательно: без заголовка блок идёт сразу после предыдущего.',
}

const SPECS: Partial<Record<BuilderBlockType, FieldSpec[]>> = {
    'hero.classic': [
        {
            kind: 'text',
            key: 'title',
            label: 'Заголовок',
            required: true,
            maxLength: 120,
            help: 'Выгода для клиента в одной строке',
        },
        { kind: 'textarea', key: 'subtitle', label: 'Подзаголовок', rows: 2 },
        {
            kind: 'textarea',
            key: 'text',
            label: 'Дополнительный текст',
            rows: 2,
            help: 'Необязательно',
        },
        {
            kind: 'group',
            key: 'cta',
            label: 'Кнопка',
            fields: [
                {
                    kind: 'text',
                    key: 'label',
                    label: 'Текст кнопки',
                    help: 'Пустой текст — кнопки не будет',
                },
                { kind: 'link', key: 'href', label: 'Кнопка ведёт' },
            ],
        },
        { kind: 'image', key: 'image', altKey: 'imageAlt', label: 'Фоновое фото' },
    ],
    'rich-text': [{ kind: 'richtext', key: 'html', label: 'Текст' }],
    features: [
        optionalTitle,
        {
            kind: 'items',
            key: 'items',
            label: 'Пункты',
            itemLabel: 'Пункт',
            addLabel: 'Добавить пункт',
            newItem: { title: '', text: '' },
            fields: [
                { kind: 'text', key: 'title', label: 'Заголовок' },
                { kind: 'textarea', key: 'text', label: 'Пояснение', rows: 2 },
            ],
        },
    ],
    steps: [
        optionalTitle,
        {
            kind: 'items',
            key: 'items',
            label: 'Шаги',
            itemLabel: 'Шаг',
            addLabel: 'Добавить шаг',
            newItem: { title: '', text: '', icon: '' },
            fields: [
                { kind: 'text', key: 'title', label: 'Название шага' },
                { kind: 'textarea', key: 'text', label: 'Что происходит', rows: 2 },
                {
                    kind: 'select',
                    key: 'icon',
                    label: 'Иконка',
                    help: 'Фирменная иконка вместо номера шага',
                    options: STEP_ICONS,
                },
            ],
        },
    ],
    'fence-configurator': [
        { kind: 'text', key: 'title', label: 'Заголовок', required: true },
        { kind: 'textarea', key: 'subtitle', label: 'Подзаголовок', rows: 2 },
        {
            kind: 'note',
            text: 'Сумма = длина × цена за метр материала × коэффициент высоты + ворота. Цены — за погонный метр забора под ключ; без них посетитель увидит неверный расчёт.',
        },
        {
            kind: 'items',
            key: 'materials',
            label: 'Материалы',
            itemLabel: 'Материал',
            addLabel: 'Добавить материал',
            newItem: { title: '', pricePerMeter: 0, pattern: 'profnastil' },
            fields: [
                { kind: 'text', key: 'title', label: 'Название', required: true },
                {
                    kind: 'number',
                    key: 'pricePerMeter',
                    label: 'Цена за погонный метр, ₽',
                    help: 'Для высоты с коэффициентом 1',
                },
                {
                    kind: 'select',
                    key: 'pattern',
                    label: 'Рисунок на картинке',
                    options: FENCE_PATTERNS,
                },
            ],
        },
        {
            kind: 'items',
            key: 'heights',
            label: 'Высоты',
            itemLabel: 'Высота',
            addLabel: 'Добавить высоту',
            newItem: { label: '', factor: 1 },
            fields: [
                { kind: 'text', key: 'label', label: 'Подпись', help: 'Например, «1,8 м»' },
                {
                    kind: 'number',
                    key: 'factor',
                    label: 'Коэффициент к цене',
                    step: 0.01,
                    help: '1 — базовая высота; 1,12 — на 12% дороже',
                },
            ],
        },
        {
            kind: 'items',
            key: 'colors',
            label: 'Цвета RAL',
            itemLabel: 'Цвет',
            addLabel: 'Добавить цвет',
            newItem: { ral: '', name: '', hex: '' },
            fields: [
                { kind: 'text', key: 'ral', label: 'Номер RAL', help: 'Например, 6005' },
                { kind: 'text', key: 'name', label: 'Название', help: 'Например, «зелёный мох»' },
                {
                    kind: 'text',
                    key: 'hex',
                    label: 'Цвет на экране (HEX)',
                    help: 'Приближение для картинки, например #2D7F27',
                },
            ],
        },
        {
            kind: 'group',
            key: 'gate',
            label: 'Ворота',
            fields: [
                {
                    kind: 'text',
                    key: 'label',
                    label: 'Подпись',
                    help: 'Например, «Ворота и калитка»',
                },
                {
                    kind: 'number',
                    key: 'price',
                    label: 'Цена, ₽',
                    help: '0 — пункт не показывается',
                },
            ],
        },
        {
            kind: 'group',
            key: 'length',
            label: 'Длина забора, м',
            fields: [
                { kind: 'number', key: 'min', label: 'Минимум' },
                { kind: 'number', key: 'max', label: 'Максимум' },
                { kind: 'number', key: 'default', label: 'По умолчанию' },
            ],
        },
        {
            kind: 'text',
            key: 'cta',
            label: 'Текст кнопки',
            help: 'Кнопка ведёт к форме заявки и подставляет выбранные параметры',
        },
        { kind: 'textarea', key: 'note', label: 'Пояснение под суммой', rows: 2 },
    ],
    faq: [
        optionalTitle,
        {
            kind: 'items',
            key: 'items',
            label: 'Вопросы',
            itemLabel: 'Вопрос',
            addLabel: 'Добавить вопрос',
            newItem: { question: '', answer: '' },
            fields: [
                { kind: 'text', key: 'question', label: 'Вопрос', required: true },
                { kind: 'textarea', key: 'answer', label: 'Ответ', required: true, rows: 3 },
            ],
        },
    ],
    gallery: [
        optionalTitle,
        {
            kind: 'items',
            key: 'items',
            label: 'Фото',
            itemLabel: 'Фото',
            addLabel: 'Добавить фото из медиатеки',
            media: 'src',
            newItem: { src: '', alt: '' },
            fields: [{ kind: 'image', key: 'src', altKey: 'alt', label: 'Фото' }],
        },
    ],
    reviews: [
        optionalTitle,
        { kind: 'textarea', key: 'subtitle', label: 'Подзаголовок', rows: 2 },
        {
            kind: 'items',
            key: 'items',
            label: 'Отзывы',
            itemLabel: 'Отзыв',
            addLabel: 'Добавить отзыв',
            media: 'image',
            newItem: { author: '', place: '', details: '', text: '', image: '', imageAlt: '' },
            fields: [
                { kind: 'image', key: 'image', altKey: 'imageAlt', label: 'Фото объекта' },
                { kind: 'textarea', key: 'text', label: 'Текст отзыва', rows: 4 },
                { kind: 'text', key: 'author', label: 'Имя клиента' },
                { kind: 'text', key: 'place', label: 'Район или населённый пункт' },
                {
                    kind: 'text',
                    key: 'details',
                    label: 'Что заказали',
                    help: 'Например: 28 м, Largo, RAL 7024',
                },
            ],
        },
    ],
    portfolio: [
        optionalTitle,
        {
            kind: 'items',
            key: 'items',
            label: 'Работы',
            itemLabel: 'Работа',
            addLabel: 'Добавить работу из медиатеки',
            media: 'image',
            newItem: { title: '', image: '', href: '' },
            fields: [
                { kind: 'image', key: 'image', label: 'Фото' },
                { kind: 'text', key: 'title', label: 'Название' },
                { kind: 'link', key: 'href', label: 'Ссылка на страницу работы' },
            ],
        },
    ],
    slider: [
        {
            kind: 'items',
            key: 'items',
            label: 'Слайды',
            itemLabel: 'Слайд',
            addLabel: 'Добавить слайд из медиатеки',
            media: 'src',
            newItem: { src: '', alt: '', title: '', text: '', buttonLabel: '', buttonHref: '' },
            fields: [
                { kind: 'image', key: 'src', altKey: 'alt', label: 'Фото' },
                { kind: 'text', key: 'title', label: 'Заголовок' },
                { kind: 'textarea', key: 'text', label: 'Текст', rows: 2 },
                { kind: 'text', key: 'buttonLabel', label: 'Текст кнопки' },
                { kind: 'link', key: 'buttonHref', label: 'Кнопка ведёт' },
            ],
        },
    ],
    'price-table': [optionalTitle, { kind: 'table', label: 'Строки таблицы' }],
    cta: [
        { kind: 'text', key: 'title', label: 'Заголовок', required: true },
        { kind: 'textarea', key: 'subtitle', label: 'Подзаголовок', rows: 2 },
        {
            kind: 'group',
            key: 'cta',
            label: 'Кнопка',
            fields: [
                { kind: 'text', key: 'label', label: 'Текст кнопки' },
                { kind: 'link', key: 'href', label: 'Кнопка ведёт' },
            ],
        },
    ],
    'contact-form': [
        { kind: 'text', key: 'title', label: 'Заголовок формы', required: true },
        {
            kind: 'note',
            text: 'Поля формы общие для всего сайта. Заявки приходят в раздел «Заявки». Пока форма стоит среди блоков, общая форма внизу страницы не показывается.',
        },
    ],
}

const LABELS: Record<string, string> = {
    title: 'Заголовок',
    subtitle: 'Подзаголовок',
    text: 'Текст',
    richText: 'Текст',
    html: 'Текст',
    description: 'Описание',
    label: 'Подпись',
    value: 'Значение',
    href: 'Ссылка',
    url: 'Ссылка',
    image: 'Изображение',
    src: 'Изображение',
    imageUrl: 'Изображение',
    backgroundImage: 'Фоновое изображение',
    poster: 'Постер',
    alt: 'Подпись к фото (alt)',
    imageAlt: 'Подпись к фото (alt)',
    caption: 'Подпись',
    question: 'Вопрос',
    answer: 'Ответ',
    price: 'Цена',
    name: 'Название',
    phone: 'Телефон',
    address: 'Адрес',
    email: 'Email',
    quote: 'Цитата',
    author: 'Автор',
    items: 'Элементы',
    features: 'Особенности',
    cta: 'Кнопка',
    buttonLabel: 'Текст кнопки',
    buttonHref: 'Кнопка ведёт',
    columns: 'Колонки',
    height: 'Высота',
    enabled: 'Включено',
    before: 'Фото «До»',
    after: 'Фото «После»',
}

const IMAGE_KEYS = new Set([
    'image',
    'src',
    'imageUrl',
    'backgroundImage',
    'poster',
    'before',
    'after',
])
const LINK_KEYS = new Set(['href', 'url', 'buttonHref'])
const LONG_KEYS = new Set(['text', 'subtitle', 'description', 'answer', 'quote'])
const RICH_KEYS = new Set(['html', 'richText'])
const HIDDEN_KEYS = new Set(['alt', 'imageAlt', 'className'])

export function fieldLabel(key: string): string {
    return LABELS[key] ?? key
}

function inferField(key: string, value: unknown): FieldSpec | null {
    if (HIDDEN_KEYS.has(key)) {
        return null
    }
    if (typeof value === 'string') {
        if (RICH_KEYS.has(key)) return { kind: 'richtext', key, label: fieldLabel(key) }
        if (IMAGE_KEYS.has(key))
            return {
                kind: 'image',
                key,
                label: fieldLabel(key),
                altKey: key === 'src' ? 'alt' : key === 'image' ? 'imageAlt' : undefined,
            }
        if (LINK_KEYS.has(key)) return { kind: 'link', key, label: fieldLabel(key) }
        if (LONG_KEYS.has(key) || value.length > 80)
            return { kind: 'textarea', key, label: fieldLabel(key), rows: 3 }
        return { kind: 'text', key, label: fieldLabel(key) }
    }
    if (typeof value === 'boolean') {
        return { kind: 'checkbox', key, label: fieldLabel(key) }
    }
    if (typeof value === 'number') {
        return { kind: 'number', key, label: fieldLabel(key) }
    }
    if (Array.isArray(value)) {
        if (value.every((item) => typeof item === 'string')) {
            return { kind: 'strings', key, label: fieldLabel(key), addLabel: 'Добавить строку' }
        }
        const sample = value.find(isRecord)
        if (sample !== undefined) {
            const fields = inferFields(sample)
            const newItem = Object.fromEntries(
                Object.entries(sample).map(([childKey, childValue]) => [
                    childKey,
                    typeof childValue === 'string' ? '' : childValue,
                ]),
            )
            return {
                kind: 'items',
                key,
                label: fieldLabel(key),
                itemLabel: 'Элемент',
                addLabel: 'Добавить',
                fields,
                newItem,
            }
        }
        return null
    }
    if (isRecord(value)) {
        return { kind: 'group', key, label: fieldLabel(key), fields: inferFields(value) }
    }

    return null
}

export function inferFields(content: Record<string, unknown>): FieldSpec[] {
    return Object.entries(content)
        .map(([key, value]) => inferField(key, value))
        .filter((field): field is FieldSpec => field !== null)
}

/** Поля блока: заданные вручную для основных видов, выведенные из содержимого — для остальных. */
export function fieldSpecsFor(
    type: BuilderBlockType,
    content: Record<string, unknown>,
): FieldSpec[] {
    const own = SPECS[type]
    if (own !== undefined) {
        return own
    }

    // Старые блоки с тем же устройством содержимого (например, text с полем html) получают формы аналога.
    const canonical = canonicalType(type)
    if (canonical !== type && SPECS[canonical] !== undefined) {
        const inferred = inferFields(content)
        if (inferred.length > 0) {
            return inferred
        }
    }

    return inferFields(content)
}

export const LEAD_FORM_ANCHOR = '#lead-form'

/** Старые шаблоны ссылались на «#lead»: на сайте это та же форма. */
export function isLeadFormLink(href: string): boolean {
    return href === LEAD_FORM_ANCHOR || href === '#lead'
}
