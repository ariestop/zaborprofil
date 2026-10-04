import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useForm, useWatch, type FieldPath, type UseFormReturn } from 'react-hook-form'
import { useToast } from '../../app/providers/toast-provider'
import {
  fetchPageBuilder,
  fetchPageDetail,
  fetchPagePreviewLink,
  usePublishPageMutation,
  useSavePageBuilderMutation,
  useUpdatePageMutation,
  useUpdatePageSeoMutation,
} from '../../entities/page/api'
import { adminQueryKeys } from '../../shared/api/query'
import { applyServerValidationErrors } from '../../shared/api/validation'
import { useBuilderStore } from '../../modules/page-builder/state/builderStore'
import { normalizePageBlocks, validatePageBlocks } from '../../modules/page-builder/utils/pageBlocks'
import type { ContentPageDetail } from '../../types/api'
import { describeApiError } from '../seo/redirects/redirect-rules'
import {
  hasFieldChanges,
  pageEditorSchema,
  pageToFormValues,
  pickFields,
  PAGE_SEO_FIELDS,
  PAGE_SETTINGS_FIELDS,
  tabForField,
  toPagePayload,
  toSeoPayload,
  type EditorTab,
  type PageEditorFormValues,
} from './form'
import { clearDraft, draftDiffersFromServer, readDraft, writeDraft, type PageDraft } from './draft-storage'
import { EDIT_CONFLICT_MESSAGE, parseEditConflict, type EditConflict } from './edit-conflict'
import { resolveSaveState, type SaveState } from './save-state'

function seoSignatureOf(values: PageEditorFormValues): string {
  return JSON.stringify(PAGE_SEO_FIELDS.map((field) => values[field]))
}

export const AUTOSAVE_DELAY_MS = 20_000
export const DRAFT_WRITE_DELAY_MS = 500

export type SaveMode = 'manual' | 'auto'

interface UsePageEditorControllerOptions {
  page: ContentPageDetail
  /** Версия блоков, полученная вместе с документом Builder; без неё проверка конфликтов при сохранении отключена. */
  builderVersion?: string | null
  onInvalidTab?: (tab: EditorTab) => void
}

export interface PendingDraftInfo {
  savedAt: string
  /** Блоки на сервере изменились после создания черновика: при сохранении будет предложено разрешить конфликт. */
  serverChanged: boolean
}

export interface PageEditorController {
  form: UseFormReturn<PageEditorFormValues>
  pageId: string
  savedValues: PageEditorFormValues
  saveState: SaveState
  lastSavedAt: Date | null
  errorMessage: string | null
  settingsDirty: boolean
  seoDirty: boolean
  blocksDirty: boolean
  hasUnsavedChanges: boolean
  autosaveEnabled: boolean
  isPublishing: boolean
  isGuardDisabled: () => boolean
  conflict: EditConflict | null
  pendingDraft: PendingDraftInfo | null
  overwriteConflict: () => Promise<boolean>
  dismissConflict: () => void
  restoreDraft: () => void
  discardDraft: () => void
  saveAll: () => Promise<boolean>
  publish: () => Promise<boolean>
  openPreview: () => Promise<void>
  reloadFromServer: () => Promise<void>
  disableLeaveGuard: () => void
}

function openPreviewWindow(): Window | null {
  try {
    return window.open('about:blank', '_blank')
  } catch {
    return null
  }
}

export function usePageEditorController({ page, builderVersion = null, onInvalidTab }: UsePageEditorControllerOptions): PageEditorController {
  const pageId = page.id
  const queryClient = useQueryClient()
  const { push } = useToast()
  const updatePage = useUpdatePageMutation(pageId)
  const updateSeo = useUpdatePageSeoMutation(pageId)
  const saveBuilder = useSavePageBuilderMutation(pageId)
  const publishPage = usePublishPageMutation(pageId)

  const [initialValues] = useState(() => pageToFormValues(page))
  const form = useForm<PageEditorFormValues>({
    resolver: zodResolver(pageEditorSchema),
    defaultValues: initialValues,
  })

  const [savedValues, setSavedValues] = useState(initialValues)
  const savedRef = useRef(initialValues)
  const [saving, setSaving] = useState(false)
  const [failure, setFailure] = useState<{ seo: string; blocks: unknown } | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [hasSavedOnce, setHasSavedOnce] = useState(false)
  const [lastSavedAt, setLastSavedAt] = useState<Date | null>(null)
  const guardDisabledRef = useRef(false)
  const versionRef = useRef<string | null>(builderVersion)
  const [conflict, setConflict] = useState<EditConflict | null>(null)
  const [draft, setDraft] = useState<PageDraft | null>(() => {
    const stored = readDraft(page.id)
    if (stored === null) {
      return null
    }

    return draftDiffersFromServer(stored, { blocks: useBuilderStore.getState().blocks, values: initialValues }) ? stored : null
  })
  const inflightRef = useRef<Promise<boolean> | null>(null)

  const values = useWatch({ control: form.control }) as PageEditorFormValues
  const blocks = useBuilderStore((state) => state.blocks)
  const blocksDirty = useBuilderStore((state) => state.dirty)

  const settingsDirty = hasFieldChanges(values, savedValues, PAGE_SETTINGS_FIELDS)
  const seoDirty = hasFieldChanges(values, savedValues, PAGE_SEO_FIELDS)
  const hasUnsavedChanges = settingsDirty || seoDirty || blocksDirty
  const autosaveEnabled = page.status !== 'published' && conflict === null

  const seoSignature = useMemo(() => seoSignatureOf(values), [values])
  const failed = failure !== null && failure.seo === seoSignature && failure.blocks === blocks

  const markFailed = useCallback(() => {
    setFailure({ seo: seoSignatureOf(form.getValues()), blocks: useBuilderStore.getState().blocks })
  }, [form])

  const commitSaved = useCallback((source: PageEditorFormValues, fields: readonly (keyof PageEditorFormValues)[]) => {
    const next = pickFields(source, savedRef.current, fields)
    savedRef.current = next
    setSavedValues(next)
  }, [])

  const runSave = useCallback(async (mode: SaveMode): Promise<boolean> => {
    const manual = mode === 'manual'
    const current = form.getValues()
    const saved = savedRef.current
    const builder = useBuilderStore.getState()

    const settingsChanged = manual && hasFieldChanges(current, saved, PAGE_SETTINGS_FIELDS)
    let seoChanged = hasFieldChanges(current, saved, PAGE_SEO_FIELDS)
    let blocksChanged = builder.dirty

    if (!settingsChanged && !seoChanged && !blocksChanged) {
      return true
    }

    setSaving(true)
    setFailure(null)
    setErrorMessage(null)

    let skippedInvalid: string | null = null
    const reject = (tab: EditorTab, message: string): boolean => {
      markFailed()
      setErrorMessage(message)
      if (manual) {
        onInvalidTab?.(tab)
        push({ title: 'Не удалось сохранить', description: message })
      }

      return false
    }

    try {
      if (settingsChanged && !(await form.trigger([...PAGE_SETTINGS_FIELDS] as FieldPath<PageEditorFormValues>[]))) {
        return reject('settings', 'Исправьте ошибки во вкладке «Настройки».')
      }

      if (seoChanged && !(await form.trigger([...PAGE_SEO_FIELDS] as FieldPath<PageEditorFormValues>[]))) {
        if (manual) {
          return reject('seo', 'Исправьте ошибки во вкладке «SEO».')
        }

        seoChanged = false
        skippedInvalid = 'Исправьте ошибки во вкладке «SEO», чтобы автосохранение продолжилось.'
      }

      const sentBlocks = builder.blocks
      const normalizedBlocks = normalizePageBlocks(sentBlocks)
      if (blocksChanged) {
        const validation = validatePageBlocks(normalizedBlocks)
        useBuilderStore.getState().setValidationIssues(validation.issues)
        if (!validation.isValid) {
          if (manual) {
            return reject('content', 'Исправьте ошибки валидации блоков.')
          }

          blocksChanged = false
          skippedInvalid = 'Исправьте ошибки валидации блоков, чтобы автосохранение продолжилось.'
        }
      }

      if (settingsChanged) {
        await updatePage.mutateAsync(toPagePayload(current))
        commitSaved(current, PAGE_SETTINGS_FIELDS)
      }

      if (seoChanged) {
        await updateSeo.mutateAsync(toSeoPayload(current))
        commitSaved(current, PAGE_SEO_FIELDS)
      }

      if (blocksChanged) {
        const saved = await saveBuilder.mutateAsync({ blocks: normalizedBlocks, baseVersion: versionRef.current })
        if (typeof saved.version === 'string') {
          versionRef.current = saved.version
        }
        if (useBuilderStore.getState().blocks === sentBlocks) {
          useBuilderStore.getState().setDirty(false)
        }
      }

      if (skippedInvalid !== null) {
        markFailed()
        setErrorMessage(skippedInvalid)
        return false
      }

      setHasSavedOnce(true)
      setLastSavedAt(new Date())
      if (manual) {
        push({ title: 'Изменения сохранены' })
      }

      return true
    } catch (error) {
      const editConflict = parseEditConflict(error)
      if (editConflict !== null) {
        setConflict(editConflict)
        markFailed()
        setErrorMessage(EDIT_CONFLICT_MESSAGE)
        return false
      }

      applyServerValidationErrors(error, form.setError)
      const invalidField = Object.keys(form.formState.errors)[0]
      if (invalidField !== undefined) {
        onInvalidTab?.(tabForField(invalidField))
      }

      const message = describeApiError(error, 'Проверьте обязательные поля и права доступа.')
      markFailed()
      setErrorMessage(message)
      push({ title: 'Не удалось сохранить', description: message })
      return false
    } finally {
      setSaving(false)
    }
  }, [commitSaved, form, markFailed, onInvalidTab, push, saveBuilder, updatePage, updateSeo])

  const runSaveRef = useRef(runSave)
  useEffect(() => {
    runSaveRef.current = runSave
  }, [runSave])

  const save = useCallback((mode: SaveMode): Promise<boolean> => {
    const previous = inflightRef.current
    const task = previous === null
      ? runSaveRef.current(mode)
      : previous.catch(() => false).then(() => runSaveRef.current(mode))
    const tracked = task.finally(() => {
      if (inflightRef.current === tracked) {
        inflightRef.current = null
      }
    })
    inflightRef.current = tracked

    return tracked
  }, [])

  const saveAll = useCallback(() => save('manual'), [save])

  const autosaveDirty = seoDirty || blocksDirty
  useEffect(() => {
    if (!autosaveEnabled || !autosaveDirty) {
      return
    }

    const timer = window.setTimeout(() => {
      void save('auto')
    }, AUTOSAVE_DELAY_MS)

    return () => window.clearTimeout(timer)
  }, [autosaveDirty, autosaveEnabled, blocks, save, seoSignature])

  const publish = useCallback(async (): Promise<boolean> => {
    if (!(await save('manual'))) {
      return false
    }

    try {
      await publishPage.mutateAsync()
      push({ title: 'Страница опубликована', description: 'Публичная версия обновлена.' })
      return true
    } catch (error) {
      push({ title: 'Не удалось опубликовать', description: describeApiError(error, 'Проверьте права и SEO-аудит страницы.') })
      return false
    }
  }, [publishPage, push, save])

  const openPreview = useCallback(async (): Promise<void> => {
    const previewWindow = openPreviewWindow()
    try {
      if (!(await save('manual'))) {
        previewWindow?.close()
        return
      }

      const { previewUrl } = await fetchPagePreviewLink(pageId)
      if (previewWindow === null) {
        window.open(previewUrl, '_blank', 'noopener')
        return
      }

      previewWindow.opener = null
      previewWindow.location.href = previewUrl
    } catch (error) {
      previewWindow?.close()
      push({ title: 'Не удалось открыть предпросмотр', description: describeApiError(error, 'Повторите попытку.') })
    }
  }, [pageId, push, save])

  const reloadFromServer = useCallback(async (): Promise<void> => {
    const [freshPage, freshBuilder] = await Promise.all([fetchPageDetail(pageId), fetchPageBuilder(pageId)])
    const freshValues = pageToFormValues(freshPage)
    versionRef.current = freshBuilder.version
    setConflict(null)
    setDraft(null)
    clearDraft(pageId)
    savedRef.current = freshValues
    setSavedValues(freshValues)
    form.reset(freshValues)
    useBuilderStore.getState().setBlocks(normalizePageBlocks(freshBuilder.blocks))
    useBuilderStore.getState().setValidationIssues([])
    setFailure(null)
    setErrorMessage(null)
    await queryClient.invalidateQueries({ queryKey: adminQueryKeys.pageById(pageId) })
  }, [form, pageId, queryClient])

  const disableLeaveGuard = useCallback(() => {
    guardDisabledRef.current = true
  }, [])

  const isGuardDisabled = useCallback(() => guardDisabledRef.current, [])

  const overwriteConflict = useCallback(async (): Promise<boolean> => {
    if (conflict === null) {
      return false
    }

    versionRef.current = conflict.serverVersion
    setConflict(null)

    return save('manual')
  }, [conflict, save])

  const dismissConflict = useCallback(() => setConflict(null), [])

  const blocksRef = useRef(blocks)
  const persistDraftRef = useRef(false)
  const shouldPersistDraft = hasUnsavedChanges && draft === null
  useEffect(() => {
    blocksRef.current = blocks
    persistDraftRef.current = shouldPersistDraft
  }, [blocks, shouldPersistDraft])

  const persistDraft = useCallback(() => {
    writeDraft(pageId, { baseVersion: versionRef.current, blocks: blocksRef.current, values: form.getValues() })
  }, [form, pageId])

  useEffect(() => {
    if (draft !== null) {
      return
    }

    if (!hasUnsavedChanges) {
      clearDraft(pageId)
      return
    }

    const timer = window.setTimeout(persistDraft, DRAFT_WRITE_DELAY_MS)
    return () => window.clearTimeout(timer)
  }, [blocks, draft, hasUnsavedChanges, pageId, persistDraft, values])

  useEffect(() => {
    const flush = () => {
      if (persistDraftRef.current) {
        persistDraft()
      }
    }

    window.addEventListener('pagehide', flush)
    return () => {
      window.removeEventListener('pagehide', flush)
      flush()
    }
  }, [persistDraft])

  const restoreDraft = useCallback(() => {
    if (draft === null) {
      return
    }

    for (const field of [...PAGE_SETTINGS_FIELDS, ...PAGE_SEO_FIELDS]) {
      if (field in draft.values) {
        form.setValue(field, draft.values[field] as never, { shouldDirty: true })
      }
    }

    useBuilderStore.getState().setBlocks(normalizePageBlocks(draft.blocks), true)
    versionRef.current = draft.baseVersion ?? versionRef.current
    setDraft(null)
  }, [draft, form])

  const discardDraft = useCallback(() => {
    clearDraft(pageId)
    setDraft(null)
  }, [pageId])

  const pendingDraft: PendingDraftInfo | null = draft === null
    ? null
    : {
        savedAt: draft.savedAt,
        serverChanged: draft.baseVersion !== null && builderVersion !== null && draft.baseVersion !== builderVersion,
      }

  const saveState = resolveSaveState({ dirty: hasUnsavedChanges, saving, failed, hasSavedOnce })

  return {
    form,
    pageId,
    savedValues,
    saveState,
    lastSavedAt,
    errorMessage,
    settingsDirty,
    seoDirty,
    blocksDirty,
    hasUnsavedChanges,
    autosaveEnabled,
    isPublishing: publishPage.isPending,
    isGuardDisabled,
    conflict,
    pendingDraft,
    overwriteConflict,
    dismissConflict,
    restoreDraft,
    discardDraft,
    saveAll,
    publish,
    openPreview,
    reloadFromServer,
    disableLeaveGuard,
  }
}
