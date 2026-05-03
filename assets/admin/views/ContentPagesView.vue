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
const loading = ref(false)
const saving = ref(false)
const statusChanging = ref<string | null>(null)
const error = ref<string | null>(null)
const previewUrl = ref<string | null>(null)
const seoAudit = ref<{ passed: boolean; issues: Array<{ severity: string; code: string; message: string; field: string }> } | null>(null)

type PageStatus = ContentPageItem['status']

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
const hiddenStatusActions = new Set<PageStatus>(['published', 'scheduled', 'deleted'])
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
  previewUrl.value = null
  seoAudit.value = null
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

function fillBlockForm(block: ContentBlockItem): void {
  selectedBlockId.value = block.id
  blockForm.type = block.type
  blockForm.name = block.name
  blockForm.position = block.position
  blockForm.isEnabled = block.isEnabled
  blockForm.visibility = block.visibility
  blockForm.content = JSON.stringify(block.content, null, 2)
  blockForm.settings = JSON.stringify(block.settings, null, 2)
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
  seoAudit.value = null
  selected.value = await apiRequest<ContentPageDetail>(`/admin/api/content/pages/${id}`)
  fillPageForm(selected.value)
  selectedBlockId.value = selected.value.blocks[0]?.id ?? null
  if (selectedBlock.value) {
    fillBlockForm(selectedBlock.value)
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
  error.value = null
  saving.value = true
  try {
    if (selected.value === null) {
      const created = await apiRequest<ContentPageItem>('/admin/api/content/pages', { method: 'POST', body: pagePayload() })
      await loadPages()
      await selectPage(created.id)
      await createBlocksFromTemplate()
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
  }
}

async function createBlocksFromTemplate(): Promise<void> {
  if (!selected.value || selected.value.blocks.length > 0) {
    return
  }
  const template = templates.value.find((item) => item.code === form.template)
  if (!template) {
    return
  }
  for (const block of template.blocksSchema) {
    await apiRequest(`/admin/api/content/pages/${selected.value.id}/blocks`, {
      method: 'POST',
      body: {
        type: block.type,
        name: block.name,
        position: block.position,
        content: block.content,
        settings: block.settings,
        isEnabled: block.isEnabled,
      },
    })
  }
  await selectPage(selected.value.id)
}

async function saveBlock(): Promise<void> {
  if (!selected.value) {
    return
  }
  error.value = null
  try {
    if (!selectedBlock.value) {
      await apiRequest(`/admin/api/content/pages/${selected.value.id}/blocks`, { method: 'POST', body: blockPayload() })
    } else {
      await apiRequest(`/admin/api/content/blocks/${selectedBlock.value.id}`, { method: 'PUT', body: blockPayload() })
    }
    await selectPage(selected.value.id)
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось сохранить блок'
  }
}

async function deleteBlock(block: ContentBlockItem): Promise<void> {
  if (!selected.value) {
    return
  }
  await apiRequest(`/admin/api/content/blocks/${block.id}`, { method: 'DELETE' })
  await selectPage(selected.value.id)
}

async function moveBlock(block: ContentBlockItem, direction: -1 | 1): Promise<void> {
  if (!selected.value) {
    return
  }
  const sorted = [...selected.value.blocks].sort((left, right) => left.position - right.position)
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
}

async function publishPage(): Promise<void> {
  if (!selected.value || !canPublishSelected.value || statusChanging.value !== null) {
    return
  }
  error.value = null
  statusChanging.value = 'published'
  try {
    await apiRequest<ContentPageItem>(`/admin/api/content/pages/${selected.value.id}/publish`, {
      method: 'POST',
      body: { comment: 'Published from Page Engine editor' },
    })
    await loadPages()
    await selectPage(selected.value.id)
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
  seoAudit.value = await apiRequest<{ passed: boolean; issues: Array<{ severity: string; code: string; message: string; field: string }> }>(
    `/admin/api/seo/audit/pages/${selected.value.id}`,
  )
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
          <h2 class="text-lg font-semibold text-slate-950">Page Engine</h2>
          <p class="mt-1 text-sm text-slate-600">Страницы, блоки, SEO, preview, публикация и история версий.</p>
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
            <span class="block text-xs text-slate-500">{{ page.path }} · {{ page.status }}</span>
          </button>
        </div>

        <div v-if="selected" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="mb-3 flex items-center justify-between">
            <p class="text-sm font-semibold text-slate-700">Блоки</p>
            <button type="button" class="text-xs font-semibold text-emerald-700" @click="startNewBlock()">+ блок</button>
          </div>
          <button v-for="block in selected.blocks" :key="block.id" type="button" class="mb-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-left text-sm hover:bg-slate-50" @click="fillBlockForm(block)">
            <span class="block font-medium text-slate-900">{{ block.position + 1 }}. {{ block.name }}</span>
            <span class="block text-xs text-slate-500">{{ block.type }} · {{ block.isEnabled ? 'visible' : 'hidden' }}</span>
          </button>
        </div>
      </aside>

      <div class="space-y-6">
        <form class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="savePage">
          <div class="grid grid-cols-3 gap-4">
            <label class="text-sm font-medium text-slate-700">
              Тип
              <select v-model="form.type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <option v-for="type in pageTypes" :key="type" :value="type">{{ type }}</option>
              </select>
            </label>
            <label class="text-sm font-medium text-slate-700">
              Шаблон
              <select v-model="form.template" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <option v-for="template in templatesForType" :key="template.code" :value="template.code">{{ template.name }}</option>
                <option value="default">default</option>
              </select>
            </label>
            <label class="text-sm font-medium text-slate-700">
              Visibility
              <select v-model="form.visibility" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="public">public</option>
                <option value="hidden">hidden</option>
                <option value="unlisted">unlisted</option>
              </select>
            </label>
            <label class="text-sm font-medium text-slate-700">
              Заголовок
              <input v-model="form.title" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm font-medium text-slate-700">
              H1
              <input v-model="form.h1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm font-medium text-slate-700">
              URL path
              <input v-model="form.path" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            </label>
            <label class="text-sm font-medium text-slate-700">
              Slug
              <input v-model="form.slug" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
            </label>
            <label class="flex items-end gap-2 pb-2 text-sm text-slate-700">
              <input v-model="form.isIndexable" type="checkbox" class="rounded border-slate-300">
              Индексировать
            </label>
          </div>

          <div class="mt-6 grid gap-4">
            <label class="text-sm font-medium text-slate-700">Meta description<textarea v-model="form.metaDescription" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label>
            <label class="text-sm font-medium text-slate-700">Canonical URL<input v-model="form.canonicalUrl" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <div class="grid grid-cols-2 gap-4">
              <label class="text-sm font-medium text-slate-700">OG title<input v-model="form.ogTitle" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
              <label class="text-sm font-medium text-slate-700">OG image<input v-model="form.ogImage" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            </div>
            <label class="text-sm font-medium text-slate-700">OG description<textarea v-model="form.ogDescription" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" /></label>
            <label class="text-sm font-medium text-slate-700">JSON-LD<textarea v-model="form.jsonLd" rows="5" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs" /></label>
          </div>

          <div class="mt-6 flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">{{ saving ? 'Сохраняется...' : 'Сохранить' }}</button>
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
            <button v-if="selected" type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="buildPreviewLink">Preview</button>
            <a v-if="previewUrl" :href="previewUrl" target="_blank" rel="noreferrer" class="text-sm font-medium text-emerald-700">Открыть preview</a>
            <button v-if="selected" type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="runSeoAudit">Checklist</button>
          </div>
        </form>

        <section v-if="selected" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="mb-4 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-950">Block editor</h3>
            <select v-model="blockForm.type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="startNewBlock(blockForm.type)">
              <option v-for="schema in blockSchemas" :key="schema.type" :value="schema.type">{{ schema.label }}</option>
            </select>
          </div>
          <div class="grid grid-cols-3 gap-4">
            <label class="text-sm font-medium text-slate-700">Название<input v-model="blockForm.name" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Позиция<input v-model.number="blockForm.position" type="number" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="flex items-end gap-2 pb-2 text-sm text-slate-700"><input v-model="blockForm.isEnabled" type="checkbox" class="rounded border-slate-300"> Включен</label>
          </div>
          <div class="mt-4 grid grid-cols-2 gap-4">
            <label class="text-sm font-medium text-slate-700">Content JSON<textarea v-model="blockForm.content" rows="10" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs" /></label>
            <label class="text-sm font-medium text-slate-700">Settings JSON<textarea v-model="blockForm.settings" rows="10" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs" /></label>
          </div>
          <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800" @click="saveBlock">{{ selectedBlock ? 'Сохранить блок' : 'Добавить блок' }}</button>
            <button v-if="selectedBlock" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @click="moveBlock(selectedBlock, -1)">Вверх</button>
            <button v-if="selectedBlock" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @click="moveBlock(selectedBlock, 1)">Вниз</button>
            <button v-if="selectedBlock" type="button" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700" @click="deleteBlock(selectedBlock)">Удалить</button>
          </div>
        </section>

        <section v-if="seoAudit" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <p class="text-sm font-semibold" :class="seoAudit.passed ? 'text-emerald-700' : 'text-red-700'">{{ seoAudit.passed ? 'Checklist passed' : 'Checklist has blocking issues' }}</p>
          <ul class="mt-3 space-y-2 text-sm text-slate-700">
            <li v-for="issue in seoAudit.issues" :key="issue.code + issue.field" class="rounded-lg bg-slate-50 px-3 py-2">
              <span class="font-semibold">{{ issue.severity }}</span> {{ issue.message }} <span class="text-xs text-slate-500">{{ issue.field }}</span>
            </li>
          </ul>
        </section>

        <section v-if="selected" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h3 class="text-base font-semibold text-slate-950">Revisions</h3>
          <p v-if="revisions.length === 0" class="mt-2 text-sm text-slate-500">Публикаций пока нет.</p>
          <div v-for="revision in revisions" :key="revision.id" class="mt-3 flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
            <div>
              <p class="font-medium text-slate-900">v{{ revision.version }} · {{ revision.title }}</p>
              <p class="text-xs text-slate-500">{{ revision.path }} · {{ revision.createdAt }} · {{ revision.comment ?? 'без комментария' }}</p>
            </div>
            <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="rollbackRevision(revision)">Rollback</button>
          </div>
        </section>
      </div>
    </div>
  </section>
</template>
