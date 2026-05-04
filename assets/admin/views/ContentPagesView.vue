<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { apiRequest } from '../api/client'
import type { BlockSchemaItem, ContentBlockItem, ContentPageDetail, ContentPageItem, PageRevisionItem, PageTemplateItem } from '../types/api'

const pages = ref<ContentPageItem[]>([])
const templates = ref<PageTemplateItem[]>([])
const blockSchemas = ref<BlockSchemaItem[]>([])
const revisions = ref<PageRevisionItem[]>([])
const selected = ref<ContentPageDetail | null>(null)
const selectedBlockId = ref<string | null>(null)
const blockEditorOpen = ref(false)
const loading = ref(false)
const saving = ref(false)
const savingMessage = ref('Сохраняется...')
const statusChanging = ref<string | null>(null)
const error = ref<string | null>(null)
const previewUrl = ref<string | null>(null)

type PageStatus = ContentPageItem['status']
type SeoAuditIssue = { severity: string; code: string; message: string; field: string }
type SeoAuditResult = { passed: boolean; issues: SeoAuditIssue[] }

const seoAuditToast = ref<SeoAuditResult | null>(null)
let seoAuditToastTimeout: number | null = null
type DropPlacement = 'before' | 'after'
const draggingBlockId = ref<string | null>(null)
const dragOverBlockId = ref<string | null>(null)
const dragOverPlacement = ref<DropPlacement | null>(null)
const reorderSaving = ref(false)

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
  image: { image: '/uploads/example.webp', alt: 'Готовый забор на участке', caption: 'Пример выполненной работы' },
  gallery: { items: [{ image: '/uploads/work-1.webp', alt: 'Забор из профнастила' }] },
  video: { url: 'https://rutube.ru/video/example/', title: 'Видеообзор объекта' },
  feature_grid: { items: [{ title: 'Собственное производство', text: 'Контролируем качество материалов и сроки.' }] },
  price_cards: { items: [{ title: 'Забор из профнастила', price: 'от 2 500 ₽/м', text: 'Материалы и монтаж под ключ.' }] },
  steps: { items: [{ title: 'Замер', text: 'Выезжаем на участок и уточняем параметры.' }] },
  faq: { items: [{ question: 'Сколько стоит установка забора?', answer: 'Стоимость зависит от материала, длины, высоты и условий монтажа.' }] },
  cta_form: { title: 'Получить расчёт', text: 'Оставьте контакты, и мы подготовим смету.', button: 'Оставить заявку' },
  telegram_cta: { title: 'Написать в Telegram', text: 'Ответим на вопросы и рассчитаем стоимость.', url: 'https://t.me/example' },
  contacts: { items: [{ title: 'Телефон', value: '+7 (999) 000-00-00' }] },
  map: { address: 'Москва, МКАД', embedUrl: 'https://yandex.ru/map-widget/v1/?um=example' },
  portfolio_grid: { items: [], title: 'Наши работы' },
  seo_text: { title: 'SEO-текст', text: 'Развёрнутый текст с описанием услуги, материалов, сроков и преимуществ.' },
  html_embed: { html: '<iframe src="https://example.com/widget" title="Виджет"></iframe>' },
  table: { columns: ['Услуга', 'Цена'], rows: [['Монтаж забора', 'от 2 500 ₽/м']] },
  accordion: { items: [{ title: 'Что входит в стоимость?', text: 'Материалы, доставка и монтаж указываются в смете.' }] },
  calculator_placeholder: { title: 'Калькулятор стоимости', text: 'Скоро здесь будет расчёт стоимости.' },
  before_after: { before: '/uploads/before.webp', after: '/uploads/after.webp' },
  review_cards: { items: [{ name: 'Иван', text: 'Работу выполнили аккуратно и в срок.' }] },
  documents: { items: [{ title: 'Сертификат', url: '/uploads/certificate.pdf' }] },
}
const blockSettingsExamples: Record<string, Record<string, unknown>> = {
  hero: { layout: 'default' },
  gallery: { columns: 3 },
  feature_grid: { columns: 3 },
  price_cards: { currency: 'RUB' },
  steps: { columns: 4 },
  faq: { schemaOrg: true },
  cta_form: { source: 'page_engine' },
  telegram_cta: { style: 'card' },
  contacts: { layout: 'cards' },
  map: { height: 420 },
  portfolio_grid: { limit: 6 },
  seo_text: { collapsed: false },
  html_embed: { sandbox: true },
  table: { responsive: 'scroll' },
  accordion: { multiple: false },
  image: { lazy: true },
  video: { lazy: true },
}

const form = reactive({
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

const blockForm = reactive({
  type: 'hero',
  name: '',
  position: 0,
  isEnabled: true,
  visibility: 'public',
  content: '{}',
  settings: '{}',
})

const selectedBlock = computed(() => selected.value?.blocks.find((block) => block.id === selectedBlockId.value) ?? null)
const templatesForType = computed(() => templates.value.filter((template) => template.pageType === form.type))
const selectedBlockSchema = computed(() => blockSchemas.value.find((item) => item.type === blockForm.type) ?? null)
const selectedBlockExampleContent = computed(() => blockContentExamples[blockForm.type] ?? selectedBlockSchema.value?.defaultContent ?? {})
const selectedBlockExampleSettings = computed(() => blockSettingsExamples[blockForm.type] ?? selectedBlockSchema.value?.defaultSettings ?? {})
const selectedBlocks = computed(() => [...(selected.value?.blocks ?? [])].sort((left, right) => left.position - right.position))
const availableStatusActions = computed(() => {
  if (!selected.value) {
    return []
  }

  return statusTransitions[selected.value.status].filter((status) => !hiddenStatusActions.has(status))
})
const canPublishSelected = computed(() => {
  if (!selected.value || selected.value.status === 'published') {
    return false
  }

  return statusTransitions[selected.value.status].includes('published')
})

function resetPageForm(): void {
  selected.value = null
  selectedBlockId.value = null
  blockEditorOpen.value = false
  previewUrl.value = null
  closeSeoAuditToast()
  revisions.value = []
  form.type = 'landing'
  form.title = ''
  form.slug = ''
  form.path = ''
  form.h1 = ''
  form.template = 'service_landing'
  form.sortOrder = 0
  form.isIndexable = true
  form.visibility = 'public'
  form.metaDescription = ''
  form.canonicalUrl = ''
  form.ogTitle = ''
  form.ogDescription = ''
  form.ogImage = ''
  form.jsonLd = ''
}

function fillPageForm(page: ContentPageDetail): void {
  form.type = page.type
  form.title = page.title
  form.slug = page.slug
  form.path = page.path
  form.h1 = page.h1
  form.template = page.template
  form.sortOrder = page.sortOrder
  form.isIndexable = page.isIndexable
  form.visibility = page.visibility
  form.metaDescription = page.seo.metaDescription ?? ''
  form.canonicalUrl = page.seo.canonicalUrl ?? ''
  form.ogTitle = page.seo.ogTitle ?? ''
  form.ogDescription = page.seo.ogDescription ?? ''
  form.ogImage = page.seo.ogImage ?? ''
  form.jsonLd = page.seo.jsonLd ? JSON.stringify(page.seo.jsonLd, null, 2) : ''
}

function fillBlockForm(block: ContentBlockItem, openEditor = true): void {
  selectedBlockId.value = block.id
  blockForm.type = block.type
  blockForm.name = block.name
  blockForm.position = block.position
  blockForm.isEnabled = block.isEnabled
  blockForm.visibility = block.visibility
  blockForm.content = JSON.stringify(block.content, null, 2)
  blockForm.settings = JSON.stringify(block.settings, null, 2)
  blockEditorOpen.value = openEditor
}

function parseObject(value: string, label: string): Record<string, unknown> {
  const parsed = JSON.parse(value || '{}') as unknown
  if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
    throw new Error(`${label} должен быть JSON-объектом.`)
  }

  return parsed as Record<string, unknown>
}

function parseJsonLdInput(): Record<string, unknown>[] | null {
  if (form.jsonLd.trim() === '') {
    return null
  }

  const decoded = JSON.parse(form.jsonLd) as unknown
  if (!Array.isArray(decoded) || decoded.some((item) => item === null || typeof item !== 'object' || Array.isArray(item))) {
    throw new Error('JSON-LD должен быть массивом объектов.')
  }

  return decoded as Record<string, unknown>[]
}

function pagePayload(): Record<string, unknown> {
  return {
    type: form.type,
    title: form.title,
    slug: form.slug,
    path: form.path,
    h1: form.h1,
    template: form.template,
    sortOrder: form.sortOrder,
    isIndexable: form.isIndexable,
    visibility: form.visibility,
  }
}

function seoPayload(): Record<string, unknown> {
  return {
    metaDescription: form.metaDescription || null,
    canonicalUrl: form.canonicalUrl || null,
    ogTitle: form.ogTitle || null,
    ogDescription: form.ogDescription || null,
    ogImage: form.ogImage || null,
    ogType: 'website',
    jsonLd: parseJsonLdInput(),
  }
}

function blockPayload(): Record<string, unknown> {
  return {
    type: blockForm.type,
    name: blockForm.name || blockForm.type,
    position: blockForm.position,
    isEnabled: blockForm.isEnabled,
    visibility: blockForm.visibility,
    content: parseObject(blockForm.content, 'Content'),
    settings: parseObject(blockForm.settings, 'Settings'),
  }
}

function statusLabel(status: PageStatus): string {
  return statusLabels[status] ?? status
}

function pageTypeLabel(type: string): string {
  return pageTypeLabels[type] ?? type
}

function visibilityLabel(visibility: string): string {
  return visibilityLabels[visibility] ?? visibility
}

function templateLabel(code: string, fallback?: string): string {
  return templateLabels[code] ?? fallback ?? code
}

function blockTypeLabel(type: string): string {
  return blockTypeLabels[type] ?? type
}

function exampleJson(value: Record<string, unknown>): string {
  return JSON.stringify(value, null, 2)
}

function statusButtonClass(): string {
  return 'rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60'
}

async function loadBaseData(): Promise<void> {
  const [pageResponse, templateResponse, schemaResponse] = await Promise.all([
    apiRequest<{ pages: ContentPageItem[] }>('/admin/api/content/pages'),
    apiRequest<{ templates: PageTemplateItem[] }>('/admin/api/content/templates'),
    apiRequest<{ blockSchemas: BlockSchemaItem[] }>('/admin/api/content/block-schemas'),
  ])
  pages.value = pageResponse.pages
  templates.value = templateResponse.templates
  blockSchemas.value = schemaResponse.blockSchemas
}

async function loadPages(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    await loadBaseData()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось загрузить страницы'
  } finally {
    loading.value = false
  }
}

async function selectPage(id: string): Promise<void> {
  previewUrl.value = null
  blockEditorOpen.value = false
  closeSeoAuditToast()
  selected.value = await apiRequest<ContentPageDetail>(`/admin/api/content/pages/${id}`)
  fillPageForm(selected.value)
  selectedBlockId.value = selectedBlocks.value[0]?.id ?? null
  if (selectedBlock.value) {
    fillBlockForm(selectedBlock.value, false)
  }
  await loadRevisions()
}

async function loadRevisions(): Promise<void> {
  if (!selected.value) {
    revisions.value = []
    return
  }
  const response = await apiRequest<{ revisions: PageRevisionItem[] }>(`/admin/api/content/pages/${selected.value.id}/revisions`)
  revisions.value = response.revisions
}

async function savePage(): Promise<void> {
  if (saving.value) {
    return
  }

  error.value = null
  saving.value = true
  savingMessage.value = 'Сохраняется...'
  try {
    if (selected.value === null) {
      const created = await apiRequest<ContentPageItem>('/admin/api/content/pages', { method: 'POST', body: pagePayload() })
      await loadPages()
      await selectPage(created.id)
      await createBlocksFromTemplate(created.id)
      return
    }

    await apiRequest<ContentPageItem>(`/admin/api/content/pages/${selected.value.id}`, { method: 'PUT', body: pagePayload() })
    await apiRequest<ContentPageItem>(`/admin/api/content/pages/${selected.value.id}/seo`, { method: 'PUT', body: seoPayload() })
    await loadPages()
    await selectPage(selected.value.id)
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось сохранить страницу'
  } finally {
    saving.value = false
    savingMessage.value = 'Сохраняется...'
  }
}

async function persistSelectedPageDraft(): Promise<string | null> {
  if (!selected.value) {
    return null
  }

  const pageId = selected.value.id
  await apiRequest<ContentPageItem>(`/admin/api/content/pages/${pageId}`, { method: 'PUT', body: pagePayload() })
  await apiRequest<ContentPageItem>(`/admin/api/content/pages/${pageId}/seo`, { method: 'PUT', body: seoPayload() })

  return pageId
}

async function createBlocksFromTemplate(pageId: string): Promise<void> {
  if (!selected.value || selected.value.blocks.length > 0) {
    return
  }
  const template = templates.value.find((item) => item.code === form.template)
  if (!template) {
    return
  }
  savingMessage.value = 'Создаются стартовые блоки...'
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

async function saveBlock(): Promise<void> {
  if (!selected.value) {
    return
  }
  error.value = null
  const pageId = selected.value.id
  try {
    if (!selectedBlock.value) {
      await apiRequest(`/admin/api/content/pages/${pageId}/blocks`, { method: 'POST', body: blockPayload() })
    } else {
      await apiRequest(`/admin/api/content/blocks/${selectedBlock.value.id}`, { method: 'PUT', body: blockPayload() })
    }
    await selectPage(pageId)
    closeBlockEditor()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось сохранить блок'
  }
}

async function deleteBlock(block: ContentBlockItem): Promise<void> {
  if (!selected.value) {
    return
  }
  const pageId = selected.value.id
  await apiRequest(`/admin/api/content/blocks/${block.id}`, { method: 'DELETE' })
  await selectPage(pageId)
  closeBlockEditor()
}

async function moveBlock(block: ContentBlockItem, direction: -1 | 1): Promise<void> {
  if (!selected.value) {
    return
  }
  const sorted = selectedBlocks.value
  const index = sorted.findIndex((item) => item.id === block.id)
  const target = index + direction
  if (target < 0 || target >= sorted.length) {
    return
  }
  const [removed] = sorted.splice(index, 1)
  sorted.splice(target, 0, removed)
  await apiRequest(`/admin/api/content/pages/${selected.value.id}/blocks/reorder`, {
    method: 'POST',
    body: { blockIds: sorted.map((item) => item.id) },
  })
  await selectPage(selected.value.id)
}

function blockCardClass(block: ContentBlockItem): string[] {
  const classes = [
    selectedBlockId.value === block.id ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200',
    reorderSaving.value ? 'cursor-wait' : 'cursor-grab',
  ]

  if (draggingBlockId.value === block.id) {
    classes.push('opacity-50')
  }

  if (dragOverBlockId.value === block.id && draggingBlockId.value !== block.id) {
    classes.push(dragOverPlacement.value === 'before' ? 'ring-2 ring-emerald-400 ring-offset-2' : 'ring-2 ring-emerald-600 ring-offset-2')
  }

  return classes
}

function resolveDropPlacement(event: DragEvent): DropPlacement {
  if (event.currentTarget instanceof HTMLElement) {
    const rect = event.currentTarget.getBoundingClientRect()
    return event.clientY > rect.top + rect.height / 2 ? 'after' : 'before'
  }

  return 'after'
}

function buildReorderedBlocks(draggedId: string, targetId: string, placement: DropPlacement): ContentBlockItem[] | null {
  if (draggedId === targetId) {
    return null
  }

  const currentBlocks = selectedBlocks.value
  const reordered = [...currentBlocks]
  const draggedIndex = reordered.findIndex((item) => item.id === draggedId)
  if (draggedIndex === -1) {
    return null
  }

  const [dragged] = reordered.splice(draggedIndex, 1)
  const targetIndex = reordered.findIndex((item) => item.id === targetId)
  if (targetIndex === -1) {
    return null
  }

  reordered.splice(placement === 'after' ? targetIndex + 1 : targetIndex, 0, dragged)

  if (reordered.every((item, index) => item.id === currentBlocks[index]?.id)) {
    return null
  }

  return reordered
}

function applyBlockOrder(blocks: ContentBlockItem[]): void {
  if (!selected.value) {
    return
  }

  selected.value.blocks = blocks.map((block, position) => ({ ...block, position }))
}

function resetBlockDragState(): void {
  draggingBlockId.value = null
  dragOverBlockId.value = null
  dragOverPlacement.value = null
}

function handleBlockDragStart(event: DragEvent, block: ContentBlockItem): void {
  if (reorderSaving.value || selectedBlocks.value.length < 2) {
    event.preventDefault()
    return
  }

  draggingBlockId.value = block.id
  event.dataTransfer?.setData('text/plain', block.id)
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
  }
}

function handleBlockDragOver(event: DragEvent, block: ContentBlockItem): void {
  if (!draggingBlockId.value || draggingBlockId.value === block.id || reorderSaving.value) {
    return
  }

  event.preventDefault()
  dragOverBlockId.value = block.id
  dragOverPlacement.value = resolveDropPlacement(event)
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move'
  }
}

async function handleBlockDrop(event: DragEvent, targetBlock: ContentBlockItem): Promise<void> {
  event.preventDefault()

  if (!selected.value || reorderSaving.value) {
    resetBlockDragState()
    return
  }

  const draggedId = draggingBlockId.value ?? event.dataTransfer?.getData('text/plain') ?? null
  if (!draggedId) {
    resetBlockDragState()
    return
  }

  const reordered = buildReorderedBlocks(draggedId, targetBlock.id, resolveDropPlacement(event))
  if (reordered === null) {
    resetBlockDragState()
    return
  }

  const pageId = selected.value.id
  const previousBlocks = selected.value.blocks
  reorderSaving.value = true
  error.value = null
  resetBlockDragState()
  applyBlockOrder(reordered)

  try {
    await apiRequest(`/admin/api/content/pages/${pageId}/blocks/reorder`, {
      method: 'POST',
      body: { blockIds: reordered.map((item) => item.id) },
    })
    await selectPage(pageId)
  } catch (caught) {
    if (selected.value?.id === pageId) {
      selected.value.blocks = previousBlocks
    }
    error.value = caught instanceof Error ? caught.message : 'Не удалось сохранить порядок блоков'
  } finally {
    reorderSaving.value = false
  }
}

function startNewBlock(type = 'hero'): void {
  selectedBlockId.value = null
  const schema = blockSchemas.value.find((item) => item.type === type)
  blockForm.type = type
  blockForm.name = schema?.label ?? type
  blockForm.position = selected.value?.blocks.length ?? 0
  blockForm.isEnabled = true
  blockForm.visibility = 'public'
  blockForm.content = JSON.stringify(schema?.defaultContent ?? {}, null, 2)
  blockForm.settings = JSON.stringify(schema?.defaultSettings ?? {}, null, 2)
  blockEditorOpen.value = true
}

function closeBlockEditor(): void {
  blockEditorOpen.value = false
}

async function publishPage(): Promise<void> {
  if (!selected.value || !canPublishSelected.value || statusChanging.value !== null) {
    return
  }
  error.value = null
  statusChanging.value = 'published'
  try {
    const pageId = await persistSelectedPageDraft()
    if (pageId === null) {
      return
    }

    await apiRequest<ContentPageItem>(`/admin/api/content/pages/${pageId}/publish`, {
      method: 'POST',
      body: { comment: 'Published from Page Engine editor' },
    })
    await loadPages()
    await selectPage(pageId)
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось опубликовать страницу'
  } finally {
    statusChanging.value = null
  }
}

async function changeStatus(status: PageStatus): Promise<void> {
  if (!selected.value || !availableStatusActions.value.includes(status) || statusChanging.value !== null) {
    return
  }
  error.value = null
  statusChanging.value = status
  try {
    await apiRequest<ContentPageItem>(`/admin/api/content/pages/${selected.value.id}/status`, { method: 'PATCH', body: { status } })
    await loadPages()
    await selectPage(selected.value.id)
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось изменить статус'
  } finally {
    statusChanging.value = null
  }
}

async function buildPreviewLink(): Promise<void> {
  if (!selected.value) {
    return
  }
  const response = await apiRequest<{ previewUrl: string }>(`/admin/api/content/pages/${selected.value.id}/preview-link`)
  previewUrl.value = response.previewUrl
}

async function runSeoAudit(): Promise<void> {
  if (!selected.value) {
    return
  }
  error.value = null
  const pageId = selected.value.id
  try {
    await persistSelectedPageDraft()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось сохранить SEO перед проверкой'
    return
  }
  const audit = await apiRequest<SeoAuditResult>(
    `/admin/api/seo/audit/pages/${pageId}`,
  )
  showSeoAuditToast(audit)
}

function showSeoAuditToast(audit: SeoAuditResult): void {
  seoAuditToast.value = audit

  if (seoAuditToastTimeout !== null) {
    window.clearTimeout(seoAuditToastTimeout)
  }

  seoAuditToastTimeout = window.setTimeout(() => {
    seoAuditToast.value = null
    seoAuditToastTimeout = null
  }, 10000)
}

function closeSeoAuditToast(): void {
  seoAuditToast.value = null

  if (seoAuditToastTimeout !== null) {
    window.clearTimeout(seoAuditToastTimeout)
    seoAuditToastTimeout = null
  }
}

async function rollbackRevision(revision: PageRevisionItem): Promise<void> {
  if (!selected.value) {
    return
  }
  await apiRequest(`/admin/api/content/pages/${selected.value.id}/revisions/${revision.id}/rollback`, { method: 'POST' })
  await loadPages()
  await selectPage(selected.value.id)
}

onMounted(loadPages)
</script>

<template>
  <section class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="text-lg font-semibold text-slate-950">Редактор страниц</h2>
          <p class="mt-1 text-sm text-slate-600">Создание страниц, блоки контента, SEO-поля, предпросмотр, публикация и история версий.</p>
        </div>
        <button type="button" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800" @click="resetPageForm">
          Создать страницу
        </button>
      </div>
      <p v-if="error" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
    </div>

    <div class="grid grid-cols-[280px_1fr] gap-6">
      <aside class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="mb-3 text-sm font-semibold text-slate-700">Страницы</p>
          <p v-if="loading" class="text-sm text-slate-500">Загрузка...</p>
          <button v-for="page in pages" :key="page.id" type="button" class="mb-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-left text-sm hover:bg-slate-50" @click="selectPage(page.id)">
            <span class="block font-medium text-slate-900">{{ page.title }}</span>
            <span class="block text-xs text-slate-500">{{ page.path }} · {{ statusLabel(page.status) }}</span>
          </button>
        </div>

        <div v-if="selected" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="mb-3 flex items-start justify-between gap-3">
            <div>
              <p class="text-sm font-semibold text-slate-700">Блоки</p>
              <p class="mt-1 text-xs text-slate-500">
                {{ reorderSaving ? 'Сохраняем новый порядок...' : 'Перетащите блок, чтобы изменить порядок вывода.' }}
              </p>
            </div>
            <button type="button" class="shrink-0 text-xs font-semibold text-emerald-700" @click="startNewBlock()">+ блок</button>
          </div>
          <button
            v-for="block in selectedBlocks"
            :key="block.id"
            type="button"
            draggable="true"
            class="mb-2 block w-full rounded-lg border px-3 py-2 text-left text-sm transition hover:bg-slate-50 disabled:opacity-70"
            :class="blockCardClass(block)"
            :disabled="reorderSaving"
            @click="fillBlockForm(block)"
            @dragstart="handleBlockDragStart($event, block)"
            @dragover="handleBlockDragOver($event, block)"
            @drop="handleBlockDrop($event, block)"
            @dragend="resetBlockDragState"
          >
            <span class="flex items-start gap-2">
              <span class="mt-0.5 select-none rounded border border-slate-200 px-1 text-[10px] uppercase text-slate-400" aria-hidden="true">drag</span>
              <span class="min-w-0">
                <span class="block font-medium text-slate-900">{{ block.position + 1 }}. {{ block.name }}</span>
                <span class="block text-xs text-slate-500">{{ blockTypeLabel(block.type) }} · {{ block.isEnabled ? 'включён' : 'выключен' }}</span>
              </span>
            </span>
          </button>
          <p v-if="selectedBlocks.length === 0" class="text-sm text-slate-500">Блоков пока нет.</p>
        </div>
      </aside>

      <div class="space-y-6">
        <form class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="savePage">
          <div class="mb-5">
            <h3 class="text-base font-semibold text-slate-950">Основные поля страницы</h3>
            <p class="mt-1 text-sm text-slate-600">Заполните адрес, заголовки и SEO-описание. Для публикации критичны путь, заголовок, H1 и корректные включённые блоки.</p>
          </div>
          <div class="grid grid-cols-3 gap-4">
            <label class="text-sm font-medium text-slate-700">
              Тип страницы
              <select v-model="form.type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <option v-for="type in pageTypes" :key="type" :value="type">{{ pageTypeLabel(type) }}</option>
              </select>
              <span class="mt-1 block text-xs font-normal text-slate-500">Определяет назначение страницы и SEO-рекомендации. Пример: «Услуга» для страницы монтажа забора.</span>
            </label>
            <label class="text-sm font-medium text-slate-700">
              Шаблон
              <select v-model="form.template" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <option v-for="template in templatesForType" :key="template.code" :value="template.code">{{ templateLabel(template.code, template.name) }}</option>
                <option value="default">{{ templateLabel('default') }}</option>
              </select>
              <span class="mt-1 block text-xs font-normal text-slate-500">Создаёт стартовый набор блоков. Пример: «Страница услуги».</span>
            </label>
            <label class="text-sm font-medium text-slate-700">
              Видимость
              <select v-model="form.visibility" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="public">{{ visibilityLabel('public') }}</option>
                <option value="hidden">{{ visibilityLabel('hidden') }}</option>
                <option value="unlisted">{{ visibilityLabel('unlisted') }}</option>
              </select>
              <span class="mt-1 block text-xs font-normal text-slate-500">Публичная страница доступна посетителям и может попасть в sitemap.</span>
            </label>
            <label class="text-sm font-medium text-slate-700">
              Заголовок страницы
              <input v-model="form.title" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
              <span class="mt-1 block text-xs font-normal text-slate-500">Title для админки и SEO. Пример: «Заборы из профнастила под ключ».</span>
            </label>
            <label class="text-sm font-medium text-slate-700">
              H1 на странице
              <input v-model="form.h1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
              <span class="mt-1 block text-xs font-normal text-slate-500">Главный заголовок для посетителя. Пример: «Установка заборов из профнастила».</span>
            </label>
            <label class="text-sm font-medium text-slate-700">
              Адрес страницы
              <input v-model="form.path" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
              <span class="mt-1 block text-xs font-normal text-slate-500">Путь должен начинаться с «/». Пример: «/zabory-iz-profnastila/».</span>
            </label>
            <label class="text-sm font-medium text-slate-700">
              Часть URL
              <input v-model="form.slug" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
              <span class="mt-1 block text-xs font-normal text-slate-500">Короткий латинский идентификатор без слэшей. Пример: «zabory-iz-profnastila».</span>
            </label>
            <label class="flex items-end gap-2 pb-2 text-sm text-slate-700">
              <input v-model="form.isIndexable" type="checkbox" class="rounded border-slate-300">
              Индексировать
            </label>
          </div>

          <div class="mt-6 grid gap-4">
            <label class="text-sm font-medium text-slate-700">
              SEO-описание
              <textarea v-model="form.metaDescription" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
              <span class="mt-1 block text-xs font-normal text-slate-500">Описание для поискового сниппета, желательно 80-320 символов. Пример: «Производим и устанавливаем заборы из профнастила под ключ в Москве и области: замер, материалы, монтаж и гарантия.»</span>
            </label>
            <label class="text-sm font-medium text-slate-700">
              Канонический URL
              <input v-model="form.canonicalUrl" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
              <span class="mt-1 block text-xs font-normal text-slate-500">Заполняйте только если канонический адрес отличается. Пример: «https://zaborprofil.ru/zabory-iz-profnastila/».</span>
            </label>
            <div class="grid grid-cols-2 gap-4">
              <label class="text-sm font-medium text-slate-700">
                Заголовок для соцсетей
                <input v-model="form.ogTitle" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <span class="mt-1 block text-xs font-normal text-slate-500">Используется в превью ссылки. Пример: «Заборы из профнастила под ключ».</span>
              </label>
              <label class="text-sm font-medium text-slate-700">
                Изображение для соцсетей
                <input v-model="form.ogImage" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <span class="mt-1 block text-xs font-normal text-slate-500">Абсолютный URL картинки. Пример: «https://zaborprofil.ru/uploads/og/zabor.webp».</span>
              </label>
            </div>
            <label class="text-sm font-medium text-slate-700">
              Описание для соцсетей
              <textarea v-model="form.ogDescription" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
              <span class="mt-1 block text-xs font-normal text-slate-500">Можно повторить SEO-описание или написать более рекламный текст.</span>
            </label>
            <label class="text-sm font-medium text-slate-700">
              JSON-LD разметка
              <textarea v-model="form.jsonLd" rows="5" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs" />
              <span class="mt-1 block text-xs font-normal text-slate-500">Необязательный массив объектов schema.org. Пример: [{"@context":"https://schema.org","@type":"LocalBusiness","name":"ЗаборПрофиль"}]</span>
            </label>
          </div>

          <div class="mt-6 flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving">{{ saving ? savingMessage : 'Сохранить' }}</button>
            <button v-if="canPublishSelected" type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60" :disabled="statusChanging !== null" @click="publishPage">
              {{ statusChanging === 'published' ? 'Публикуется...' : 'Опубликовать' }}
            </button>
            <template v-if="selected">
              <span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700">Текущий: {{ statusLabel(selected.status) }}</span>
              <button
                v-for="status in availableStatusActions"
                :key="status"
                type="button"
                :class="statusButtonClass()"
                :disabled="statusChanging !== null"
                @click="changeStatus(status)"
              >
                {{ statusChanging === status ? '...' : statusLabel(status) }}
              </button>
            </template>
            <button v-if="selected" type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="buildPreviewLink">Предпросмотр</button>
            <a v-if="previewUrl" :href="previewUrl" target="_blank" rel="noreferrer" class="text-sm font-medium text-emerald-700">Открыть предпросмотр</a>
            <button v-if="selected" type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="runSeoAudit">Проверить SEO</button>
          </div>
        </form>

        <div v-if="selected && blockEditorOpen" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/50 px-4 py-8" role="dialog" aria-modal="true" @click.self="closeBlockEditor">
          <section class="w-full max-w-5xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
          <div class="mb-4 flex items-start justify-between gap-4">
            <div>
              <h3 class="text-base font-semibold text-slate-950">Редактор блоков</h3>
              <p class="mt-1 text-sm text-slate-600">Блоки выводятся на публичной странице сверху вниз. JSON должен быть объектом в фигурных скобках.</p>
            </div>
            <div class="flex items-center gap-3">
              <select v-model="blockForm.type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="startNewBlock(blockForm.type)">
                <option v-for="schema in blockSchemas" :key="schema.type" :value="schema.type">{{ blockTypeLabel(schema.type) }}</option>
              </select>
              <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="closeBlockEditor">Закрыть</button>
            </div>
          </div>
          <div v-if="selectedBlockSchema" class="mb-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
            <p><span class="font-semibold text-slate-700">Назначение:</span> {{ selectedBlockSchema.description }}</p>
            <p v-if="selectedBlockSchema.requiredContentFields.length > 0" class="mt-1">
              <span class="font-semibold text-slate-700">Обязательные поля:</span> {{ selectedBlockSchema.requiredContentFields.join(', ') }}
            </p>
          </div>
          <div class="grid grid-cols-3 gap-4">
            <label class="text-sm font-medium text-slate-700">
              Название блока
              <input v-model="blockForm.name" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
              <span class="mt-1 block text-xs font-normal text-slate-500">Внутреннее название для редактора. Пример: «FAQ по установке».</span>
            </label>
            <label class="text-sm font-medium text-slate-700">
              Позиция
              <input v-model.number="blockForm.position" type="number" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
              <span class="mt-1 block text-xs font-normal text-slate-500">Чем меньше число, тем выше блок на странице.</span>
            </label>
            <label class="flex items-end gap-2 pb-7 text-sm text-slate-700"><input v-model="blockForm.isEnabled" type="checkbox" class="rounded border-slate-300"> Включён на странице</label>
          </div>
          <div class="mt-4 grid grid-cols-2 gap-4">
            <label class="text-sm font-medium text-slate-700">
              Контент блока JSON
              <textarea v-model="blockForm.content" rows="10" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs" />
              <span class="mt-1 block text-xs font-normal text-slate-500">Тексты, изображения, ссылки и списки для блока. Значение должно быть JSON-объектом.</span>
            </label>
            <label class="text-sm font-medium text-slate-700">
              Настройки блока JSON
              <textarea v-model="blockForm.settings" rows="10" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs" />
              <span class="mt-1 block text-xs font-normal text-slate-500">Внешний вид и поведение блока: колонки, режим отображения, schema.org. Значение должно быть JSON-объектом.</span>
            </label>
          </div>
          <div class="mt-4 grid grid-cols-2 gap-4">
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
              <p class="text-sm font-semibold text-slate-700">Пример контента для «{{ blockTypeLabel(blockForm.type) }}»</p>
              <pre class="mt-2 overflow-auto whitespace-pre-wrap text-xs text-slate-600">{{ exampleJson(selectedBlockExampleContent) }}</pre>
            </div>
            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
              <p class="text-sm font-semibold text-slate-700">Пример настроек</p>
              <pre class="mt-2 overflow-auto whitespace-pre-wrap text-xs text-slate-600">{{ exampleJson(selectedBlockExampleSettings) }}</pre>
            </div>
          </div>
          <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800" @click="saveBlock">{{ selectedBlock ? 'Сохранить блок' : 'Добавить блок' }}</button>
            <button v-if="selectedBlock" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @click="moveBlock(selectedBlock, -1)">Вверх</button>
            <button v-if="selectedBlock" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @click="moveBlock(selectedBlock, 1)">Вниз</button>
            <button v-if="selectedBlock" type="button" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700" @click="deleteBlock(selectedBlock)">Удалить</button>
          </div>
          </section>
        </div>

        <section v-if="selected" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h3 class="text-base font-semibold text-slate-950">История публикаций</h3>
          <p v-if="revisions.length === 0" class="mt-2 text-sm text-slate-500">Публикаций пока нет.</p>
          <div v-for="revision in revisions" :key="revision.id" class="mt-3 flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
            <div>
              <p class="font-medium text-slate-900">v{{ revision.version }} · {{ revision.title }}</p>
              <p class="text-xs text-slate-500">{{ revision.path }} · {{ revision.createdAt }} · {{ revision.comment ?? 'без комментария' }}</p>
            </div>
            <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="rollbackRevision(revision)">Откатить</button>
          </div>
        </section>
      </div>
    </div>

    <div
      v-if="seoAuditToast"
      class="fixed bottom-6 right-6 z-50 max-w-lg rounded-2xl border bg-white p-5 shadow-2xl"
      :class="seoAuditToast.passed ? 'border-emerald-200' : 'border-red-200'"
      role="status"
    >
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-sm font-semibold" :class="seoAuditToast.passed ? 'text-emerald-700' : 'text-red-700'">
            {{ seoAuditToast.passed ? 'Checklist пройден' : 'Checklist нашёл замечания' }}
          </p>
          <p class="mt-1 text-sm text-slate-600">
            {{ seoAuditToast.issues.length === 0 ? 'SEO-проблем не найдено.' : `Найдено замечаний: ${seoAuditToast.issues.length}` }}
          </p>
        </div>
        <button type="button" class="text-sm font-semibold text-slate-400 hover:text-slate-700" @click="closeSeoAuditToast">Закрыть</button>
      </div>
      <ul v-if="seoAuditToast.issues.length > 0" class="mt-4 max-h-72 space-y-2 overflow-auto text-sm text-slate-700">
        <li v-for="issue in seoAuditToast.issues" :key="issue.code + issue.field" class="rounded-lg bg-slate-50 px-3 py-2">
          <span class="font-semibold">{{ issue.severity }}</span> {{ issue.message }}
          <span class="text-xs text-slate-500">{{ issue.field }}</span>
        </li>
      </ul>
    </div>
  </section>
</template>
