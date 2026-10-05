import { useCallback, useEffect, useLayoutEffect, useState } from 'react'
import { useWatch } from 'react-hook-form'
import { Link, useNavigate } from 'react-router-dom'
import type { AdminPermission } from '../../entities/user/permissions'
import ForbiddenPage from '../../pages/ForbiddenPage'
import { Button, Dropdown, type DropdownItem } from '../../shared/ui'
import { cn } from '../../shared/lib/cn'
import { useAuthStore } from '../../stores/auth'
import { useBuilderStore } from '../../modules/page-builder/state/builderStore'
import type { BuilderBlock } from '../../modules/page-builder/types'
import { normalizePageBlocks } from '../../modules/page-builder/utils/pageBlocks'
import type { ContentPageDetail } from '../../types/api'
import { useAdvancedMode } from './advanced-mode'
import { ContentTab } from './ContentTab'
import { DraftRestoreBanner, EditConflictDialog, EditLockBanner } from './EditSafetyPanels'
import type { EditorTab } from './form'
import { LeaveGuard } from './LeaveGuard'
import { canPublishFrom, pageStatusLabels, statusTone } from './page-status'
import { SaveTemplateDialog, type SaveTemplateRequest } from './SaveTemplateDialog'
import { RevisionsTab } from './RevisionsTab'
import { SaveIndicator } from './SaveIndicator'
import { SeoTab } from './SeoTab'
import { SettingsTab } from './SettingsTab'
import { PagePublishingSlot } from './slots'
import { usePageEditLock } from './usePageEditLock'
import { usePageEditorController } from './usePageEditorController'

interface PageEditorProps {
  page: ContentPageDetail
  initialBlocks: BuilderBlock[]
  initialBuilderVersion?: string | null
  tab: EditorTab
}

export const editorTabs: Array<{ value: EditorTab, label: string, permission?: AdminPermission }> = [
  { value: 'content', label: 'Контент' },
  { value: 'seo', label: 'SEO', permission: 'seo.edit' },
  { value: 'settings', label: 'Настройки', permission: 'pages.edit' },
  { value: 'revisions', label: 'История', permission: 'pages.view_revisions' },
]

const statusPill: Record<ReturnType<typeof statusTone>, string> = {
  success: 'bg-[#DCFAE6] text-[#067647] dark:bg-emerald-900/40 dark:text-emerald-200',
  warning: 'bg-[#FEF0C7] text-[#7A4A00] dark:bg-amber-900/40 dark:text-amber-200',
  neutral: 'bg-[#F2F4F7] text-[#344054] dark:bg-slate-800 dark:text-slate-200',
}

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

export function PageEditor({ page, initialBlocks, initialBuilderVersion = null, tab }: PageEditorProps) {
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

  const controller = usePageEditorController({ page, builderVersion: initialBuilderVersion, onInvalidTab: openTab })
  const editLock = usePageEditLock(page.id)
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

  const permissions = useAuthStore((state) => state.permissions)
  const visibleTabs = editorTabs.filter((item) => item.permission === undefined || permissions.includes(item.permission))
  const tabAllowed = visibleTabs.some((item) => item.value === tab)
  const publishVisible = canPublishFrom(page.status) && permissions.includes('pages.publish')
  const canManageTemplates = permissions.includes('pages.manage_templates')
  const [publishingSignal, setPublishingSignal] = useState(0)
  const [pageTemplateRequest, setPageTemplateRequest] = useState<SaveTemplateRequest | null>(null)
  const [h1, metaDescription, ogImage] = useWatch({ control: controller.form.control, name: ['h1', 'metaDescription', 'ogImage'] })

  const menuItems: DropdownItem[] = [
    { key: 'publishing', label: 'Публикация и расписание…', onSelect: () => setPublishingSignal((value) => value + 1) },
    ...(canManageTemplates
      ? [{ key: 'template', label: 'Сохранить страницу как шаблон', disabled: useBuilderStore.getState().blocks.length === 0, onSelect: () => setPageTemplateRequest({ kind: 'page', blocks: useBuilderStore.getState().blocks }) }]
      : []),
    { key: 'json', label: advanced ? 'Выключить режим JSON' : 'Режим JSON (для разработчика)', onSelect: () => setAdvanced(!advanced) },
  ]

  return (
    <div data-testid="page-editor" className="flex min-w-0 flex-col">
      <LeaveGuard when={controller.hasUnsavedChanges} isAllowed={isAllowedNavigation} onSaveAndLeave={saveAll} />

      <header className="border-b border-[#E4E7EC] bg-white px-4 pt-4 lg:px-6 dark:border-slate-800 dark:bg-slate-900">
        <div className="flex flex-wrap items-center gap-x-4 gap-y-3">
          <div className="flex min-w-0 flex-[1_1_360px] flex-col gap-1">
            <div className="flex flex-wrap items-center gap-2.5">
              <h1 className="text-[22px] font-bold tracking-[-0.01em]">{page.title}</h1>
              <span className={cn('rounded-full px-2 py-0.5 text-xs font-semibold', statusPill[statusTone(page.status)])}>{pageStatusLabels[page.status]}</span>
            </div>
            <div className="flex flex-wrap items-center gap-x-3.5 gap-y-1 text-[13px] text-[#5D6679] dark:text-slate-400">
              {page.status === 'published' ? (
                <a href={page.path} target="_blank" rel="noreferrer" className="font-mono text-xs text-[#047857] underline dark:text-emerald-400">{page.path}</a>
              ) : (
                <span className="font-mono text-xs">{page.path}</span>
              )}
              <Link to="/admin/pages" className="underline-offset-2 hover:underline">К списку страниц</Link>
            </div>
          </div>
          <SaveIndicator
            state={controller.saveState}
            lastSavedAt={controller.lastSavedAt}
            autosaveActive={controller.autosaveEnabled}
            hint={controller.saveState === 'error' && controller.errorMessage !== null ? controller.errorMessage : undefined}
          />
          <div className="flex flex-wrap items-center gap-2">
            <Button type="button" variant="outline" onClick={() => void controller.openPreview()}>
              Предпросмотр
            </Button>
            <Button type="button" variant="outline" disabled={!controller.hasUnsavedChanges || controller.saveState === 'saving'} onClick={() => void saveAll()}>
              Сохранить
            </Button>
            <div className="flex">
              {publishVisible ? (
                <Button
                  type="button"
                  className="rounded-r-none bg-[#047857] hover:bg-[#065F46]"
                  disabled={controller.isPublishing || controller.saveState === 'saving'}
                  onClick={() => void controller.publish()}
                >
                  {controller.isPublishing ? 'Публикация…' : 'Опубликовать'}
                </Button>
              ) : null}
              <Dropdown
                align="end"
                items={menuItems}
                trigger={(
                  <button
                    type="button"
                    aria-label="Другие действия со страницей"
                    className={cn(
                      'inline-flex h-10 w-10 items-center justify-center focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-emerald-500',
                      publishVisible
                        ? 'rounded-r-lg border-l border-[#0B6B4E] bg-[#047857] text-white hover:bg-[#065F46]'
                        : 'rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100',
                    )}
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 9.5l6 6 6-6" /></svg>
                  </button>
                )}
              />
            </div>
            <PagePublishingSlot
              pageId={page.id}
              status={page.status}
              saveState={controller.saveState}
              hasUnsavedChanges={controller.hasUnsavedChanges}
              saveAll={saveAll}
              hideTrigger
              openSignal={publishingSignal}
            />
          </div>
        </div>

        <div role="tablist" aria-label="Разделы страницы" className="mt-3 flex flex-wrap gap-1">
          {visibleTabs.map((item) => {
            const active = item.value === tab
            const dirty = (item.value === 'seo' && controller.seoDirty) || (item.value === 'settings' && controller.settingsDirty) || (item.value === 'content' && controller.blocksDirty)
            return (
              <Link
                key={item.value}
                role="tab"
                aria-selected={active}
                to={pageEditorTabPath(page.id, item.value)}
                className={cn(
                  'flex h-[42px] items-center gap-1.5 px-3.5 text-sm transition',
                  active
                    ? 'font-semibold text-[#101828] shadow-[inset_0_-2px_0_#047857] dark:text-slate-100'
                    : 'font-medium text-[#475467] hover:text-[#101828] dark:text-slate-400 dark:hover:text-slate-100',
                )}
              >
                {item.label}
                {dirty ? <span className="text-amber-600" aria-label="есть изменения">•</span> : null}
              </Link>
            )
          })}
        </div>
      </header>

      <div className="flex flex-col gap-4 px-4 py-5 lg:px-6">
        <EditLockBanner lock={editLock} />
        <DraftRestoreBanner draft={controller.pendingDraft} onRestore={controller.restoreDraft} onDiscard={controller.discardDraft} />
        <EditConflictDialog
          conflict={controller.conflict}
          onOverwrite={() => void controller.overwriteConflict()}
          onReload={() => void controller.reloadFromServer()}
          onDismiss={controller.dismissConflict}
        />

        <div role="tabpanel" aria-label={editorTabs.find((item) => item.value === tab)?.label}>
          {!tabAllowed ? <ForbiddenPage /> : null}
          {tabAllowed && tab === 'content' ? (
            <ContentTab
              pageType={page.type}
              pageH1={h1}
              pagePath={page.path}
              metaDescription={metaDescription}
              ogImage={ogImage}
              onOpenSeo={() => openTab('seo')}
            />
          ) : null}
          {tabAllowed && tab !== 'content' ? (
            <section className="mx-auto max-w-5xl rounded-2xl border border-[#E4E7EC] bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
              {tab === 'seo' ? <SeoTab controller={controller} onOpenTab={openTab} /> : null}
              {tab === 'settings' ? <SettingsTab controller={controller} page={page} /> : null}
              {tab === 'revisions' ? <RevisionsTab controller={controller} status={page.status} /> : null}
            </section>
          ) : null}
        </div>
      </div>

      {canManageTemplates ? <SaveTemplateDialog request={pageTemplateRequest} pageType={page.type} onClose={() => setPageTemplateRequest(null)} /> : null}
    </div>
  )
}
