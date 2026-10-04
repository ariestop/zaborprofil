import { useCallback, useEffect, useReducer, useRef } from 'react'
import { useInvalidateMediaAssets, uploadMediaAsset } from '../../entities/media/api'
import type { MediaAssetItem } from '../../types/api'
import { describeMediaError, validateUploadFile } from './utils'

export type UploadStatus = 'queued' | 'uploading' | 'done' | 'error'

export interface UploadItem {
  id: string
  file: File
  progress: number
  status: UploadStatus
  error: string | null
  asset: MediaAssetItem | null
}

type UploadAction =
  | { type: 'add'; items: UploadItem[] }
  | { type: 'patch'; id: string; patch: Partial<UploadItem> }
  | { type: 'remove'; id: string }
  | { type: 'clearFinished' }

const MAX_PARALLEL_UPLOADS = 2

function reducer(state: UploadItem[], action: UploadAction): UploadItem[] {
  switch (action.type) {
    case 'add':
      return [...state, ...action.items]
    case 'patch':
      return state.map((item) => (item.id === action.id ? { ...item, ...action.patch } : item))
    case 'remove':
      return state.filter((item) => item.id !== action.id)
    case 'clearFinished':
      return state.filter((item) => item.status === 'queued' || item.status === 'uploading')
  }
}

let uploadCounter = 0

function createItem(file: File): UploadItem {
  uploadCounter += 1
  const validationError = validateUploadFile(file)

  return {
    id: `upload-${uploadCounter}`,
    file,
    progress: 0,
    status: validationError === null ? 'queued' : 'error',
    error: validationError,
    asset: null,
  }
}

export function useMediaUploads(onUploaded?: (asset: MediaAssetItem) => void, folder?: string) {
  const [items, dispatch] = useReducer(reducer, [])
  const invalidate = useInvalidateMediaAssets()
  const started = useRef(new Set<string>())
  const controllers = useRef(new Map<string, AbortController>())
  const onUploadedRef = useRef(onUploaded)
  const folderRef = useRef(folder)

  useEffect(() => {
    onUploadedRef.current = onUploaded
    folderRef.current = folder
  }, [onUploaded, folder])

  useEffect(() => {
    const running = items.filter((item) => item.status === 'uploading').length
    const free = MAX_PARALLEL_UPLOADS - running
    if (free <= 0) {
      return
    }

    const next = items.filter((item) => item.status === 'queued' && !started.current.has(item.id)).slice(0, free)
    for (const item of next) {
      started.current.add(item.id)
      const controller = new AbortController()
      controllers.current.set(item.id, controller)
      dispatch({ type: 'patch', id: item.id, patch: { status: 'uploading', progress: 0 } })

      uploadMediaAsset(item.file, {
        signal: controller.signal,
        folder: folderRef.current,
        onProgress: (fraction) => dispatch({ type: 'patch', id: item.id, patch: { progress: fraction } }),
      })
        .then((asset) => {
          dispatch({ type: 'patch', id: item.id, patch: { status: 'done', progress: 1, asset } })
          onUploadedRef.current?.(asset)
          void invalidate()
        })
        .catch((error: unknown) => {
          if (error instanceof DOMException && error.name === 'AbortError') {
            dispatch({ type: 'remove', id: item.id })
            return
          }
          dispatch({ type: 'patch', id: item.id, patch: { status: 'error', error: describeMediaError(error) } })
        })
        .finally(() => {
          controllers.current.delete(item.id)
        })
    }
  }, [items, invalidate])

  useEffect(() => {
    const activeControllers = controllers.current

    return () => {
      for (const controller of activeControllers.values()) {
        controller.abort()
      }
    }
  }, [])

  const addFiles = useCallback((files: Iterable<File>) => {
    const created = Array.from(files).map(createItem)
    if (created.length > 0) {
      dispatch({ type: 'add', items: created })
    }
  }, [])

  const cancel = useCallback((id: string) => {
    const controller = controllers.current.get(id)
    if (controller !== undefined) {
      controller.abort()
      return
    }
    dispatch({ type: 'remove', id })
  }, [])

  const retry = useCallback((id: string) => {
    started.current.delete(id)
    dispatch({ type: 'patch', id, patch: { status: 'queued', progress: 0, error: null } })
  }, [])

  const clearFinished = useCallback(() => dispatch({ type: 'clearFinished' }), [])

  return { items, addFiles, cancel, retry, clearFinished }
}
