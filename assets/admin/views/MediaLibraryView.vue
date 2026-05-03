<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiRequest } from '../api/client'
import type { MediaAssetItem } from '../types/api'

const assets = ref<MediaAssetItem[]>([])
const error = ref<string | null>(null)
const uploading = ref(false)

function csrfHeaderName(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="admin-csrf-header"]')?.content ?? 'X-CSRF-Token'
}

function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="admin-csrf-token"]')?.content ?? ''
}

async function loadAssets(): Promise<void> {
  const response = await apiRequest<{ assets: MediaAssetItem[] }>('/admin/api/media/assets')
  assets.value = response.assets
}

async function uploadFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) {
    return
  }

  uploading.value = true
  error.value = null
  const body = new FormData()
  body.set('file', file)

  try {
    const response = await fetch('/admin/api/media/assets', {
      method: 'POST',
      headers: {
        [csrfHeaderName()]: csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body,
    })

    if (!response.ok) {
      const payload = await response.json()
      throw new Error(String(payload.error ?? 'Не удалось загрузить файл'))
    }

    await loadAssets()
    input.value = ''
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось загрузить файл'
  } finally {
    uploading.value = false
  }
}

onMounted(loadAssets)
</script>

<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-slate-950">Media Library</h2>
    <p class="mt-1 text-sm text-slate-600">Безопасная загрузка изображений и PDF для контента и SEO.</p>

    <label class="mt-5 inline-flex cursor-pointer rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
      <input type="file" class="hidden" :disabled="uploading" @change="uploadFile">
      {{ uploading ? 'Загрузка...' : 'Загрузить файл' }}
    </label>
    <p v-if="error" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

    <div class="mt-6 grid gap-3">
      <article v-for="asset in assets" :key="asset.id" class="rounded-xl border border-slate-200 p-4">
        <p class="font-medium text-slate-900">{{ asset.originalName }}</p>
        <a :href="asset.publicPath" target="_blank" rel="noreferrer" class="text-sm text-emerald-700">{{ asset.publicPath }}</a>
        <p class="mt-1 text-xs text-slate-500">{{ asset.mimeType }} · {{ asset.size }} bytes</p>
      </article>
    </div>
  </section>
</template>
