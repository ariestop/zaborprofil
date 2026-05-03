<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { apiRequest } from '../api/client'
import type { MenuItem } from '../types/api'

const items = ref<MenuItem[]>([])
const error = ref<string | null>(null)

const form = reactive({
  position: 'header',
  label: '',
  url: '/',
  sortOrder: 0,
  isActive: true,
})

async function loadItems(): Promise<void> {
  const response = await apiRequest<{ items: MenuItem[] }>('/admin/api/menu/items')
  items.value = response.items
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

onMounted(loadItems)
</script>

<template>
  <section class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <h2 class="text-lg font-semibold text-slate-950">Меню сайта</h2>
      <p class="mt-1 text-sm text-slate-600">Позиции меню кэшируются и доступны в Twig через `menu_items(position)`.</p>

      <form class="mt-5 grid grid-cols-[140px_1fr_1fr_100px_auto] gap-3" @submit.prevent="createItem">
        <input v-model="form.position" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="header">
        <input v-model="form.label" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="Название">
        <input v-model="form.url" class="rounded-lg border border-slate-300 px-3 py-2" placeholder="/url/">
        <input v-model.number="form.sortOrder" type="number" class="rounded-lg border border-slate-300 px-3 py-2">
        <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Добавить</button>
      </form>
      <p v-if="error" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <article v-for="item in items" :key="item.id" class="flex items-center justify-between border-b border-slate-100 py-3 last:border-0">
        <div>
          <p class="font-medium text-slate-900">{{ item.label }}</p>
          <p class="text-sm text-slate-500">{{ item.position }} · {{ item.url }} · sort {{ item.sortOrder }}</p>
        </div>
        <span class="text-xs text-slate-500">{{ item.isActive ? 'active' : 'inactive' }}</span>
      </article>
    </div>
  </section>
</template>
