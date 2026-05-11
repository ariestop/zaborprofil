import { useEffect, useMemo, useRef, useState } from 'react'
import { ApiError, apiRequest } from '../shared/api/client'
import type { AssetBuildStatus } from '../types/api'

const widgetStateStorageKey = 'admin.assetBuildWidget.state'
const defaultBuildTargets = [
  {
    id: 'all',
    label: 'Все бандлы',
    description: 'Полная сборка site + admin с очисткой build директории.',
  },
  {
    id: 'site',
    label: 'Site',
    description: 'Собрать только публичный bundle.',
  },
  {
    id: 'admin',
    label: 'Admin',
    description: 'Собрать только админский bundle.',
  },
]

interface WidgetState {
  isExpanded: boolean
  isCollapsed: boolean
}

/** Короткий заголовок под «Assets»: не обрезать техническую команду с префиксом VITE_BUILD_TARGET. */
function formatAssetBuildHeadline(selectedTargets: string[]): string {
  const targets = selectedTargets.length > 0 ? selectedTargets : ['all']
  if (targets.includes('all')) {
    return 'Полная сборка фронтенда'
  }
  const parts: string[] = []
  if (targets.includes('site')) {
    parts.push('публичный сайт')
  }
  if (targets.includes('admin')) {
    parts.push('админ-панель')
  }
  if (parts.length > 0) {
    return parts.length === 2 ? `Сборка: ${parts.join(' и ')}` : `Сборка: ${parts[0]}`
  }

  return 'Сборка фронтенда'
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

function fallbackCopyText(text: string): boolean {
  const element = document.createElement('textarea')
  element.value = text
  element.setAttribute('readonly', 'true')
  element.style.position = 'fixed'
  element.style.left = '-9999px'
  element.style.top = '0'
  document.body.appendChild(element)
  element.focus()
  element.select()

  try {
    return document.execCommand('copy')
  } finally {
    document.body.removeChild(element)
  }
}

export default function AssetBuildWidget() {
  const initialState = initialWidgetState()
  const [status, setStatus] = useState<AssetBuildStatus>({
    status: 'idle',
    command: 'npm run build',
    selectedTargets: ['all'],
    availableTargets: defaultBuildTargets,
    startedAt: null,
    finishedAt: null,
    exitCode: null,
    progress: 0,
    logs: '',
  })
  const [selectedTargets, setSelectedTargets] = useState<string[]>(['all'])
  const [isExpanded, setIsExpanded] = useState(initialState.isExpanded)
  const [isCollapsed, setIsCollapsed] = useState(initialState.isCollapsed)
  const [isAvailable, setIsAvailable] = useState(true)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [shouldReloadAfterBuild, setShouldReloadAfterBuild] = useState(false)
  const [isLogCopied, setIsLogCopied] = useState(false)
  const pollTimer = useRef<number | null>(null)
  const reloadTimer = useRef<number | null>(null)
  const logContainer = useRef<HTMLPreElement | null>(null)

  const persistWidgetState = (): void => {
    window.localStorage.setItem(widgetStateStorageKey, JSON.stringify({
      isExpanded,
      isCollapsed,
    }))
  }

  const isRunning = status.status === 'running'
  const hasLogs = status.logs.trim().length > 0
  const progress = Math.min(Math.max(status.progress, 0), 100)
  const statusLabel = useMemo(() => {
    if (status.status === 'running') return 'Сборка выполняется'
    if (status.status === 'success') return 'Сборка завершена'
    if (status.status === 'failed') return 'Сборка упала'
    return 'Готов к запуску'
  }, [status.status])
  const statusToneClass = useMemo(() => {
    if (status.status === 'success') return 'bg-emerald-50 text-emerald-700'
    if (status.status === 'failed') return 'bg-red-50 text-red-700'
    if (status.status === 'running') return 'bg-sky-50 text-sky-700'
    return 'bg-slate-100 text-slate-600'
  }, [status.status])
  const progressClass = useMemo(() => {
    if (status.status === 'failed') return 'bg-red-500'
    if (status.status === 'success') return 'bg-emerald-600'
    return 'bg-sky-500'
  }, [status.status])
  const activeTargets = useMemo(() => (
    status.availableTargets.length > 0 ? status.availableTargets : defaultBuildTargets
  ), [status.availableTargets])
  const assetsHeadline = useMemo(
    () => formatAssetBuildHeadline(status.selectedTargets),
    [status.selectedTargets],
  )

  const stopPolling = (): void => {
    if (pollTimer.current === null) {
      return
    }
    window.clearInterval(pollTimer.current)
    pollTimer.current = null
  }

  const scheduleReloadAfterSuccess = (nextStatus: AssetBuildStatus = status): void => {
    if (nextStatus.status !== 'success' || !shouldReloadAfterBuild || reloadTimer.current !== null) {
      return
    }

    setShouldReloadAfterBuild(false)
    persistWidgetState()
    reloadTimer.current = window.setTimeout(() => {
      window.location.reload()
    }, 1200)
  }

  const syncPolling = (nextStatus: AssetBuildStatus): void => {
    if (nextStatus.status === 'running' && pollTimer.current === null) {
      pollTimer.current = window.setInterval(() => {
        void loadStatus()
      }, 1500)
    }

    if (nextStatus.status !== 'running') {
      stopPolling()
    }
  }

  const loadStatus = async (): Promise<void> => {
    try {
      const loaded = await apiRequest<AssetBuildStatus>('/admin/api/system/assets/build')
      setStatus(loaded)
      setSelectedTargets(loaded.selectedTargets.length > 0 ? loaded.selectedTargets : ['all'])
      setError(null)
      setIsAvailable(true)
      syncPolling(loaded)
      scheduleReloadAfterSuccess(loaded)
    } catch (exception) {
      if (exception instanceof ApiError && exception.status === 403) {
        setIsAvailable(false)
        stopPolling()
        return
      }

      setError(exception instanceof Error ? exception.message : 'Не удалось получить статус сборки.')
      stopPolling()
    }
  }

  const startBuild = async (): Promise<void> => {
    if (isRunning || isLoading) {
      return
    }

    setIsLoading(true)
    setError(null)
    setIsExpanded(true)
    setIsCollapsed(false)
    setShouldReloadAfterBuild(true)

    try {
      const nextStatus = await apiRequest<AssetBuildStatus>('/admin/api/system/assets/build/run', {
        method: 'POST',
        body: {
          targets: selectedTargets,
        },
      })
      setStatus(nextStatus)
      setSelectedTargets(nextStatus.selectedTargets.length > 0 ? nextStatus.selectedTargets : ['all'])
      syncPolling(nextStatus)
      scheduleReloadAfterSuccess(nextStatus)
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Не удалось запустить сборку.')
    } finally {
      setIsLoading(false)
    }
  }

  const toggleTarget = (targetId: string): void => {
    if (isRunning || isLoading) {
      return
    }

    setSelectedTargets((current) => {
      if (targetId === 'all') {
        return ['all']
      }

      const currentWithoutAll = current.filter((target) => target !== 'all')
      const isTargetSelected = currentWithoutAll.includes(targetId)

      if (isTargetSelected) {
        const nextTargets = currentWithoutAll.filter((target) => target !== targetId)
        return nextTargets.length > 0 ? nextTargets : ['all']
      }

      return [...currentWithoutAll, targetId]
    })
  }

  const toggleCollapsed = (): void => {
    if (!isCollapsed) {
      setIsExpanded(false)
    }
    setIsCollapsed(!isCollapsed)
  }

  const collapsePanel = (): void => {
    setIsExpanded(false)
    setIsCollapsed(true)
  }

  const copyLog = async (): Promise<void> => {
    const logText = status.logs.trim()
    if (logText === '') {
      return
    }

    try {
      if ('clipboard' in navigator && window.isSecureContext) {
        await navigator.clipboard.writeText(status.logs)
      } else if (!fallbackCopyText(status.logs)) {
        throw new Error('Fallback copy failed')
      }

      setIsLogCopied(true)
      setError(null)
      window.setTimeout(() => {
        setIsLogCopied(false)
      }, 1600)
    } catch {
      setError('Не удалось скопировать лог в буфер. Проверьте доступ к clipboard в браузере.')
    }
  }

  const formatDate = (value: string | null): string => {
    if (value === null) {
      return 'ещё не запускалась'
    }
    return new Intl.DateTimeFormat('ru-RU', {
      dateStyle: 'short',
      timeStyle: 'short',
    }).format(new Date(value))
  }

  useEffect(() => {
    void loadStatus()
    return () => {
      stopPolling()
      if (reloadTimer.current !== null) {
        window.clearTimeout(reloadTimer.current)
      }
    }
  }, [])

  useEffect(() => {
    persistWidgetState()
  }, [isExpanded, isCollapsed])

  useEffect(() => {
    scheduleReloadAfterSuccess()
  }, [status.status, shouldReloadAfterBuild])

  useEffect(() => {
    if (!isExpanded || logContainer.current === null) {
      return
    }
    logContainer.current.scrollTop = logContainer.current.scrollHeight
  }, [status.logs, isExpanded])

  if (!isAvailable) {
    return null
  }

  return (
    <aside
      className={[
        'fixed bottom-5 right-0 z-50 flex w-[min(468px,calc(100vw-1rem))] items-stretch transition-transform duration-500 ease-out',
        isCollapsed ? 'translate-x-[calc(100%-3rem)]' : 'translate-x-0',
      ].join(' ')}
    >
      <button
        type="button"
        className="flex w-12 shrink-0 items-center justify-center rounded-l-2xl border border-r-0 border-emerald-200 bg-emerald-700 text-xs font-bold uppercase tracking-[0.25em] text-white shadow-2xl shadow-slate-950/10 [writing-mode:vertical-rl]"
        aria-label={isCollapsed ? 'Открыть настройки сборки' : 'Свернуть настройки сборки'}
        onClick={toggleCollapsed}
      >
        настройка
      </button>

      <div className="w-[calc(100%-3rem)] overflow-hidden rounded-l-none rounded-r-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/10">
        <div className="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50">
          <button type="button" className="min-w-0 flex-1 text-left" onClick={() => setIsExpanded((value) => !value)}>
            <span className="block text-xs font-semibold uppercase tracking-wide text-emerald-700">Assets</span>
            <span
              className="mt-1 block truncate text-sm font-bold text-slate-950"
              title={status.command}
            >
              {assetsHeadline}
            </span>
          </button>
          <span className={['shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold', statusToneClass].join(' ')}>
            {statusLabel}
          </span>
          <button
            type="button"
            className="shrink-0 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-50 hover:text-slate-700"
            onClick={collapsePanel}
          >
            Свернуть
          </button>
        </div>

        {isExpanded ? (
          <div className="space-y-4 border-t border-slate-100 p-4">
            <div className="flex items-center justify-between gap-3">
              <div className="text-xs text-slate-500">
                <p>Старт: {formatDate(status.startedAt)}</p>
                {status.finishedAt && <p>Финиш: {formatDate(status.finishedAt)}</p>}
              </div>
              <button
                type="button"
                className="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                disabled={isRunning || isLoading}
                onClick={() => { void startBuild() }}
              >
                {isRunning ? 'Сборка...' : 'Перекомпилировать'}
              </button>
            </div>

            <div className="space-y-2">
              <p className="text-xs font-medium uppercase tracking-wide text-slate-500">Что пересобрать</p>
              <div className="space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                {activeTargets.map((target) => (
                  <label key={target.id} className="flex cursor-pointer items-start gap-2.5">
                    <input
                      type="checkbox"
                      className="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                      checked={selectedTargets.includes(target.id)}
                      disabled={isRunning || isLoading}
                      onChange={() => { toggleTarget(target.id) }}
                    />
                    <span className="min-w-0">
                      <span className="block text-sm font-medium text-slate-800">{target.label}</span>
                      <span className="block text-xs text-slate-500">{target.description}</span>
                    </span>
                  </label>
                ))}
              </div>
            </div>

            <div>
              <div className="mb-2 flex items-center justify-between text-xs font-medium text-slate-500">
                <span>Прогресс</span>
                <span>{progress}%</span>
              </div>
              <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                <div className={['h-full rounded-full transition-all duration-500', progressClass].join(' ')} style={{ width: `${progress}%` }} />
              </div>
            </div>

            {error && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-slate-950">
              <div className="flex items-center justify-between border-b border-white/10 px-3 py-2 text-xs text-slate-300">
                <span>Лог сборки</span>
                <div className="flex items-center gap-3">
                  {isLogCopied && <span className="text-emerald-300">Скопировано</span>}
                  <button
                    type="button"
                    className="inline-flex h-7 w-7 items-center justify-center rounded-md text-slate-300 hover:bg-white/10 hover:text-white disabled:cursor-not-allowed disabled:opacity-40"
                    disabled={!hasLogs}
                    title="Скопировать лог"
                    aria-label="Скопировать лог сборки"
                    onClick={() => { void copyLog() }}
                  >
                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <path d="M8 7.5A2.5 2.5 0 0 1 10.5 5h6A2.5 2.5 0 0 1 19 7.5v9A2.5 2.5 0 0 1 16.5 19h-6A2.5 2.5 0 0 1 8 16.5v-9Z" stroke="currentColor" strokeWidth="1.7" />
                      <path d="M6 15.5A2.5 2.5 0 0 1 4 13.05V5.5A2.5 2.5 0 0 1 6.5 3h5.55A2.5 2.5 0 0 1 14.5 5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" />
                    </svg>
                  </button>
                  {status.exitCode !== null && <span>exit code: {status.exitCode}</span>}
                </div>
              </div>
              <pre
                ref={logContainer}
                className="max-h-56 overflow-auto whitespace-pre-wrap break-words p-3 text-xs leading-5 text-slate-100"
              >{hasLogs ? status.logs : 'Лог появится после запуска сборки.'}</pre>
            </div>
          </div>
        ) : (
          <div className="flex items-center justify-between gap-3 px-4 py-3">
            <span className="text-xs text-slate-500">Прогресс: {progress}%</span>
            <button
              type="button"
              className="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
              disabled={isRunning || isLoading}
              onClick={() => { void startBuild() }}
            >
              {isRunning ? 'Сборка...' : 'Перекомпилировать'}
            </button>
          </div>
        )}
      </div>
    </aside>
  )
}
