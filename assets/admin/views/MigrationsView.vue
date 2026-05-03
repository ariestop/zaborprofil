<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { apiRequest } from '../api/client'
import type { MigrationItem } from '../types/api'

const pageSize = 15
const migrations = ref<MigrationItem[]>([])
const isLoading = ref(true)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const pendingVersion = ref<string | null>(null)
const currentPage = ref(1)

const sortedMigrations = computed(() => [...migrations.value].sort((left, right) => right.version.localeCompare(left.version)))
const totalPages = computed(() => Math.max(1, Math.ceil(sortedMigrations.value.length / pageSize)))
const pageStart = computed(() => (currentPage.value - 1) * pageSize)
const pageEnd = computed(() => Math.min(pageStart.value + pageSize, sortedMigrations.value.length))
const paginatedMigrations = computed(() => sortedMigrations.value.slice(pageStart.value, pageEnd.value))
const pageNumbers = computed(() => Array.from({ length: totalPages.value }, (_, index) => index + 1))

async function loadMigrations(): Promise<void> {
  error.value = null
  migrations.value = await apiRequest<MigrationItem[]>('/admin/api/settings/migrations')
  if (currentPage.value > totalPages.value) {
    currentPage.value = totalPages.value
  }
}

async function refreshMigrations(): Promise<void> {
  isLoading.value = true
  try {
    await loadMigrations()
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось загрузить миграции.'
  } finally {
    isLoading.value = false
  }
}

async function runMigrationAction(migration: MigrationItem, action: 'apply' | 'rollback'): Promise<void> {
  if (pendingVersion.value !== null) {
    return
  }

  const label = action === 'apply' ? 'применить' : 'откатить'
  if (!window.confirm(`Точно ${label} миграцию ${migration.file}?`)) {
    return
  }

  pendingVersion.value = migration.version
  error.value = null
  notice.value = null

  try {
    await apiRequest(`/admin/api/settings/migrations/${encodeURIComponent(migration.version)}/${action}`, {
      method: 'POST',
    })
    notice.value = action === 'apply' ? 'Миграция применена.' : 'Миграция откатана.'
    await loadMigrations()
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Действие с миграцией не выполнено.'
  } finally {
    pendingVersion.value = null
  }
}

function formatDate(value: string | null): string {
  if (value === null) {
    return 'Не применялась'
  }

  return new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value))
}

function goToPage(page: number): void {
  currentPage.value = Math.min(Math.max(page, 1), totalPages.value)
}

onMounted(refreshMigrations)
</script>

<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Settings</p>
        <h2 class="mt-2 text-2xl font-bold text-slate-950">Миграции</h2>
        <p class="mt-2 text-sm text-slate-600">
          Список файлов из папки миграций, их описания и текущий статус применения. Применение выполняется по порядку до выбранной миграции.
        </p>
      </div>
      <button
        type="button"
        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        :disabled="isLoading || pendingVersion !== null"
        @click="refreshMigrations"
      >
        Обновить
      </button>
    </div>

    <p v-if="isLoading" class="mt-6 text-slate-600">Загрузка...</p>
    <p v-else-if="error" class="mt-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>
    <p v-else-if="notice" class="mt-6 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{{ notice }}</p>

    <div v-if="!isLoading && !error && migrations.length === 0" class="mt-6 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
      Миграции не найдены.
    </div>

    <div v-else-if="!isLoading && !error" class="mt-6 overflow-hidden rounded-xl border border-slate-200">
      <div class="flex items-center justify-between gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        <span>
          Показаны {{ pageStart + 1 }}-{{ pageEnd }} из {{ sortedMigrations.length }}
        </span>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="currentPage === 1"
            @click="goToPage(currentPage - 1)"
          >
            Назад
          </button>
          <button
            v-for="page in pageNumbers"
            :key="page"
            type="button"
            :class="[
              'rounded-lg px-3 py-2 font-semibold',
              currentPage === page ? 'bg-emerald-700 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
            ]"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="currentPage === totalPages"
            @click="goToPage(currentPage + 1)"
          >
            Вперёд
          </button>
        </div>
      </div>
      <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-600">
          <tr>
            <th class="px-4 py-3 font-semibold">Файл</th>
            <th class="px-4 py-3 font-semibold">Описание</th>
            <th class="px-4 py-3 font-semibold">Статус</th>
            <th class="px-4 py-3 font-semibold">Применена</th>
            <th class="px-4 py-3 text-right font-semibold">Действие</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="migration in paginatedMigrations" :key="migration.version">
            <td class="px-4 py-3 font-medium text-slate-950">{{ migration.file }}</td>
            <td class="max-w-xl px-4 py-3 text-slate-700">{{ migration.description }}</td>
            <td class="px-4 py-3">
              <span
                :class="[
                  'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                  migration.isApplied ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700',
                ]"
              >
                {{ migration.isApplied ? 'Применена' : 'Не применена' }}
              </span>
            </td>
            <td class="px-4 py-3 text-slate-500">{{ formatDate(migration.executedAt) }}</td>
            <td class="px-4 py-3 text-right">
              <button
                v-if="migration.isApplied"
                type="button"
                class="rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!migration.canRollback || pendingVersion !== null"
                :title="migration.canRollback ? 'Откатить миграцию' : 'Откатить можно только последнюю применённую миграцию'"
                @click="runMigrationAction(migration, 'rollback')"
              >
                {{ pendingVersion === migration.version ? 'Откат...' : 'Откатить' }}
              </button>
              <button
                v-else
                type="button"
                class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!migration.canApply || pendingVersion !== null"
                @click="runMigrationAction(migration, 'apply')"
              >
                {{ pendingVersion === migration.version ? 'Применение...' : 'Применить' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
