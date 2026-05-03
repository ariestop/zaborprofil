<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ApiError, apiRequest } from '../api/client'
import type { AssetBuildStatus } from '../types/api'

const widgetStateStorageKey = 'admin.assetBuildWidget.state'

interface WidgetState {
  isExpanded: boolean
  isCollapsed: boolean
}

function initialWidgetState(): WidgetState {
  const fallback = {
    isExpanded: false,
    isCollapsed: false,
  }

  try {
    const rawState = window.localStorage.getItem(widgetStateStorageKey)
    if (rawState === null) {
      return fallback
    }

    const parsed = JSON.parse(rawState) as { isExpanded?: unknown, isCollapsed?: unknown }

    return {
      isExpanded: parsed.isExpanded === true,
      isCollapsed: parsed.isCollapsed === true,
    }
  } catch {
    window.localStorage.removeItem(widgetStateStorageKey)

    return fallback
  }
}

const initialState = initialWidgetState()

const status = ref<AssetBuildStatus>({
  status: 'idle',
  command: 'npm run build',
  startedAt: null,
  finishedAt: null,
  exitCode: null,
  progress: 0,
  logs: '',
})
const isExpanded = ref(initialState.isExpanded)
const isCollapsed = ref(initialState.isCollapsed)
const isAvailable = ref(true)
const isLoading = ref(false)
const error = ref<string | null>(null)
const logContainer = ref<HTMLElement | null>(null)
const shouldReloadAfterBuild = ref(false)
const isLogCopied = ref(false)
let pollTimer: number | null = null
let reloadTimer: number | null = null

function persistWidgetState(): void {
  window.localStorage.setItem(widgetStateStorageKey, JSON.stringify({
    isExpanded: isExpanded.value,
    isCollapsed: isCollapsed.value,
  }))
}

const isRunning = computed(() => status.value.status === 'running')
const hasLogs = computed(() => status.value.logs.trim().length > 0)
const progress = computed(() => Math.min(Math.max(status.value.progress, 0), 100))
const statusLabel = computed(() => {
  if (status.value.status === 'running') {
    return 'Сборка выполняется'
  }

  if (status.value.status === 'success') {
    return 'Сборка завершена'
  }

  if (status.value.status === 'failed') {
    return 'Сборка упала'
  }

  return 'Готов к запуску'
})
const statusToneClass = computed(() => {
  if (status.value.status === 'success') {
    return 'bg-emerald-50 text-emerald-700'
  }

  if (status.value.status === 'failed') {
    return 'bg-red-50 text-red-700'
  }

  if (status.value.status === 'running') {
    return 'bg-sky-50 text-sky-700'
  }

  return 'bg-slate-100 text-slate-600'
})
const progressClass = computed(() => {
  if (status.value.status === 'failed') {
    return 'bg-red-500'
  }

  if (status.value.status === 'success') {
    return 'bg-emerald-600'
  }

  return 'bg-sky-500'
})

async function loadStatus(): Promise<void> {
  try {
    status.value = await apiRequest<AssetBuildStatus>('/admin/api/system/assets/build')
    error.value = null
    isAvailable.value = true
    syncPolling()
    scheduleReloadAfterSuccess()
  } catch (exception) {
    if (exception instanceof ApiError && exception.status === 403) {
      isAvailable.value = false
      stopPolling()
      return
    }

    error.value = exception instanceof Error ? exception.message : 'Не удалось получить статус сборки.'
    stopPolling()
  }
}

async function startBuild(): Promise<void> {
  if (isRunning.value || isLoading.value) {
    return
  }

  isLoading.value = true
  error.value = null
  isExpanded.value = true
  isCollapsed.value = false
  shouldReloadAfterBuild.value = true

  try {
    status.value = await apiRequest<AssetBuildStatus>('/admin/api/system/assets/build/run', {
      method: 'POST',
      body: {},
    })
    syncPolling()
    scheduleReloadAfterSuccess()
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : 'Не удалось запустить сборку.'
  } finally {
    isLoading.value = false
  }
}

function syncPolling(): void {
  if (status.value.status === 'running' && pollTimer === null) {
    pollTimer = window.setInterval(loadStatus, 1500)
  }

  if (status.value.status !== 'running') {
    stopPolling()
  }
}

function stopPolling(): void {
  if (pollTimer === null) {
    return
  }

  window.clearInterval(pollTimer)
  pollTimer = null
}

function scheduleReloadAfterSuccess(): void {
  if (status.value.status !== 'success' || !shouldReloadAfterBuild.value || reloadTimer !== null) {
    return
  }

  shouldReloadAfterBuild.value = false
  persistWidgetState()
  reloadTimer = window.setTimeout(() => {
    window.location.reload()
  }, 1200)
}

function toggleCollapsed(): void {
  if (!isCollapsed.value) {
    isExpanded.value = false
  }

  isCollapsed.value = !isCollapsed.value
}

function collapsePanel(): void {
  isExpanded.value = false
  isCollapsed.value = true
}

async function copyLog(): Promise<void> {
  const logText = status.value.logs.trim()
  if (logText === '') {
    return
  }

  await navigator.clipboard.writeText(status.value.logs)
  isLogCopied.value = true
  window.setTimeout(() => {
    isLogCopied.value = false
  }, 1600)
}

function formatDate(value: string | null): string {
  if (value === null) {
    return 'ещё не запускалась'
  }

  return new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value))
}

watch(
  () => status.value.logs,
  async () => {
    if (!isExpanded.value) {
      return
    }

    await nextTick()
    if (logContainer.value !== null) {
      logContainer.value.scrollTop = logContainer.value.scrollHeight
    }
  },
)

watch(
  () => status.value.status,
  scheduleReloadAfterSuccess,
)

watch([isExpanded, isCollapsed], persistWidgetState)

onMounted(() => {
  void loadStatus()
})

onBeforeUnmount(() => {
  stopPolling()
  if (reloadTimer !== null) {
    window.clearTimeout(reloadTimer)
  }
})
</script>

<template>
  <aside
    v-if="isAvailable"
    class="fixed bottom-5 right-0 z-50 flex w-[min(468px,calc(100vw-1rem))] items-stretch transition-transform duration-500 ease-out"
    :class="isCollapsed ? 'translate-x-[calc(100%-3rem)]' : 'translate-x-0'"
  >
    <button
      type="button"
      class="flex w-12 shrink-0 items-center justify-center rounded-l-2xl border border-r-0 border-emerald-200 bg-emerald-700 text-xs font-bold uppercase tracking-[0.25em] text-white shadow-2xl shadow-slate-950/10 [writing-mode:vertical-rl]"
      :aria-label="isCollapsed ? 'Открыть настройки сборки' : 'Свернуть настройки сборки'"
      @click="toggleCollapsed"
    >
      настройка
    </button>

    <div class="w-[calc(100%-3rem)] overflow-hidden rounded-l-none rounded-r-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/10">
      <div class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
        <button type="button" class="min-w-0 flex-1 text-left" @click="isExpanded = !isExpanded">
          <span class="block text-xs font-semibold uppercase tracking-wide text-emerald-700">Assets</span>
          <span class="mt-1 block truncate text-sm font-bold text-slate-950">{{ status.command }}</span>
        </button>
        <span :class="['shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold', statusToneClass]">
          {{ statusLabel }}
        </span>
        <button
          type="button"
          class="shrink-0 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-50 hover:text-slate-700"
          @click="collapsePanel"
        >
          Свернуть
        </button>
      </div>

      <div class="h-1 bg-slate-100">
        <div class="h-full transition-all duration-500" :class="progressClass" :style="{ width: `${progress}%` }"></div>
      </div>

      <div v-if="isExpanded" class="space-y-4 border-t border-slate-100 p-4">
        <div class="flex items-center justify-between gap-3">
          <div class="text-xs text-slate-500">
            <p>Старт: {{ formatDate(status.startedAt) }}</p>
            <p v-if="status.finishedAt">Финиш: {{ formatDate(status.finishedAt) }}</p>
          </div>
          <button
            type="button"
            class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="isRunning || isLoading"
            @click="startBuild"
          >
            {{ isRunning ? 'Сборка...' : 'Перекомпилировать' }}
          </button>
        </div>

        <div>
          <div class="mb-2 flex items-center justify-between text-xs font-medium text-slate-500">
            <span>Прогресс</span>
            <span>{{ progress }}%</span>
          </div>
          <div class="h-2 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full transition-all duration-500" :class="progressClass" :style="{ width: `${progress}%` }"></div>
          </div>
        </div>

        <p v-if="error" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-950">
          <div class="flex items-center justify-between border-b border-white/10 px-3 py-2 text-xs text-slate-300">
            <span>Лог сборки</span>
            <div class="flex items-center gap-3">
              <span v-if="isLogCopied" class="text-emerald-300">Скопировано</span>
              <button
                type="button"
                class="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-300 hover:bg-white/10 hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="!hasLogs"
                title="Скопировать лог"
                aria-label="Скопировать лог сборки"
                @click="copyLog"
              >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M8 7.5A2.5 2.5 0 0 1 10.5 5h6A2.5 2.5 0 0 1 19 7.5v9A2.5 2.5 0 0 1 16.5 19h-6A2.5 2.5 0 0 1 8 16.5v-9Z" stroke="currentColor" stroke-width="1.7" />
                  <path d="M6 15.5A2.5 2.5 0 0 1 4 13.05V5.5A2.5 2.5 0 0 1 6.5 3h5.55A2.5 2.5 0 0 1 14.5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                </svg>
              </button>
              <span v-if="status.exitCode !== null">exit code: {{ status.exitCode }}</span>
            </div>
          </div>
          <pre
            ref="logContainer"
            class="max-h-56 overflow-auto whitespace-pre-wrap break-words p-3 text-xs leading-5 text-slate-100"
          >{{ hasLogs ? status.logs : 'Лог появится после запуска сборки.' }}</pre>
        </div>
      </div>

      <div v-else class="flex items-center justify-between gap-3 px-4 py-3">
        <span class="text-xs text-slate-500">Прогресс: {{ progress }}%</span>
        <button
          type="button"
          class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="isRunning || isLoading"
          @click="startBuild"
        >
          {{ isRunning ? 'Сборка...' : 'Перекомпилировать' }}
        </button>
      </div>
    </div>
  </aside>
</template>
