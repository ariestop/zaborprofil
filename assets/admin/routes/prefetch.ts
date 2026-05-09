import {
  loadBuilderRuntime,
  loadCrmPage,
  loadDashboardCharts,
  loadMediaPage,
  loadPageBuilderPage,
  loadPageDetailPage,
  loadPagesPage,
  loadRichTextRuntime,
  loadSeoPage,
  loadSettingsPage,
  loadSettingsMigrationsPage,
  loadSystemAuditPage,
  loadSystemBackupsPage,
  loadSystemCachePage,
  loadSystemDashboardPage,
  loadSystemDatabasePage,
  loadSystemDeployPage,
  loadSystemLogsPage,
  loadSystemProcessesPage,
  loadSystemQueuesPage,
  loadSystemSecurityPage,
  loadUsersPage,
} from './loaders'
import { warmupGrapesJsRuntimeOnIntent } from '../modules/page-builder/runtime/grapesjs-runtime-bridge'

interface PrefetchTask {
  key: string
  run: () => Promise<unknown>
}

type PrefetchTelemetryEvent =
  | { type: 'decision', path: string, aggressive: boolean, selectedTasks: number, totalTasks: number }
  | { type: 'skip', path: string, reason: 'no_match' | 'already_queued_or_inflight' }
  | { type: 'queued', taskKey: string, queueSize: number }
  | { type: 'started', taskKey: string, inFlight: number }
  | { type: 'success', taskKey: string }
  | { type: 'failure', taskKey: string }

type PrefetchTelemetryListener = (event: PrefetchTelemetryEvent) => void

interface NetworkInformationLike {
  effectiveType?: string
  saveData?: boolean
}

interface NavigatorLike {
  connection?: NetworkInformationLike
  deviceMemory?: number
  hardwareConcurrency?: number
}

const MAX_CONCURRENT_PREFETCH = 2
const prefetchQueue: PrefetchTask[] = []
const inFlightTaskKeys = new Set<string>()
const queuedTaskKeys = new Set<string>()
const telemetryListeners = new Set<PrefetchTelemetryListener>()

function emitTelemetry(event: PrefetchTelemetryEvent): void {
  for (const listener of telemetryListeners) {
    listener(event)
  }
}

export function subscribePrefetchTelemetry(listener: PrefetchTelemetryListener): () => void {
  telemetryListeners.add(listener)
  return () => {
    telemetryListeners.delete(listener)
  }
}

function requestIdle(cb: () => void): void {
  const idle = (globalThis as { requestIdleCallback?: (callback: () => void, options?: { timeout: number }) => void }).requestIdleCallback

  if (typeof idle === 'function') {
    idle(() => cb(), { timeout: 1500 })
    return
  }

  globalThis.setTimeout(cb, 200)
}

function shouldUseAggressivePrefetch(): boolean {
  const navigatorLike = (globalThis as { navigator?: NavigatorLike }).navigator
  if (navigatorLike === undefined) {
    return true
  }

  const connection = navigatorLike.connection
  const effectiveType = connection?.effectiveType?.toLowerCase() ?? ''
  if (connection?.saveData === true) {
    return false
  }

  if (effectiveType.includes('2g') || effectiveType === 'slow-2g') {
    return false
  }

  if ((navigatorLike.deviceMemory ?? 4) <= 2) {
    return false
  }

  if ((navigatorLike.hardwareConcurrency ?? 4) <= 2) {
    return false
  }

  return true
}

function runPrefetchTask(task: PrefetchTask): void {
  inFlightTaskKeys.add(task.key)
  emitTelemetry({ type: 'started', taskKey: task.key, inFlight: inFlightTaskKeys.size })
  void task
    .run()
    .then(() => {
      emitTelemetry({ type: 'success', taskKey: task.key })
    })
    .catch(() => {
      // Silent best-effort prefetch.
      emitTelemetry({ type: 'failure', taskKey: task.key })
    })
    .finally(() => {
      inFlightTaskKeys.delete(task.key)
      runQueuedPrefetchTasks()
    })
}

function runQueuedPrefetchTasks(): void {
  while (inFlightTaskKeys.size < MAX_CONCURRENT_PREFETCH && prefetchQueue.length > 0) {
    const nextTask = prefetchQueue.shift()
    if (nextTask === undefined) {
      continue
    }

    queuedTaskKeys.delete(nextTask.key)
    if (inFlightTaskKeys.has(nextTask.key)) {
      continue
    }

    runPrefetchTask(nextTask)
  }
}

function enqueuePrefetchTask(task: PrefetchTask): void {
  if (inFlightTaskKeys.has(task.key) || queuedTaskKeys.has(task.key)) {
    emitTelemetry({ type: 'skip', path: task.key, reason: 'already_queued_or_inflight' })
    return
  }

  prefetchQueue.push(task)
  queuedTaskKeys.add(task.key)
  emitTelemetry({ type: 'queued', taskKey: task.key, queueSize: prefetchQueue.length })
  runQueuedPrefetchTasks()
}

const routePrefetchers: Array<{ match: RegExp, tasks: PrefetchTask[] }> = [
  {
    match: /^\/admin\/dashboard$/,
    tasks: [
      { key: 'route-pages', run: loadPagesPage },
      { key: 'route-crm', run: loadCrmPage },
      { key: 'route-users', run: loadUsersPage },
      { key: 'widget-dashboard-charts', run: loadDashboardCharts },
    ],
  },
  {
    match: /^\/admin\/pages$/,
    tasks: [
      { key: 'route-page-detail', run: loadPageDetailPage },
      { key: 'route-page-builder', run: loadPageBuilderPage },
      { key: 'runtime-rich-text', run: loadRichTextRuntime },
    ],
  },
  {
    match: /^\/admin\/pages\/[^/]+$/,
    tasks: [
      { key: 'route-page-builder', run: loadPageBuilderPage },
      { key: 'runtime-rich-text', run: loadRichTextRuntime },
    ],
  },
  {
    match: /^\/admin\/pages\/[^/]+\/builder$/,
    tasks: [
      { key: 'runtime-builder', run: loadBuilderRuntime },
      { key: 'runtime-rich-text', run: loadRichTextRuntime },
    ],
  },
  {
    match: /^\/admin\/crm$/,
    tasks: [
      { key: 'widget-dashboard-charts', run: loadDashboardCharts },
      { key: 'route-users', run: loadUsersPage },
      { key: 'route-pages', run: loadPagesPage },
    ],
  },
  {
    match: /^\/admin\/(media|seo|settings|settings\/migrations|users)$/,
    tasks: [
      { key: 'route-pages', run: loadPagesPage },
      { key: 'route-crm', run: loadCrmPage },
      { key: 'route-media', run: loadMediaPage },
      { key: 'route-seo', run: loadSeoPage },
      { key: 'route-settings', run: loadSettingsPage },
      { key: 'route-settings-migrations', run: loadSettingsMigrationsPage },
      { key: 'route-users', run: loadUsersPage },
    ],
  },
  {
    match: /^\/admin\/system(\/(processes|logs|queues|cache|database|security|backups|deploy|audit))?$/,
    tasks: [
      { key: 'route-system-dashboard', run: loadSystemDashboardPage },
      { key: 'route-system-processes', run: loadSystemProcessesPage },
      { key: 'route-system-logs', run: loadSystemLogsPage },
      { key: 'route-system-queues', run: loadSystemQueuesPage },
      { key: 'route-system-cache', run: loadSystemCachePage },
      { key: 'route-system-database', run: loadSystemDatabasePage },
      { key: 'route-system-security', run: loadSystemSecurityPage },
      { key: 'route-system-backups', run: loadSystemBackupsPage },
      { key: 'route-system-deploy', run: loadSystemDeployPage },
      { key: 'route-system-audit', run: loadSystemAuditPage },
    ],
  },
]

export function prefetchRouteByPath(path: string): void {
  const entry = routePrefetchers.find((item) => item.match.test(path))
  if (entry === undefined) {
    emitTelemetry({ type: 'skip', path, reason: 'no_match' })
    return
  }

  const aggressivePrefetch = shouldUseAggressivePrefetch()
  const tasks = aggressivePrefetch ? entry.tasks : entry.tasks.slice(0, 1)
  emitTelemetry({
    type: 'decision',
    path,
    aggressive: aggressivePrefetch,
    selectedTasks: tasks.length,
    totalTasks: entry.tasks.length,
  })
  for (const task of tasks) {
    enqueuePrefetchTask(task)
  }
}

export function schedulePrefetchForCurrentRoute(path: string): void {
  requestIdle(() => {
    prefetchRouteByPath(path)
  })
}

export function preloadBuilderOnIntent(): void {
  enqueuePrefetchTask({ key: 'route-page-builder', run: loadPageBuilderPage })
  warmupGrapesJsRuntimeOnIntent()
}
