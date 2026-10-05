import { apiRequest } from '../../shared/api/client'
import type { ContentPageItem } from '../../types/api'
import type { PageWorkflow, RevisionDiff, WorkflowStatus } from './types'

const pagePath = (pageId: string): string => `/admin/api/content/pages/${pageId}`

export const workflowQueryKey = (pageId: string) => ['page-workflow', pageId] as const

export function fetchWorkflow(pageId: string): Promise<PageWorkflow> {
  return apiRequest<PageWorkflow>(`${pagePath(pageId)}/workflow`)
}

export function changePageStatus(pageId: string, status: WorkflowStatus, comment: string | null): Promise<ContentPageItem> {
  return apiRequest<ContentPageItem>(`${pagePath(pageId)}/status`, { method: 'PATCH', body: { status, comment } })
}

export interface ScheduleInput {
  publishAt: string | null
  unpublishAt: string | null
  comment: string | null
}

export function schedulePage(pageId: string, input: ScheduleInput): Promise<ContentPageItem> {
  return apiRequest<ContentPageItem>(`${pagePath(pageId)}/schedule`, { method: 'POST', body: input })
}

export function cancelPageSchedule(pageId: string, comment: string | null): Promise<ContentPageItem> {
  return apiRequest<ContentPageItem>(`${pagePath(pageId)}/schedule`, { method: 'DELETE', body: { comment } })
}

export function fetchRevisionDiff(pageId: string, from: string, to: string): Promise<RevisionDiff> {
  const query = new URLSearchParams({ from, to })

  return apiRequest<RevisionDiff>(`${pagePath(pageId)}/revisions/diff?${query.toString()}`)
}

export function rollbackRevision(pageId: string, revisionId: string): Promise<ContentPageItem> {
  return apiRequest<ContentPageItem>(`${pagePath(pageId)}/revisions/${revisionId}/rollback`, { method: 'POST' })
}
