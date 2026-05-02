<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiRequest } from '../api/client'
import type { SettingItem } from '../types/api'

const settings = ref<SettingItem[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    settings.value = await apiRequest<SettingItem[]>('/admin/api/settings')
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить настройки.'
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Settings</p>
        <h2 class="mt-2 text-2xl font-bold text-slate-950">Настройки проекта</h2>
      </div>
      <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
        Добавить настройку
      </button>
    </div>

    <p v-if="isLoading" class="mt-6 text-slate-600">Загрузка...</p>
    <p v-else-if="error" class="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>
    <div v-else-if="settings.length === 0" class="mt-6 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
      Настройки пока не созданы.
    </div>
    <div v-else class="mt-6 overflow-hidden rounded-xl border border-slate-200">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-600">
          <tr>
            <th class="px-4 py-3 font-semibold">Scope</th>
            <th class="px-4 py-3 font-semibold">Key</th>
            <th class="px-4 py-3 font-semibold">Value</th>
            <th class="px-4 py-3 font-semibold">Updated</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="setting in settings" :key="setting.id">
            <td class="px-4 py-3 font-medium text-slate-950">{{ setting.scope }}</td>
            <td class="px-4 py-3 text-slate-700">{{ setting.key }}</td>
            <td class="px-4 py-3 text-slate-600">{{ JSON.stringify(setting.value) }}</td>
            <td class="px-4 py-3 text-slate-500">{{ setting.updatedAt }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
