<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { apiRequest } from '../api/client'
import type { ContentPageDetail, ContentPageItem } from '../types/api'

const pages = ref<ContentPageItem[]>([])
const selected = ref<ContentPageDetail | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const previewUrl = ref<string | null>(null)
const seoAudit = ref<{ passed: boolean; issues: Array<{ severity: string; code: string; message: string; field: string }> } | null>(null)

const form = reactive({
  type: 'landing',
  title: '',
  slug: '',
  path: '',
  h1: '',
  template: 'default',
  sortOrder: 0,
  isIndexable: true,
  metaDescription: '',
  canonicalUrl: '',
  ogTitle: '',
  ogDescription: '',
  ogImage: '',
  jsonLd: '',
})

function fillForm(page: ContentPageDetail): void {
  form.type = page.type
  form.title = page.title
  form.slug = page.slug
  form.path = page.path
  form.h1 = page.h1
  form.template = page.template
  form.sortOrder = page.sortOrder
  form.isIndexable = page.isIndexable
  form.metaDescription = page.seo.metaDescription ?? ''
  form.canonicalUrl = page.seo.canonicalUrl ?? ''
  form.ogTitle = page.seo.ogTitle ?? ''
  form.ogDescription = page.seo.ogDescription ?? ''
  form.ogImage = page.seo.ogImage ?? ''
  form.jsonLd = page.seo.jsonLd ? JSON.stringify(page.seo.jsonLd, null, 2) : ''
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

async function loadPages(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    const response = await apiRequest<{ pages: ContentPageItem[] }>('/admin/api/content/pages')
    pages.value = response.pages
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
  fillForm(selected.value)
}

async function savePage(): Promise<void> {
  error.value = null

  try {
    if (selected.value === null) {
      const created = await apiRequest<ContentPageItem>('/admin/api/content/pages', {
        method: 'POST',
        body: pagePayload(),
      })
      await loadPages()
      await selectPage(created.id)
      return
    }

    await apiRequest<ContentPageItem>(`/admin/api/content/pages/${selected.value.id}`, {
      method: 'PUT',
      body: pagePayload(),
    })
    await apiRequest<ContentPageItem>(`/admin/api/content/pages/${selected.value.id}/seo`, {
      method: 'PUT',
      body: seoPayload(),
    })
    await loadPages()
    await selectPage(selected.value.id)
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось сохранить страницу'
  }
}

async function publishPage(): Promise<void> {
  if (selected.value === null) {
    return
  }

  await apiRequest<ContentPageItem>(`/admin/api/content/pages/${selected.value.id}/publish`, { method: 'POST' })
  await loadPages()
  await selectPage(selected.value.id)
}

async function buildPreviewLink(): Promise<void> {
  if (selected.value === null) {
    return
  }

  const response = await apiRequest<{ previewUrl: string }>(`/admin/api/content/pages/${selected.value.id}/preview-link`)
  previewUrl.value = response.previewUrl
}

async function runSeoAudit(): Promise<void> {
  if (selected.value === null) {
    return
  }

  seoAudit.value = await apiRequest<{ passed: boolean; issues: Array<{ severity: string; code: string; message: string; field: string }> }>(
    `/admin/api/seo/audit/pages/${selected.value.id}`,
  )
}

onMounted(loadPages)
</script>

<template>
  <section class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="text-lg font-semibold text-slate-950">Страницы и SEO</h2>
          <p class="mt-1 text-sm text-slate-600">Минимальный редактор контента: создание, SEO, публикация и preview.</p>
        </div>
        <button
          type="button"
          class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
          @click="selected = null; previewUrl = null"
        >
          Новая страница
        </button>
      </div>

      <p v-if="error" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
    </div>

    <div class="grid grid-cols-[320px_1fr] gap-6">
      <aside class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="mb-3 text-sm font-semibold text-slate-700">Список страниц</p>
        <p v-if="loading" class="text-sm text-slate-500">Загрузка...</p>
        <button
          v-for="page in pages"
          :key="page.id"
          type="button"
          class="mb-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-left text-sm hover:bg-slate-50"
          @click="selectPage(page.id)"
        >
          <span class="block font-medium text-slate-900">{{ page.title }}</span>
          <span class="block text-xs text-slate-500">{{ page.path }} · {{ page.status }}</span>
        </button>
      </aside>

      <form class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="savePage">
        <div class="grid grid-cols-2 gap-4">
          <label class="text-sm font-medium text-slate-700">
            Тип
            <input v-model="form.type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          </label>
          <label class="text-sm font-medium text-slate-700">
            Шаблон
            <input v-model="form.template" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
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
            Slug
            <input v-model="form.slug" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          </label>
          <label class="text-sm font-medium text-slate-700">
            URL path
            <input v-model="form.path" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          </label>
        </div>

        <label class="mt-4 flex items-center gap-2 text-sm text-slate-700">
          <input v-model="form.isIndexable" type="checkbox" class="rounded border-slate-300">
          Разрешить индексацию
        </label>

        <div class="mt-6 grid gap-4">
          <label class="text-sm font-medium text-slate-700">
            Meta description
            <textarea v-model="form.metaDescription" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
          </label>
          <label class="text-sm font-medium text-slate-700">
            Canonical URL
            <input v-model="form.canonicalUrl" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          </label>
          <label class="text-sm font-medium text-slate-700">
            OG title
            <input v-model="form.ogTitle" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          </label>
          <label class="text-sm font-medium text-slate-700">
            OG description
            <textarea v-model="form.ogDescription" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
          </label>
          <label class="text-sm font-medium text-slate-700">
            OG image
            <input v-model="form.ogImage" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
          </label>
          <label class="text-sm font-medium text-slate-700">
            JSON-LD
            <textarea
              v-model="form.jsonLd"
              rows="7"
              placeholder='[{"@context":"https://schema.org","@type":"Product","name":"Забор"}]'
              class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs"
            />
          </label>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
          <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            Сохранить
          </button>
          <button
            v-if="selected"
            type="button"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            @click="publishPage"
          >
            Опубликовать
          </button>
          <button
            v-if="selected"
            type="button"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            @click="buildPreviewLink"
          >
            Preview
          </button>
          <a v-if="previewUrl" :href="previewUrl" target="_blank" rel="noreferrer" class="text-sm font-medium text-emerald-700">
            Открыть предпросмотр
          </a>
          <button
            v-if="selected"
            type="button"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            @click="runSeoAudit"
          >
            SEO audit
          </button>
        </div>

        <div v-if="seoAudit" class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-sm font-semibold" :class="seoAudit.passed ? 'text-emerald-700' : 'text-red-700'">
            {{ seoAudit.passed ? 'SEO audit: blocking issues not found' : 'SEO audit: blocking issues found' }}
          </p>
          <ul v-if="seoAudit.issues.length > 0" class="mt-3 space-y-2 text-sm text-slate-700">
            <li v-for="issue in seoAudit.issues" :key="issue.code + issue.field" class="rounded-lg bg-white px-3 py-2">
              <span class="font-semibold">{{ issue.severity }}</span>
              <span class="ml-2">{{ issue.message }}</span>
              <span class="ml-2 text-xs text-slate-500">{{ issue.field }}</span>
            </li>
          </ul>
        </div>
      </form>
    </div>
  </section>
</template>
