import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { ApiError } from './client'

interface ValidationPayload {
  error?: unknown
  code?: unknown
  details?: Array<{ field?: unknown, message?: unknown }>
}

export function applyServerValidationErrors<TFieldValues extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<TFieldValues>,
): void {
  if (!(error instanceof ApiError)) {
    return
  }

  if (error.status !== 422 || typeof error.payload !== 'object' || error.payload === null) {
    return
  }

  const payload = error.payload as ValidationPayload
  if (!Array.isArray(payload.details)) {
    return
  }

  for (const detail of payload.details) {
    if (typeof detail.field !== 'string' || typeof detail.message !== 'string') {
      continue
    }

    setError(detail.field as Path<TFieldValues>, {
      type: 'server',
      message: detail.message,
    })
  }
}
