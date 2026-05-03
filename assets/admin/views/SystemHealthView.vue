<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiRequest } from '../api/client'
import type { SystemHealthResponse } from '../types/api'

const health = ref<SystemHealthResponse | null>(null)
const isLoading = ref(true)
const error = ref<string | null>(null)

async function loadHealth(): Promise<void> {
  isLoading.value = true
  error.value = null

  try {
    health.value = await apiRequest<SystemHealthResponse>('/admin/api/system/health')
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить health status.'
  } finally {
    isLoading.value = false
  }
}

onMounted(loadHealth)

function statusClass(status: string): string {
  if (status === 'ok') {
    return 'bg-emerald-50 text-emerald-700 ring-emerald-200'
  }

  if (status === 'warning') {
    return 'bg-amber-50 text-amber-700 ring-amber-200'
  }

  return 'bg-red-50 text-red-700 ring-red-200'
}
</script>

<template>
  <section class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
      <div class="flex items-start justify-between gap-4">
        <div>
          <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">System</p>
          <h2 class="mt-2 text-2xl font-bold text-slate-950">Health Center</h2>
          <p class="mt-2 text-sm text-slate-600">
            Проверка приложения, БД, Redis/cache, storage, миграций и диска.
          </p>
        </div>
        <button
          type="button"
          class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
          @click="loadHealth"
        >
          Обновить
        </button>
      </div>

      <p v-if="isLoading" class="mt-6 text-slate-600">Загрузка...</p>
      <p v-else-if="error" class="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>

      <template v-else-if="health">
        <div class="mt-6 grid gap-4 md:grid-cols-4">
          <div class="rounded-xl bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Status</p>
            <p class="mt-1 text-lg font-bold" :class="health.status === 'ok' ? 'text-emerald-700' : 'text-red-700'">
              {{ health.status }}
            </p>
          </div>
          <div class="rounded-xl bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">APP_ENV</p>
            <p class="mt-1 text-lg font-bold text-slate-950">{{ health.environment.appEnv }}</p>
          </div>
          <div class="rounded-xl bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">APP_DEBUG</p>
            <p class="mt-1 text-lg font-bold text-slate-950">{{ health.environment.appDebug ? 'true' : 'false' }}</p>
          </div>
          <div class="rounded-xl bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">PHP</p>
            <p class="mt-1 text-lg font-bold text-slate-950">{{ health.environment.phpVersion }}</p>
          </div>
        </div>
      </template>
    </div>

    <div v-if="health?.warnings.length" class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
      <h3 class="text-base font-semibold text-amber-900">System Warnings</h3>
      <ul class="mt-3 space-y-2 text-sm text-amber-800">
        <li v-for="warning in health.warnings" :key="warning.code">{{ warning.message }}</li>
      </ul>
    </div>

    <div v-if="health" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-600">
          <tr>
            <th class="px-4 py-3 font-semibold">Check</th>
            <th class="px-4 py-3 font-semibold">Status</th>
            <th class="px-4 py-3 font-semibold">Message</th>
            <th class="px-4 py-3 font-semibold">Details</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="check in health.checks" :key="check.name">
            <td class="px-4 py-3 font-medium text-slate-950">{{ check.label }}</td>
            <td class="px-4 py-3">
              <span class="rounded-full px-2 py-1 text-xs font-semibold ring-1" :class="statusClass(check.status)">
                {{ check.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-700">{{ check.message }}</td>
            <td class="px-4 py-3 text-slate-500">{{ JSON.stringify(check.details) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
