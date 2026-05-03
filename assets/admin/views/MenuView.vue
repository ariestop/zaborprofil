<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { apiRequest } from '../api/client'
import type { MenuItem } from '../types/api'

interface MenuPositionOption {
  value: string
  label: string
}

const items = ref<MenuItem[]>([])
const positions = ref<MenuPositionOption[]>([
  { value: 'header', label: 'Header' },
  { value: 'footer', label: 'Footer' },
  { value: 'service', label: 'Service navigation' },
])
const error = ref<string | null>(null)

const form = reactive({
  position: 'header',
  label: '',
  url: '/',
  sortOrder: 0,
  isActive: true,
})

async function loadItems(): Promise<void> {
  const response = await apiRequest<{ items: MenuItem[], positions?: MenuPositionOption[] }>('/admin/api/menu/items')
  items.value = response.items
  positions.value = response.positions ?? positions.value
}

async function createItem(): Promise<void> {
  error.value = null

  try {
    await apiRequest<MenuItem>('/admin/api/menu/items', {
      method: 'POST',
      body: { ...form },
    })
    form.label = ''
    form.url = '/'
    await loadItems()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось сохранить пункт меню'
  }
}

async function updateItem(item: MenuItem): Promise<void> {
  error.value = null

  try {
    await apiRequest<MenuItem>(`/admin/api/menu/items/${item.id}`, {
      method: 'PUT',
      body: {
        position: item.position,
        label: item.label,
        url: item.url,
        sortOrder: item.sortOrder,
        isActive: item.isActive,
      },
    })
    await loadItems()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось обновить пункт меню'
  }
}

async function deleteItem(item: MenuItem): Promise<void> {
  error.value = null

  try {
    await apiRequest<void>(`/admin/api/menu/items/${item.id}`, { method: 'DELETE' })
    await loadItems()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Не удалось удалить пункт меню'
  }
}

onMounted(loadItems)
</script>

<template>
  <section class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="text-lg font-semibold text-slate-950">Меню сайта</h2>
      <p class="mt-1 text-sm text-slate-600">Header, footer и service navigation кэшируются и доступны в Twig через `menu_items(position)`.</p>

      <form class="mt-5 grid grid-cols-[140px_1fr_1fr_100px_auto] gap-3" @submit.prevent="createItem">
        <select v-model="form.position" class="rounded-lg border border-slate-300 px-3 py-2">
          <option v-for="position in positions" :key="position.value" :value="position.value">{{ position.label }}</option>
        </select>
        <input v-model="form.label" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="Название">
        <input v-model="form.url" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="/url/">
        <input v-model.number="form.sortOrder" type="number" class="rounded-lg border border-slate-300 px-3 py-2">
        <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Добавить</button>
      </form>
      <p v-if="error" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <article v-for="item in items" :key="item.id" class="grid grid-cols-[140px_1fr_1fr_100px_90px_auto] items-center gap-3 border-b border-slate-100 py-3 last:border-0">
        <select v-model="item.position" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <option v-for="position in positions" :key="position.value" :value="position.value">{{ position.label }}</option>
        </select>
        <input v-model="item.label" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input v-model="item.url" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input v-model.number="item.sortOrder" type="number" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
          <input v-model="item.isActive" type="checkbox" class="rounded border-slate-300">
          active
        </label>
        <div class="flex justify-end gap-2">
          <button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700" @click="updateItem(item)">
            Save
          </button>
          <button class="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-100" @click="deleteItem(item)">
            Delete
          </button>
        </div>
      </article>
    </div>
  </section>
</template>
