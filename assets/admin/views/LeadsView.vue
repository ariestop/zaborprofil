<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiRequest } from '../api/client'
import type { LeadItem } from '../types/api'

const leads = ref<LeadItem[]>([])

async function loadLeads(): Promise<void> {
  const response = await apiRequest<{ leads: LeadItem[] }>('/admin/api/leads')
  leads.value = response.leads
}

async function setStatus(lead: LeadItem, status: LeadItem['status']): Promise<void> {
  await apiRequest<LeadItem>(`/admin/api/leads/${lead.id}/status`, {
    method: 'PATCH',
    body: { status },
  })
  await loadLeads()
}

function changeStatus(lead: LeadItem, event: Event): void {
  const target = event.target as HTMLSelectElement
  void setStatus(lead, target.value as LeadItem['status'])
}

onMounted(loadLeads)
</script>

<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-slate-950">Заявки</h2>
    <p class="mt-1 text-sm text-slate-600">Публичные формы отправляют заявки в `/api/leads` с consent snapshot.</p>

    <div class="mt-6 space-y-3">
      <article v-for="lead in leads" :key="lead.id" class="rounded-xl border border-slate-200 p-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="font-medium text-slate-900">{{ lead.name }} · {{ lead.phone }}</p>
            <p class="text-sm text-slate-500">{{ lead.email ?? 'email не указан' }} · {{ lead.source }}</p>
            <p v-if="lead.message" class="mt-2 text-sm text-slate-700">{{ lead.message }}</p>
          </div>
          <select :value="lead.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" @change="changeStatus(lead, $event)">
            <option value="new">new</option>
            <option value="in_progress">in_progress</option>
            <option value="done">done</option>
            <option value="spam">spam</option>
          </select>
        </div>
      </article>
    </div>
  </section>
</template>
