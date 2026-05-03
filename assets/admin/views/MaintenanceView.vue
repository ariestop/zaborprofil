<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiRequest } from '../api/client'
import type { MaintenanceStatus } from '../types/api'

const status = ref<MaintenanceStatus | null>(null)
const message = ref('Сайт временно находится на техническом обслуживании.')
const allowedIps = ref('')
const error = ref<string | null>(null)

async function loadStatus(): Promise<void> {
  try {
    status.value = await apiRequest<MaintenanceStatus>('/admin/api/system/maintenance')
    if (status.value.message) {
      message.value = status.value.message
    }
    allowedIps.value = (status.value.allowedIps ?? []).join('\n')
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить maintenance status.'
  }
}

async function enableMaintenance(): Promise<void> {
  status.value = await apiRequest<MaintenanceStatus>('/admin/api/system/maintenance/on', {
    method: 'POST',
    body: {
      message: message.value,
      allowedIps: allowedIps.value.split('\n').map((item) => item.trim()).filter(Boolean),
    },
  })
}

async function disableMaintenance(): Promise<void> {
  status.value = await apiRequest<MaintenanceStatus>('/admin/api/system/maintenance/off', {
    method: 'POST',
    body: {},
  })
}

onMounted(loadStatus)
</script>

<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">System</p>
    <h2 class="mt-2 text-2xl font-bold text-slate-950">Maintenance Mode</h2>

    <p v-if="error" class="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>

    <div v-if="status" class="mt-6 rounded-xl bg-slate-50 p-4">
      <p class="text-sm text-slate-500">Текущий статус</p>
      <p class="mt-1 text-lg font-bold" :class="status.enabled ? 'text-red-700' : 'text-emerald-700'">
        {{ status.enabled ? 'Включен' : 'Выключен' }}
      </p>
      <p v-if="status.enabledAt" class="mt-1 text-sm text-slate-500">С {{ status.enabledAt }}</p>
    </div>

    <label class="mt-6 block text-sm font-medium text-slate-700">
      Сообщение
      <textarea v-model="message" class="mt-2 min-h-28 w-full rounded-lg border border-slate-300 p-3"></textarea>
    </label>

    <label class="mt-4 block text-sm font-medium text-slate-700">
      Whitelist IP, по одному на строку
      <textarea v-model="allowedIps" class="mt-2 min-h-24 w-full rounded-lg border border-slate-300 p-3"></textarea>
    </label>

    <div class="mt-6 flex gap-3">
      <button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800" type="button" @click="enableMaintenance">
        Включить maintenance
      </button>
      <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" type="button" @click="disableMaintenance">
        Выключить
      </button>
    </div>
  </section>
</template>
