<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiRequest } from '../api/client'
import type { AuditLogEntryItem } from '../types/api'

const entries = ref<AuditLogEntryItem[]>([])
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    entries.value = await apiRequest<AuditLogEntryItem[]>('/admin/api/system/audit')
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить audit log.'
  }
})
</script>

<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">System</p>
    <h2 class="mt-2 text-2xl font-bold text-slate-950">Audit Log</h2>

    <p v-if="error" class="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>

    <div v-else class="mt-6 overflow-hidden rounded-xl border border-slate-200">
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-600">
          <tr>
            <th class="px-4 py-3 font-semibold">Time</th>
            <th class="px-4 py-3 font-semibold">Actor</th>
            <th class="px-4 py-3 font-semibold">Action</th>
            <th class="px-4 py-3 font-semibold">Entity</th>
            <th class="px-4 py-3 font-semibold">Diff</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="entry in entries" :key="entry.id">
            <td class="px-4 py-3 text-slate-500">{{ entry.occurredAt }}</td>
            <td class="px-4 py-3 text-slate-700">{{ entry.actorEmail ?? 'system' }}</td>
            <td class="px-4 py-3 font-medium text-slate-950">{{ entry.action }}</td>
            <td class="px-4 py-3 text-slate-700">{{ entry.entityType }}<br>{{ entry.entityId }}</td>
            <td class="px-4 py-3 text-xs text-slate-500">
              <pre>{{ JSON.stringify({ old: entry.oldValues, new: entry.newValues }, null, 2) }}</pre>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
