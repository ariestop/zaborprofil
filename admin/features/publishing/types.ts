export type WorkflowStatus = 'draft' | 'review' | 'approved' | 'published' | 'scheduled' | 'unpublished' | 'archived' | 'deleted'

export interface WorkflowTransition {
  status: WorkflowStatus
  allowed: boolean
}

export interface WorkflowRevisionRef {
  id: string
  version: number
  createdAt: string
}

export interface WorkflowHistoryEntry {
  id: string
  event: string
  occurredAt: string
  actor: string
  fromStatus: string | null
  toStatus: string | null
  comment: string | null
  details: Record<string, unknown>
}

export interface PageWorkflow {
  pageId: string
  status: WorkflowStatus
  scheduledPublishAt: string | null
  scheduledUnpublishAt: string | null
  hasUnpublishedChanges: boolean
  publishedRevision: WorkflowRevisionRef | null
  scheduledRevision: WorkflowRevisionRef | null
  transitions: WorkflowTransition[]
  canCancelSchedule: boolean
  history: WorkflowHistoryEntry[]
}

export type DiffScalar = string | number | boolean | null

export interface TextDiffPart {
  op: 'equal' | 'insert' | 'delete'
  text: string
}

export interface DiffChange {
  field: string
  before: DiffScalar
  after: DiffScalar
  textDiff?: TextDiffPart[]
}

export interface BlockDiff {
  status: 'added' | 'removed' | 'changed' | 'unchanged'
  type: string
  name: string
  positionBefore: number | null
  positionAfter: number | null
  changes: DiffChange[]
}

export interface RevisionDiffSide {
  id: string
  version: number | null
  createdAt: string
  createdBy: string | null
  comment: string | null
  action: string | null
}

export interface RevisionDiff {
  pageId: string
  from: RevisionDiffSide
  to: RevisionDiffSide
  hasChanges: boolean
  summary: {
    fields: number
    seo: number
    settings: number
    blocksAdded: number
    blocksRemoved: number
    blocksChanged: number
  }
  fields: DiffChange[]
  seo: DiffChange[]
  settings: DiffChange[]
  blocks: BlockDiff[]
}
