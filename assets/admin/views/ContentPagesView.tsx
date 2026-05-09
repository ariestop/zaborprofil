import { DndContext, PointerSensor, closestCenter, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core'
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { useEffect, useMemo, useState } from 'react'
import { apiRequest } from '../api/client'
import TiptapRichTextEditor from '../components/TiptapRichTextEditor'
import type { BlockSchemaItem, ContentBlockItem, ContentPageDetail, ContentPageItem, MediaAssetItem, PageRevisionItem, PageTemplateItem } from '../types/api'
import { createVisualState, supportsVisualEditor, toBlockContent, type BlockEditorMode, type VisualBlockFormState } from '../utils/blockVisualEditor'

type PageStatus = ContentPageItem['status']
type SeoAuditIssue = { severity: string; code: string; message: string; field: string }
type SeoAuditResult = { passed: boolean; issues: SeoAuditIssue[] }

const pageTypes = ['home', 'landing', 'service', 'product_category_landing', 'material_landing', 'portfolio_index', 'portfolio_item', 'contacts', 'prices', 'text_page', 'seo_landing', 'system_page']
const statusTransitions: Record<PageStatus, PageStatus[]> = {
  draft: ['review', 'approved', 'published', 'deleted'],
  review: ['approved', 'draft', 'deleted'],
  approved: ['published', 'scheduled', 'draft', 'deleted'],
  published: ['unpublished', 'scheduled', 'archived', 'deleted'],
  scheduled: ['published', 'draft', 'deleted'],
  unpublished: ['draft', 'published', 'archived', 'deleted'],
  archived: ['draft', 'deleted'],
  deleted: ['draft'],
}
const hiddenStatusActions = new Set<PageStatus>(['review', 'approved', 'published', 'scheduled', 'deleted'])
const statusLabels: Record<PageStatus, string> = {
  draft: 'Черновик',
  review: 'На проверке',
  approved: 'Одобрено',
  published: 'Опубликовано',
  scheduled: 'Запланировано',
  unpublished: 'Снято',
  archived: 'Архив',
  deleted: 'Удалено',
}
const pageTypeLabels: Record<string, string> = {
  home: 'Главная',
  landing: 'Посадочная страница',
  service: 'Услуга',
  product_category_landing: 'Категория товаров',
  material_landing: 'Материал',
  portfolio_index: 'Список работ',
  portfolio_item: 'Работа в портфолио',
  contacts: 'Контакты',
  prices: 'Цены',
  text_page: 'Текстовая страница',
  seo_landing: 'SEO-посадочная',
  system_page: 'Системная страница',
}
const visibilityLabels: Record<string, string> = {
  public: 'Публичная',
  hidden: 'Скрытая',
  unlisted: 'Доступна по ссылке',
}
const templateLabels: Record<string, string> = {
  home_default: 'Главная страница',
  service_landing: 'Страница услуги',
  material_landing: 'Страница материала',
  portfolio_index: 'Список работ',
  contacts: 'Контакты',
  prices: 'Цены',
  text_page: 'Текстовая страница',
  seo_landing: 'SEO-посадочная',
  default: 'Без шаблона',
}
const blockTypeLabels: Record<string, string> = {
  hero: 'Первый экран',
  text: 'Текст',
  text_image: 'Текст с изображением',
  image: 'Изображение',
  gallery: 'Галерея',
  video: 'Видео',
  feature_grid: 'Преимущества',
  price_cards: 'Карточки цен',
  steps: 'Этапы работ',
  faq: 'FAQ: вопросы и ответы',
  cta_form: 'Форма заявки',
  telegram_cta: 'Переход в Telegram',
  contacts: 'Контакты',
  map: 'Карта',
  portfolio_grid: 'Сетка работ',
  seo_text: 'SEO-текст',
  html_embed: 'HTML-вставка',
  table: 'Таблица',
  accordion: 'Аккордеон',
  calculator_placeholder: 'Место под калькулятор',
  before_after: 'До/после',
  review_cards: 'Отзывы',
  documents: 'Документы',
}
const blockContentExamples: Record<string, Record<string, unknown>> = {
  hero: { title: 'Заборы под ключ в Москве', text: 'Изготовим и установим забор на участке с гарантией.', cta: { text: 'Рассчитать стоимость', url: '#lead-form' } },
  text: { title: 'Описание услуги', text: 'Короткий полезный текст для посетителя страницы.' },
  text_image: { title: 'Почему выбирают нас', text: 'Работаем по договору, соблюдаем сроки и используем проверенные материалы.', image: '/uploads/example.webp', alt: 'Монтаж забора' },
}
const blockSettingsExamples: Record<string, Record<string, unknown>> = {
  hero: { layout: 'default' },
  gallery: { columns: 3 },
  feature_grid: { columns: 3 },
  faq: { schemaOrg: true },
}

function SortableBlockCard({
  block,
  isSelected,
  onSelect,
  blockTypeLabel,
}: {
  block: ContentBlockItem
  isSelected: boolean
  onSelect: () => void
  blockTypeLabel: (type: string) => string
}) {
  const { attributes, listeners, setNodeRef, transform, transition } = useSortable({ id: block.id })

  return (
    <div
      ref={setNodeRef}
      className={[
        'mb-2 flex items-center gap-2 rounded-lg border px-2 py-2 text-left text-sm transition hover:bg-slate-50',
        isSelected ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200',
      ].join(' ')}
      style={{
        transform: CSS.Transform.toString(transform),
        transition,
      }}
    >
      <button
        type="button"
        className="flex-1 rounded-md px-1 py-1 text-left"
        onClick={onSelect}
      >
        <span className="block font-medium text-slate-900">{block.position + 1}. {block.name}</span>
        <span className="block text-xs text-slate-500">{blockTypeLabel(block.type)} · {block.isEnabled ? 'включён' : 'выключен'}</span>
      </button>
      <button
        type="button"
        className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
        title="Перетащить блок"
        style={{ touchAction: 'none' }}
        {...attributes}
        {...listeners}
      >
        ::
      </button>
    </div>
  )
}

export default function ContentPagesView() {
  const [pages, setPages] = useState<ContentPageItem[]>([])
  const [templates, setTemplates] = useState<PageTemplateItem[]>([])
  const [blockSchemas, setBlockSchemas] = useState<BlockSchemaItem[]>([])
  const [revisions, setRevisions] = useState<PageRevisionItem[]>([])
  const [selected, setSelected] = useState<ContentPageDetail | null>(null)
  const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null)
  const [blockEditorOpen, setBlockEditorOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [savingMessage, setSavingMessage] = useState('Сохраняется...')
  const [statusChanging, setStatusChanging] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)
  const [seoAuditToast, setSeoAuditToast] = useState<SeoAuditResult | null>(null)
  const [mediaAssets, setMediaAssets] = useState<MediaAssetItem[]>([])
  const [mediaLoading, setMediaLoading] = useState(false)
  const [mediaPickerOpen, setMediaPickerOpen] = useState(false)
  const blockSensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }))

  const [form, setForm] = useState({
    type: 'landing',
    title: '',
    slug: '',
    path: '',
    h1: '',
    template: 'service_landing',
    sortOrder: 0,
    isIndexable: true,
    visibility: 'public',
    metaDescription: '',
    canonicalUrl: '',
    ogTitle: '',
    ogDescription: '',
    ogImage: '',
    jsonLd: '',
  })

  const [blockForm, setBlockForm] = useState({
    type: 'hero',
    name: '',
    position: 0,
    isEnabled: true,
    visibility: 'public',
    content: '{}',
    settings: '{}',
  })
  const [visualBlockForm, setVisualBlockForm] = useState<VisualBlockFormState>({
    mode: 'json',
    text: { title: '', text: '' },
    textImage: { title: '', text: '', image: '', alt: '' },
  })

  const selectedBlocks = useMemo(() => [...(selected?.blocks ?? [])].sort((left, right) => left.position - right.position), [selected])
  const selectedBlock = useMemo(() => selected?.blocks.find((block) => block.id === selectedBlockId) ?? null, [selected, selectedBlockId])
  const templatesForType = useMemo(() => templates.filter((template) => template.pageType === form.type), [templates, form.type])
  const selectedBlockSchema = useMemo(() => blockSchemas.find((item) => item.type === blockForm.type) ?? null, [blockSchemas, blockForm.type])
  const selectedBlockExampleContent = useMemo(() => blockContentExamples[blockForm.type] ?? selectedBlockSchema?.defaultContent ?? {}, [blockForm.type, selectedBlockSchema])
  const selectedBlockExampleSettings = useMemo(() => blockSettingsExamples[blockForm.type] ?? selectedBlockSchema?.defaultSettings ?? {}, [blockForm.type, selectedBlockSchema])
  const blockSupportsVisualEditor = supportsVisualEditor(blockForm.type)
  const availableStatusActions = useMemo(() => {
    if (!selected) return []
    return statusTransitions[selected.status].filter((status) => !hiddenStatusActions.has(status))
  }, [selected])
  const canPublishSelected = useMemo(() => {
    if (!selected || selected.status === 'published') return false
    return statusTransitions[selected.status].includes('published')
  }, [selected])

  const statusLabel = (status: PageStatus): string => statusLabels[status] ?? status
  const pageTypeLabel = (type: string): string => pageTypeLabels[type] ?? type
  const visibilityLabel = (visibility: string): string => visibilityLabels[visibility] ?? visibility
  const templateLabel = (code: string, fallback?: string): string => templateLabels[code] ?? fallback ?? code
  const blockTypeLabel = (type: string): string => blockTypeLabels[type] ?? type

  const parseObject = (value: string, label: string): Record<string, unknown> => {
    const parsed = JSON.parse(value || '{}') as unknown
    if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
      throw new Error(`${label} должен быть JSON-объектом.`)
    }
    return parsed as Record<string, unknown>
  }

  const parseJsonLdInput = (): Record<string, unknown>[] | null => {
    if (form.jsonLd.trim() === '') {
      return null
    }
    const decoded = JSON.parse(form.jsonLd) as unknown
    if (!Array.isArray(decoded) || decoded.some((item) => item === null || typeof item !== 'object' || Array.isArray(item))) {
      throw new Error('JSON-LD должен быть массивом объектов.')
    }
    return decoded as Record<string, unknown>[]
  }

  const pagePayload = (): Record<string, unknown> => ({
    type: form.type,
    title: form.title,
    slug: form.slug,
    path: form.path,
    h1: form.h1,
    template: form.template,
    sortOrder: form.sortOrder,
    isIndexable: form.isIndexable,
    visibility: form.visibility,
  })

  const seoPayload = (): Record<string, unknown> => ({
    metaDescription: form.metaDescription || null,
    canonicalUrl: form.canonicalUrl || null,
    ogTitle: form.ogTitle || null,
    ogDescription: form.ogDescription || null,
    ogImage: form.ogImage || null,
    ogType: 'website',
    jsonLd: parseJsonLdInput(),
  })

  const parseBlockContentForVisual = (): Record<string, unknown> => {
    try {
      return parseObject(blockForm.content, 'Content')
    } catch {
      return {}
    }
  }

  const blockPayload = (): Record<string, unknown> => {
    const parsedContent = visualBlockForm.mode === 'visual'
      ? parseBlockContentForVisual()
      : parseObject(blockForm.content, 'Content')

    return {
      type: blockForm.type,
      name: blockForm.name || blockForm.type,
      position: blockForm.position,
      isEnabled: blockForm.isEnabled,
      visibility: blockForm.visibility,
      content: toBlockContent(blockForm.type, visualBlockForm, parsedContent),
      settings: parseObject(blockForm.settings, 'Settings'),
    }
  }

  const syncVisualFormFromJson = (blockType: string, contentInput: unknown): void => {
    const created = createVisualState(blockType, contentInput)
    setVisualBlockForm(created)
  }

  const loadBaseData = async (): Promise<void> => {
    const [pageResponse, templateResponse, schemaResponse] = await Promise.all([
      apiRequest<{ pages: ContentPageItem[] }>('/admin/api/content/pages'),
      apiRequest<{ templates: PageTemplateItem[] }>('/admin/api/content/templates'),
      apiRequest<{ blockSchemas: BlockSchemaItem[] }>('/admin/api/content/block-schemas'),
    ])
    setPages(pageResponse.pages)
    setTemplates(templateResponse.templates)
    setBlockSchemas(schemaResponse.blockSchemas)
  }

  const loadPages = async (): Promise<void> => {
    setLoading(true)
    setError(null)
    try {
      await loadBaseData()
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Не удалось загрузить страницы')
    } finally {
      setLoading(false)
    }
  }

  const fillPageForm = (page: ContentPageDetail): void => {
    setForm({
      type: page.type,
      title: page.title,
      slug: page.slug,
      path: page.path,
      h1: page.h1,
      template: page.template,
      sortOrder: page.sortOrder,
      isIndexable: page.isIndexable,
      visibility: page.visibility,
      metaDescription: page.seo.metaDescription ?? '',
      canonicalUrl: page.seo.canonicalUrl ?? '',
      ogTitle: page.seo.ogTitle ?? '',
      ogDescription: page.seo.ogDescription ?? '',
      ogImage: page.seo.ogImage ?? '',
      jsonLd: page.seo.jsonLd ? JSON.stringify(page.seo.jsonLd, null, 2) : '',
    })
  }

  const fillBlockForm = (block: ContentBlockItem, openEditor = true): void => {
    setSelectedBlockId(block.id)
    setBlockForm({
      type: block.type,
      name: block.name,
      position: block.position,
      isEnabled: block.isEnabled,
      visibility: block.visibility,
      content: JSON.stringify(block.content, null, 2),
      settings: JSON.stringify(block.settings, null, 2),
    })
    syncVisualFormFromJson(block.type, block.content)
    setBlockEditorOpen(openEditor)
  }

  const loadRevisions = async (pageId?: string): Promise<void> => {
    const id = pageId ?? selected?.id
    if (!id) {
      setRevisions([])
      return
    }
    const response = await apiRequest<{ revisions: PageRevisionItem[] }>(`/admin/api/content/pages/${id}/revisions`)
    setRevisions(response.revisions)
  }

  const selectPage = async (id: string): Promise<void> => {
    setPreviewUrl(null)
    setBlockEditorOpen(false)
    setSeoAuditToast(null)
    const page = await apiRequest<ContentPageDetail>(`/admin/api/content/pages/${id}`)
    setSelected(page)
    fillPageForm(page)
    const firstBlockId = [...page.blocks].sort((left, right) => left.position - right.position)[0]?.id ?? null
    setSelectedBlockId(firstBlockId)
    if (firstBlockId) {
      const firstBlock = page.blocks.find((block) => block.id === firstBlockId)
      if (firstBlock) {
        fillBlockForm(firstBlock, false)
      }
    }
    await loadRevisions(id)
  }

  const resetPageForm = (): void => {
    setSelected(null)
    setSelectedBlockId(null)
    setBlockEditorOpen(false)
    setPreviewUrl(null)
    setSeoAuditToast(null)
    setRevisions([])
    setForm({
      type: 'landing',
      title: '',
      slug: '',
      path: '',
      h1: '',
      template: 'service_landing',
      sortOrder: 0,
      isIndexable: true,
      visibility: 'public',
      metaDescription: '',
      canonicalUrl: '',
      ogTitle: '',
      ogDescription: '',
      ogImage: '',
      jsonLd: '',
    })
  }

  const createBlocksFromTemplate = async (pageId: string): Promise<void> => {
    if (!selected || selected.blocks.length > 0) return
    const template = templates.find((item) => item.code === form.template)
    if (!template) return

    setSavingMessage('Создаются стартовые блоки...')
    await Promise.all(template.blocksSchema.map((block) => (
      apiRequest(`/admin/api/content/pages/${pageId}/blocks`, {
        method: 'POST',
        body: {
          type: block.type,
          name: block.name,
          position: block.position,
          content: block.content,
          settings: block.settings,
          isEnabled: block.type === 'faq' ? false : block.isEnabled,
        },
      })
    )))
    await selectPage(pageId)
  }

  const savePage = async (): Promise<void> => {
    if (saving) return
    setError(null)
    setSaving(true)
    setSavingMessage('Сохраняется...')
    try {
      if (selected === null) {
        const created = await apiRequest<ContentPageItem>('/admin/api/content/pages', { method: 'POST', body: pagePayload() })
        await loadPages()
        await selectPage(created.id)
        await createBlocksFromTemplate(created.id)
      } else {
        await apiRequest<ContentPageItem>(`/admin/api/content/pages/${selected.id}`, { method: 'PUT', body: pagePayload() })
        await apiRequest<ContentPageItem>(`/admin/api/content/pages/${selected.id}/seo`, { method: 'PUT', body: seoPayload() })
        await loadPages()
        await selectPage(selected.id)
      }
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Не удалось сохранить страницу')
    } finally {
      setSaving(false)
      setSavingMessage('Сохраняется...')
    }
  }

  const persistSelectedPageDraft = async (): Promise<string | null> => {
    if (!selected) return null
    const pageId = selected.id
    await apiRequest<ContentPageItem>(`/admin/api/content/pages/${pageId}`, { method: 'PUT', body: pagePayload() })
    await apiRequest<ContentPageItem>(`/admin/api/content/pages/${pageId}/seo`, { method: 'PUT', body: seoPayload() })
    return pageId
  }

  const saveBlock = async (): Promise<void> => {
    if (!selected) return
    setError(null)
    try {
      if (!selectedBlock) {
        await apiRequest(`/admin/api/content/pages/${selected.id}/blocks`, { method: 'POST', body: blockPayload() })
      } else {
        await apiRequest(`/admin/api/content/blocks/${selectedBlock.id}`, { method: 'PUT', body: blockPayload() })
      }
      await selectPage(selected.id)
      setBlockEditorOpen(false)
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Не удалось сохранить блок')
    }
  }

  const deleteBlock = async (block: ContentBlockItem): Promise<void> => {
    if (!selected) return
    await apiRequest(`/admin/api/content/blocks/${block.id}`, { method: 'DELETE' })
    await selectPage(selected.id)
    setBlockEditorOpen(false)
  }

  const moveBlock = async (block: ContentBlockItem, direction: -1 | 1): Promise<void> => {
    if (!selected) return
    const sorted = [...selectedBlocks]
    const index = sorted.findIndex((item) => item.id === block.id)
    const target = index + direction
    if (target < 0 || target >= sorted.length) return
    const [removed] = sorted.splice(index, 1)
    sorted.splice(target, 0, removed)
    await reorderBlocksByIds(sorted.map((item) => item.id))
  }

  const reorderBlocksByIds = async (blockIds: string[]): Promise<void> => {
    if (!selected) return
    await apiRequest(`/admin/api/content/pages/${selected.id}/blocks/reorder`, {
      method: 'POST',
      body: { blockIds },
    })
    await selectPage(selected.id)
  }

  const handleBlockDragEnd = (event: DragEndEvent): void => {
    const { active, over } = event
    if (!selected || over === null || active.id === over.id) {
      return
    }

    const oldIndex = selectedBlocks.findIndex((item) => item.id === active.id)
    const newIndex = selectedBlocks.findIndex((item) => item.id === over.id)
    if (oldIndex < 0 || newIndex < 0) {
      return
    }

    const reordered = [...selectedBlocks]
    const [moved] = reordered.splice(oldIndex, 1)
    if (moved === undefined) {
      return
    }
    reordered.splice(newIndex, 0, moved)

    void reorderBlocksByIds(reordered.map((item) => item.id)).catch((caught) => {
      setError(caught instanceof Error ? caught.message : 'Не удалось изменить порядок блоков')
    })
  }

  const startNewBlock = (type = 'hero'): void => {
    setSelectedBlockId(null)
    const schema = blockSchemas.find((item) => item.type === type)
    setBlockForm({
      type,
      name: schema?.label ?? type,
      position: selected?.blocks.length ?? 0,
      isEnabled: true,
      visibility: 'public',
      content: JSON.stringify(schema?.defaultContent ?? {}, null, 2),
      settings: JSON.stringify(schema?.defaultSettings ?? {}, null, 2),
    })
    syncVisualFormFromJson(type, schema?.defaultContent ?? {})
    setBlockEditorOpen(true)
  }

  const setBlockEditorMode = (mode: BlockEditorMode): void => {
    if (!supportsVisualEditor(blockForm.type)) {
      setVisualBlockForm((current) => ({ ...current, mode: 'json' }))
      return
    }
    if (mode === 'visual') {
      syncVisualFormFromJson(blockForm.type, parseBlockContentForVisual())
      setVisualBlockForm((current) => ({ ...current, mode: 'visual' }))
      return
    }
    setBlockForm((current) => ({
      ...current,
      content: JSON.stringify(
        toBlockContent(current.type, visualBlockForm, parseBlockContentForVisual()),
        null,
        2,
      ),
    }))
    setVisualBlockForm((current) => ({ ...current, mode: 'json' }))
  }

  const openMediaPicker = async (): Promise<void> => {
    setMediaPickerOpen(true)
    if (mediaAssets.length > 0) return
    setMediaLoading(true)
    try {
      const response = await apiRequest<{ assets: MediaAssetItem[] }>('/admin/api/media/assets')
      setMediaAssets(response.assets)
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Не удалось загрузить медиатеку')
    } finally {
      setMediaLoading(false)
    }
  }

  const selectTextImageAsset = (asset: MediaAssetItem): void => {
    setVisualBlockForm((current) => ({
      ...current,
      textImage: {
        ...current.textImage,
        image: asset.publicPath,
        alt: current.textImage.alt.trim() === '' ? asset.originalName : current.textImage.alt,
      },
    }))
    setMediaPickerOpen(false)
  }

  const publishPage = async (): Promise<void> => {
    if (!selected || !canPublishSelected || statusChanging !== null) return
    setError(null)
    setStatusChanging('published')
    try {
      const pageId = await persistSelectedPageDraft()
      if (!pageId) return
      await apiRequest<ContentPageItem>(`/admin/api/content/pages/${pageId}/publish`, {
        method: 'POST',
        body: { comment: 'Published from Page Engine editor' },
      })
      await loadPages()
      await selectPage(pageId)
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Не удалось опубликовать страницу')
    } finally {
      setStatusChanging(null)
    }
  }

  const changeStatus = async (status: PageStatus): Promise<void> => {
    if (!selected || !availableStatusActions.includes(status) || statusChanging !== null) return
    setError(null)
    setStatusChanging(status)
    try {
      await apiRequest<ContentPageItem>(`/admin/api/content/pages/${selected.id}/status`, { method: 'PATCH', body: { status } })
      await loadPages()
      await selectPage(selected.id)
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Не удалось изменить статус')
    } finally {
      setStatusChanging(null)
    }
  }

  const deletePage = async (): Promise<void> => {
    if (!selected || statusChanging !== null) return

    const confirmed = window.confirm(`Удалить страницу "${selected.title}"?`)
    if (!confirmed) {
      return
    }

    setError(null)
    setStatusChanging('deleted')
    try {
      await apiRequest<ContentPageItem>(`/admin/api/content/pages/${selected.id}/status`, {
        method: 'PATCH',
        body: { status: 'deleted' },
      })
      await loadPages()
      resetPageForm()
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Не удалось удалить страницу')
    } finally {
      setStatusChanging(null)
    }
  }

  const buildPreviewLink = async (): Promise<void> => {
    if (!selected) return
    const response = await apiRequest<{ previewUrl: string }>(`/admin/api/content/pages/${selected.id}/preview-link`)
    setPreviewUrl(response.previewUrl)
  }

  const runSeoAudit = async (): Promise<void> => {
    if (!selected) return
    setError(null)
    const pageId = selected.id
    try {
      await persistSelectedPageDraft()
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Не удалось сохранить SEO перед проверкой')
      return
    }

    const audit = await apiRequest<SeoAuditResult>(`/admin/api/seo/audit/pages/${pageId}`)
    setSeoAuditToast(audit)
    window.setTimeout(() => setSeoAuditToast(null), 10000)
  }

  const rollbackRevision = async (revision: PageRevisionItem): Promise<void> => {
    if (!selected) return
    await apiRequest(`/admin/api/content/pages/${selected.id}/revisions/${revision.id}/rollback`, { method: 'POST' })
    await loadPages()
    await selectPage(selected.id)
  }

  useEffect(() => {
    void loadPages()
  }, [])

  return (
    <section className="space-y-6">
      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h2 className="text-lg font-semibold text-slate-950">Редактор страниц</h2>
            <p className="mt-1 text-sm text-slate-600">Создание страниц, блоки контента, SEO-поля, предпросмотр, публикация и история версий.</p>
          </div>
          <button type="button" className="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800" onClick={resetPageForm}>
            Создать страницу
          </button>
        </div>
        {error && <p className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}
      </div>

      <div className="grid grid-cols-[280px_1fr] gap-6">
        <aside className="space-y-4">
          <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="mb-3 text-sm font-semibold text-slate-700">Страницы</p>
            {loading && <p className="text-sm text-slate-500">Загрузка...</p>}
            {pages.map((page) => (
              <button key={page.id} type="button" className="mb-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-left text-sm hover:bg-slate-50" onClick={() => { void selectPage(page.id) }}>
                <span className="block font-medium text-slate-900">{page.title}</span>
                <span className="block text-xs text-slate-500">{page.path} · {statusLabel(page.status)}</span>
              </button>
            ))}
          </div>

          {selected && (
            <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
              <div className="mb-3 flex items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold text-slate-700">Блоки</p>
                </div>
                <button type="button" className="shrink-0 text-xs font-semibold text-emerald-700" onClick={() => startNewBlock()}>+ блок</button>
              </div>
              <DndContext sensors={blockSensors} collisionDetection={closestCenter} onDragEnd={handleBlockDragEnd}>
                <SortableContext items={selectedBlocks.map((block) => block.id)} strategy={verticalListSortingStrategy}>
                  {selectedBlocks.map((block) => (
                    <SortableBlockCard
                      key={block.id}
                      block={block}
                      isSelected={selectedBlockId === block.id}
                      onSelect={() => fillBlockForm(block)}
                      blockTypeLabel={blockTypeLabel}
                    />
                  ))}
                </SortableContext>
              </DndContext>
              {selectedBlocks.length === 0 && <p className="text-sm text-slate-500">Блоков пока нет.</p>}
            </div>
          )}
        </aside>

        <div className="space-y-6">
          <form className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" onSubmit={(event) => { event.preventDefault(); void savePage() }}>
            <div className="mb-5">
              <h3 className="text-base font-semibold text-slate-950">Основные поля страницы</h3>
              <p className="mt-1 text-sm text-slate-600">Заполните адрес, заголовки и SEO-описание. Для публикации критичны путь, заголовок, H1 и корректные включённые блоки.</p>
            </div>
            <div className="grid grid-cols-3 gap-4">
              <label className="text-sm font-medium text-slate-700">Тип страницы
                <select value={form.type} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setForm((current) => ({ ...current, type: event.target.value }))}>
                  {pageTypes.map((type) => <option key={type} value={type}>{pageTypeLabel(type)}</option>)}
                </select>
                <span className="mt-1 block text-xs font-normal text-slate-500">Определяет назначение страницы и SEO-рекомендации. Пример: «Услуга» для страницы монтажа забора.</span>
              </label>
              <label className="text-sm font-medium text-slate-700">Шаблон
                <select value={form.template} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setForm((current) => ({ ...current, template: event.target.value }))}>
                  {templatesForType.map((template) => <option key={template.code} value={template.code}>{templateLabel(template.code, template.name)}</option>)}
                  <option value="default">{templateLabel('default')}</option>
                </select>
                <span className="mt-1 block text-xs font-normal text-slate-500">Создаёт стартовый набор блоков. Пример: «Страница услуги».</span>
              </label>
              <label className="text-sm font-medium text-slate-700">Видимость
                <select value={form.visibility} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setForm((current) => ({ ...current, visibility: event.target.value }))}>
                  <option value="public">{visibilityLabel('public')}</option>
                  <option value="hidden">{visibilityLabel('hidden')}</option>
                  <option value="unlisted">{visibilityLabel('unlisted')}</option>
                </select>
                <span className="mt-1 block text-xs font-normal text-slate-500">Публичная страница доступна посетителям и может попасть в sitemap.</span>
              </label>
              <label className="text-sm font-medium text-slate-700">Заголовок страницы
                <input value={form.title} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setForm((current) => ({ ...current, title: event.target.value }))} />
                <span className="mt-1 block text-xs font-normal text-slate-500">Title для админки и SEO. Пример: «Заборы из профнастила под ключ».</span>
              </label>
              <label className="text-sm font-medium text-slate-700">H1
                <input value={form.h1} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setForm((current) => ({ ...current, h1: event.target.value }))} />
                <span className="mt-1 block text-xs font-normal text-slate-500">Главный заголовок для посетителя. Пример: «Установка заборов из профнастила».</span>
              </label>
              <label className="text-sm font-medium text-slate-700">Адрес страницы
                <input value={form.path} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setForm((current) => ({ ...current, path: event.target.value }))} />
                <span className="mt-1 block text-xs font-normal text-slate-500">Путь должен начинаться с «/». Пример: «/zabory-iz-profnastila/».</span>
              </label>
              <label className="text-sm font-medium text-slate-700">Slug
                <input value={form.slug} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setForm((current) => ({ ...current, slug: event.target.value }))} />
                <span className="mt-1 block text-xs font-normal text-slate-500">Короткий латинский идентификатор без слэшей. Пример: «zabory-iz-profnastila».</span>
              </label>
              <label className="flex items-end gap-2 pb-2 text-sm text-slate-700">
                <input checked={form.isIndexable} type="checkbox" className="rounded border-slate-300" onChange={(event) => setForm((current) => ({ ...current, isIndexable: event.target.checked }))} />
                Индексировать
              </label>
            </div>

            <div className="mt-6 grid gap-4">
              <label className="text-sm font-medium text-slate-700">SEO-описание
                <textarea value={form.metaDescription} rows={3} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setForm((current) => ({ ...current, metaDescription: event.target.value }))} />
                <span className="mt-1 block text-xs font-normal text-slate-500">Описание для поискового сниппета, желательно 80-320 символов. Пример: «Производим и устанавливаем заборы из профнастила под ключ в Москве и области: замер, материалы, монтаж и гарантия.»</span>
              </label>
              <label className="text-sm font-medium text-slate-700">Канонический URL
                <input value={form.canonicalUrl} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setForm((current) => ({ ...current, canonicalUrl: event.target.value }))} />
                <span className="mt-1 block text-xs font-normal text-slate-500">Заполняйте только если канонический адрес отличается. Пример: «https://zaborprofil.ru/zabory-iz-profnastila/».</span>
              </label>
              <div className="grid grid-cols-2 gap-4">
                <label className="text-sm font-medium text-slate-700">Заголовок для соцсетей
                  <input value={form.ogTitle} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setForm((current) => ({ ...current, ogTitle: event.target.value }))} />
                  <span className="mt-1 block text-xs font-normal text-slate-500">Используется в превью ссылки. Пример: «Заборы из профнастила под ключ».</span>
                </label>
                <label className="text-sm font-medium text-slate-700">Изображение для соцсетей
                  <input value={form.ogImage} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setForm((current) => ({ ...current, ogImage: event.target.value }))} />
                  <span className="mt-1 block text-xs font-normal text-slate-500">Абсолютный URL картинки. Пример: «https://zaborprofil.ru/uploads/og/zabor.webp».</span>
                </label>
              </div>
              <label className="text-sm font-medium text-slate-700">Описание для соцсетей
                <textarea value={form.ogDescription} rows={2} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setForm((current) => ({ ...current, ogDescription: event.target.value }))} />
                <span className="mt-1 block text-xs font-normal text-slate-500">Можно повторить SEO-описание или написать более рекламный текст.</span>
              </label>
              <label className="text-sm font-medium text-slate-700">JSON-LD
                <textarea value={form.jsonLd} rows={5} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs" onChange={(event) => setForm((current) => ({ ...current, jsonLd: event.target.value }))} />
                <span className="mt-1 block text-xs font-normal text-slate-500">Необязательный массив объектов schema.org. Пример: {'[{"@context":"https://schema.org","@type":"LocalBusiness","name":"ЗаборПрофиль"}]'}</span>
              </label>
            </div>

            <div className="mt-6 flex flex-wrap items-center gap-3">
              <button type="submit" className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60" disabled={saving}>{saving ? savingMessage : 'Сохранить'}</button>
              {canPublishSelected && (
                <button type="button" className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60" disabled={statusChanging !== null} onClick={() => { void publishPage() }}>
                  {statusChanging === 'published' ? 'Публикуется...' : 'Опубликовать'}
                </button>
              )}
              {selected && <span className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700">Текущий: {statusLabel(selected.status)}</span>}
              {availableStatusActions.map((status) => (
                <button key={status} type="button" className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60" disabled={statusChanging !== null} onClick={() => { void changeStatus(status) }}>
                  {statusChanging === status ? '...' : statusLabel(status)}
                </button>
              ))}
              {selected && selected.status !== 'deleted' && (
                <button
                  type="button"
                  className="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                  disabled={statusChanging !== null}
                  onClick={() => {
                    void deletePage()
                  }}
                >
                  {statusChanging === 'deleted' ? 'Удаление...' : 'Удалить страницу'}
                </button>
              )}
              {selected && <button type="button" className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" onClick={() => { void buildPreviewLink() }}>Предпросмотр</button>}
              {previewUrl && <a href={previewUrl} target="_blank" rel="noreferrer" className="text-sm font-medium text-emerald-700">Открыть предпросмотр</a>}
              {selected && <button type="button" className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" onClick={() => { void runSeoAudit() }}>Проверить SEO</button>}
            </div>
          </form>

          {selected && blockEditorOpen && (
            <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/50 px-4 py-8" role="dialog" aria-modal="true" onClick={() => setBlockEditorOpen(false)}>
              <section className="w-full max-w-5xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl" onClick={(event) => event.stopPropagation()}>
                <div className="mb-4 flex items-start justify-between gap-4">
                  <div>
                    <h3 className="text-base font-semibold text-slate-950">Редактор блоков</h3>
                    <p className="mt-1 text-sm text-slate-600">Блоки выводятся на публичной странице сверху вниз. JSON должен быть объектом в фигурных скобках.</p>
                  </div>
                  <div className="flex items-center gap-3">
                    <select value={blockForm.type} className="rounded-lg border border-slate-300 px-3 py-2 text-sm" onChange={(event) => startNewBlock(event.target.value)}>
                      {blockSchemas.map((schema) => <option key={schema.type} value={schema.type}>{blockTypeLabel(schema.type)}</option>)}
                    </select>
                    <button type="button" className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" onClick={() => setBlockEditorOpen(false)}>Закрыть</button>
                  </div>
                </div>
                {selectedBlockSchema && (
                  <div className="mb-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <p><span className="font-semibold text-slate-700">Назначение:</span> {selectedBlockSchema.description}</p>
                    {selectedBlockSchema.requiredContentFields.length > 0 && (
                      <p className="mt-1">
                        <span className="font-semibold text-slate-700">Обязательные поля:</span> {selectedBlockSchema.requiredContentFields.join(', ')}
                      </p>
                    )}
                  </div>
                )}
                <div className="grid grid-cols-3 gap-4">
                  <label className="text-sm font-medium text-slate-700">Название блока
                    <input value={blockForm.name} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setBlockForm((current) => ({ ...current, name: event.target.value }))} />
                    <span className="mt-1 block text-xs font-normal text-slate-500">Внутреннее название для редактора. Пример: «FAQ по установке».</span>
                  </label>
                  <label className="text-sm font-medium text-slate-700">Позиция
                    <input value={blockForm.position} type="number" className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" onChange={(event) => setBlockForm((current) => ({ ...current, position: Number(event.target.value) }))} />
                    <span className="mt-1 block text-xs font-normal text-slate-500">Чем меньше число, тем выше блок на странице.</span>
                  </label>
                  <label className="flex items-end gap-2 pb-7 text-sm text-slate-700">
                    <input checked={blockForm.isEnabled} type="checkbox" className="rounded border-slate-300" onChange={(event) => setBlockForm((current) => ({ ...current, isEnabled: event.target.checked }))} /> Включён на странице
                  </label>
                </div>

                {blockSupportsVisualEditor && (
                  <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div className="inline-flex rounded-lg border border-slate-300 bg-white p-1 text-xs font-semibold">
                      <button type="button" className={['rounded-md px-3 py-1', visualBlockForm.mode === 'visual' ? 'bg-emerald-700 text-white' : 'text-slate-600'].join(' ')} onClick={() => setBlockEditorMode('visual')}>Визуально</button>
                      <button type="button" className={['rounded-md px-3 py-1', visualBlockForm.mode === 'json' ? 'bg-emerald-700 text-white' : 'text-slate-600'].join(' ')} onClick={() => setBlockEditorMode('json')}>JSON</button>
                    </div>
                  </div>
                )}

                {blockSupportsVisualEditor && visualBlockForm.mode === 'visual' && (
                  <div className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/40 p-4">
                    {blockForm.type === 'text' && (
                      <div className="space-y-4">
                        <label className="block text-sm font-medium text-slate-700">Заголовок
                          <input value={visualBlockForm.text.title} className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2" onChange={(event) => setVisualBlockForm((current) => ({ ...current, text: { ...current.text, title: event.target.value } }))} />
                        </label>
                        <TiptapRichTextEditor modelValue={visualBlockForm.text.text} onChange={(value) => setVisualBlockForm((current) => ({ ...current, text: { ...current.text, text: value } }))} />
                      </div>
                    )}
                    {blockForm.type === 'text_image' && (
                      <div className="space-y-4">
                        <label className="block text-sm font-medium text-slate-700">Заголовок
                          <input value={visualBlockForm.textImage.title} className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2" onChange={(event) => setVisualBlockForm((current) => ({ ...current, textImage: { ...current.textImage, title: event.target.value } }))} />
                        </label>
                        <TiptapRichTextEditor modelValue={visualBlockForm.textImage.text} onChange={(value) => setVisualBlockForm((current) => ({ ...current, textImage: { ...current.textImage, text: value } }))} />
                        <div className="grid grid-cols-[1fr_auto] gap-3">
                          <label className="text-sm font-medium text-slate-700">Изображение
                            <input value={visualBlockForm.textImage.image} className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2" onChange={(event) => setVisualBlockForm((current) => ({ ...current, textImage: { ...current.textImage, image: event.target.value } }))} />
                          </label>
                          <button type="button" className="mt-6 h-fit rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" onClick={() => { void openMediaPicker() }}>
                            Выбрать из медиатеки
                          </button>
                        </div>
                        <label className="block text-sm font-medium text-slate-700">Alt изображения
                          <input value={visualBlockForm.textImage.alt} className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2" onChange={(event) => setVisualBlockForm((current) => ({ ...current, textImage: { ...current.textImage, alt: event.target.value } }))} />
                        </label>
                      </div>
                    )}
                  </div>
                )}

                <div className="mt-4 grid grid-cols-2 gap-4">
                  {(!blockSupportsVisualEditor || visualBlockForm.mode === 'json') && (
                    <label className="text-sm font-medium text-slate-700">Контент блока JSON
                      <textarea value={blockForm.content} rows={10} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs" onChange={(event) => setBlockForm((current) => ({ ...current, content: event.target.value }))} />
                      <span className="mt-1 block text-xs font-normal text-slate-500">Тексты, изображения, ссылки и списки для блока. Значение должно быть JSON-объектом.</span>
                    </label>
                  )}
                  <label className="text-sm font-medium text-slate-700">Настройки блока JSON
                    <textarea value={blockForm.settings} rows={10} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs" onChange={(event) => setBlockForm((current) => ({ ...current, settings: event.target.value }))} />
                    <span className="mt-1 block text-xs font-normal text-slate-500">Внешний вид и поведение блока: колонки, режим отображения, schema.org. Значение должно быть JSON-объектом.</span>
                  </label>
                </div>
                <div className="mt-4 grid grid-cols-2 gap-4">
                  <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                    <p className="text-sm font-semibold text-slate-700">Пример контента для «{blockTypeLabel(blockForm.type)}»</p>
                    <pre className="mt-2 overflow-auto whitespace-pre-wrap text-xs text-slate-600">{JSON.stringify(selectedBlockExampleContent, null, 2)}</pre>
                  </div>
                  <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                    <p className="text-sm font-semibold text-slate-700">Пример настроек</p>
                    <pre className="mt-2 overflow-auto whitespace-pre-wrap text-xs text-slate-600">{JSON.stringify(selectedBlockExampleSettings, null, 2)}</pre>
                  </div>
                </div>
                <div className="mt-4 flex flex-wrap gap-2">
                  <button type="button" className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800" onClick={() => { void saveBlock() }}>{selectedBlock ? 'Сохранить блок' : 'Добавить блок'}</button>
                  {selectedBlock && <button type="button" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" onClick={() => { void moveBlock(selectedBlock, -1) }}>Вверх</button>}
                  {selectedBlock && <button type="button" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" onClick={() => { void moveBlock(selectedBlock, 1) }}>Вниз</button>}
                  {selectedBlock && <button type="button" className="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700" onClick={() => { void deleteBlock(selectedBlock) }}>Удалить</button>}
                </div>
              </section>
            </div>
          )}

          {mediaPickerOpen && (
            <div className="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 px-4" role="dialog" aria-modal="true" onClick={() => setMediaPickerOpen(false)}>
              <section className="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl" onClick={(event) => event.stopPropagation()}>
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <h4 className="text-base font-semibold text-slate-900">Выбор изображения</h4>
                    <p className="mt-1 text-sm text-slate-600">Выберите файл из Media Library. В блок будет записан `publicPath`.</p>
                  </div>
                  <button type="button" className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" onClick={() => setMediaPickerOpen(false)}>Закрыть</button>
                </div>
                {mediaLoading && <p className="mt-4 text-sm text-slate-500">Загрузка файлов...</p>}
                {!mediaLoading && (
                  <div className="mt-4 max-h-[55vh] space-y-3 overflow-y-auto">
                    {mediaAssets.map((asset) => (
                      <article key={asset.id} className="rounded-xl border border-slate-200 p-3">
                        <div className="flex items-center justify-between gap-3">
                          <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-slate-900">{asset.originalName}</p>
                            <p className="truncate text-xs text-slate-500">{asset.publicPath}</p>
                          </div>
                          <button type="button" className="shrink-0 rounded-lg bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800" onClick={() => selectTextImageAsset(asset)}>Выбрать</button>
                        </div>
                      </article>
                    ))}
                  </div>
                )}
              </section>
            </div>
          )}

          {selected && (
            <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
              <h3 className="text-base font-semibold text-slate-950">История публикаций</h3>
              {revisions.length === 0 && <p className="mt-2 text-sm text-slate-500">Публикаций пока нет.</p>}
              {revisions.map((revision) => (
                <div key={revision.id} className="mt-3 flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                  <div>
                    <p className="font-medium text-slate-900">v{revision.version} · {revision.title}</p>
                    <p className="text-xs text-slate-500">{revision.path} · {revision.createdAt} · {revision.comment ?? 'без комментария'}</p>
                  </div>
                  <button type="button" className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" onClick={() => { void rollbackRevision(revision) }}>Откатить</button>
                </div>
              ))}
            </section>
          )}
        </div>
      </div>

      {seoAuditToast && (
        <div className={['fixed bottom-6 right-6 z-50 max-w-lg rounded-2xl border bg-white p-5 shadow-2xl', seoAuditToast.passed ? 'border-emerald-200' : 'border-red-200'].join(' ')} role="status">
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className={['text-sm font-semibold', seoAuditToast.passed ? 'text-emerald-700' : 'text-red-700'].join(' ')}>
                {seoAuditToast.passed ? 'Checklist пройден' : 'Checklist нашёл замечания'}
              </p>
              <p className="mt-1 text-sm text-slate-600">
                {seoAuditToast.issues.length === 0 ? 'SEO-проблем не найдено.' : `Найдено замечаний: ${seoAuditToast.issues.length}`}
              </p>
            </div>
            <button type="button" className="text-sm font-semibold text-slate-400 hover:text-slate-700" onClick={() => setSeoAuditToast(null)}>Закрыть</button>
          </div>
          {seoAuditToast.issues.length > 0 && (
            <ul className="mt-4 max-h-72 space-y-2 overflow-auto text-sm text-slate-700">
              {seoAuditToast.issues.map((issue) => (
                <li key={issue.code + issue.field} className="rounded-lg bg-slate-50 px-3 py-2">
                  <span className="font-semibold">{issue.severity}</span> {issue.message}
                  <span className="text-xs text-slate-500"> {issue.field}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </section>
  )
}
