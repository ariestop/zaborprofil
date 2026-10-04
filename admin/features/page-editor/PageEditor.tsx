import { useCallback, useEffect, useLayoutEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Badge, Button, Switch } from '../../shared/ui'
import { useBuilderStore } from '../../modules/page-builder/state/builderStore'
import type { BuilderBlock } from '../../modules/page-builder/types'
import { normalizePageBlocks } from '../../modules/page-builder/utils/pageBlocks'
import type { ContentPageDetail } from '../../types/api'
import { useAdvancedMode } from './advanced-mode'
import { ContentTab } from './ContentTab'
import type { EditorTab } from './form'
import { LeaveGuard } from './LeaveGuard'
import { canPublishFrom, pageStatusLabels, statusTone } from './page-status'
import { RevisionsTab } from './RevisionsTab'
import { SaveIndicator } from './SaveIndicator'
import { SeoTab } from './SeoTab'
import { SettingsTab } from './SettingsTab'
import { PagePublishingSlot } from './slots'
import { usePageEditorController } from './usePageEditorController'

interface PageEditorProps {
  page: ContentPageDetail
  initialBlocks: BuilderBlock[]
  tab: EditorTab
}

export const editorTabs: Array<{ value: EditorTab, label: string }> = [
  { value: 'content', label: 'Контент' },
  { value: 'seo', label: 'SEO' },
  { value: 'settings', label: 'Настройки' },
  { value: 'revisions', label: 'Ревизии' },
]

export function pageEditorTabPath(pageId: string, tab: EditorTab): string {
  return tab === 'content' ? `/admin/pages/${pageId}` : `/admin/pages/${pageId}/${tab}`
}

function isInsidePage(pathname: string, pageId: string): boolean {
  const base = `/admin/pages/${pageId}`
  return pathname === base || pathname.startsWith(`${base}/`)
}

function initBuilderStore(initialBlocks: BuilderBlock[]): void {
  const store = useBuilderStore.getState()
  store.setBlocks(normalizePageBlocks(initialBlocks))
  store.setValidationIssues([])
}

export function PageEditor({ page, initialBlocks, tab }: PageEditorProps) {
  const navigate = useNavigate()
  const advanced = useAdvancedMode((state) => state.enabled)
  const setAdvanced = useAdvancedMode((state) => state.setEnabled)
  useState(() => {
    initBuilderStore(initialBlocks)
    return true
  })

  useLayoutEffect(() => {
    initBuilderStore(initialBlocks)

    return () => {
      const cleanup = useBuilderStore.getState()
      cleanup.setBlocks([])
      cleanup.setValidationIssues([])
    }
    // Блоки инициализируются один раз: последующие обновления запроса не должны затирать несохранённые правки.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page.id])

  const openTab = useCallback((target: EditorTab) => {
    void navigate(pageEditorTabPath(page.id, target))
  }, [navigate, page.id])

  const controller = usePageEditorController({ page, onInvalidTab: openTab })
  const { saveAll } = controller

  useEffect(() => {
    const listener = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
        event.preventDefault()
        void saveAll()
      }
    }

    window.addEventListener('keydown', listener)
    return () => window.removeEventListener('keydown', listener)
  }, [saveAll])

  const { isGuardDisabled } = controller
  const isAllowedNavigation = useCallback(
    (next: { pathname: string }) => isGuardDisabled() || isInsidePage(next.pathname, page.id),
    [isGuardDisabled, page.id],
  )

  const publishVisible = canPublishFrom(page.status)

  return (
    <div data-testid="page-editor">
      <LeaveGuard when={controller.hasUnsavedChanges} isAllowed={isAllowedNavigation} onSaveAndLeave={saveAll} />

      <header className="mb-4 flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-xl font-semibold">{page.title}</h2>
            <Badge tone={statusTone(page.status)}>{pageStatusLabels[page.status]}</Badge>
          </div>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {page.status === 'published' ? (
              <a href={page.path} target="_blank" rel="noreferrer" className="font-mono text-emerald-700 underline dark:text-emerald-400">{page.path}</a>
            ) : (
              <span className="font-mono">{page.path}</span>
            )}
            {' · '}
            <Link to="/admin/pages" className="underline">К списку страниц</Link>
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <SaveIndicator
            state={controller.saveState}
            lastSavedAt={controller.lastSavedAt}
            autosaveActive={controller.autosaveEnabled}
            hint={controller.saveState === 'error' && controller.errorMessage !== null ? controller.errorMessage : undefined}
          />
          <label className="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
            <Switch checked={advanced} onCheckedChange={setAdvanced} />
            Расширенный режим (JSON)
          </label>
          <Button type="button" variant="outline" onClick={() => void controller.openPreview()}>
            Предпросмотр
          </Button>
          <Button type="button" variant="outline" disabled={!controller.hasUnsavedChanges || controller.saveState === 'saving'} onClick={() => void saveAll()}>
            Сохранить
          </Button>
          {publishVisible ? (
            <Button type="button" disabled={controller.isPublishing || controller.saveState === 'saving'} onClick={() => void controller.publish()}>
              {controller.isPublishing ? 'Публикация…' : 'Опубликовать'}
            </Button>
          ) : null}
          <PagePublishingSlot
            pageId={page.id}
            status={page.status}
            saveState={controller.saveState}
            hasUnsavedChanges={controller.hasUnsavedChanges}
            saveAll={saveAll}
          />
        </div>
      </header>

      <div role="tablist" aria-label="Разделы страницы" className="mb-4 flex flex-wrap gap-1 border-b border-slate-200 dark:border-slate-800">
        {editorTabs.map((item) => {
          const active = item.value === tab
          return (
            <Link
              key={item.value}
              role="tab"
              aria-selected={active}
              to={pageEditorTabPath(page.id, item.value)}
              className={[
                '-mb-px rounded-t-lg border-b-2 px-4 py-2 text-sm font-medium transition',
                active
                  ? 'border-emerald-600 text-emerald-800 dark:text-emerald-300'
                  : 'border-transparent text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-100',
              ].join(' ')}
            >
              {item.label}
              {item.value === 'seo' && controller.seoDirty ? <span className="ml-1 text-amber-600" aria-label="есть изменения">•</span> : null}
              {item.value === 'settings' && controller.settingsDirty ? <span className="ml-1 text-amber-600" aria-label="есть изменения">•</span> : null}
              {item.value === 'content' && controller.blocksDirty ? <span className="ml-1 text-amber-600" aria-label="есть изменения">•</span> : null}
            </Link>
          )
        })}
      </div>

      <div role="tabpanel" aria-label={editorTabs.find((item) => item.value === tab)?.label}>
        {tab === 'content' ? <ContentTab pageId={page.id} /> : null}
        {tab === 'seo' ? <SeoTab controller={controller} onOpenTab={openTab} /> : null}
        {tab === 'settings' ? <SettingsTab controller={controller} page={page} /> : null}
        {tab === 'revisions' ? <RevisionsTab controller={controller} status={page.status} /> : null}
      </div>
    </div>
  )
}
