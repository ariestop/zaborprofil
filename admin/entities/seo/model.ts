import type { RedirectItem } from '../../types/api'

export type RedirectStatusFilter = 'all' | 'active' | 'inactive'
export type RedirectSort = 'source' | 'hits' | 'updated' | 'lastHit'
export type SortDirection = 'asc' | 'desc'

export interface RedirectListParams {
  q: string
  status: RedirectStatusFilter
  sort: RedirectSort
  direction: SortDirection
  page: number
  perPage: number
}

export interface PaginatedResponse<TItem> {
  items: TItem[]
  total: number
  page: number
  perPage: number
  pages: number
}

export interface RedirectListResponse extends PaginatedResponse<RedirectItem> {
  counts: { total: number, active: number, inactive: number }
}

export interface RedirectWarning {
  code: string
  message: string
}

export type RedirectSaveResult = RedirectItem & { warnings: RedirectWarning[] }

export interface RedirectPayload {
  sourcePath?: string
  targetPath: string
  statusCode: number
  isActive: boolean
}

export interface RedirectRuleRef {
  id: string
  sourcePath: string
  targetPath: string
  statusCode: number
}

export interface RedirectAnalysis {
  activeRules: number
  loops: Array<{ path: string[], rules: RedirectRuleRef[] }>
  chains: Array<{ path: string[], finalTarget: string, rules: RedirectRuleRef[] }>
}

export type ImportAction = 'create' | 'update' | 'skip'

export interface RedirectImportReport {
  dryRun: boolean
  totalRows: number
  created: number
  updated: number
  skipped: number
  failed: number
  errors: Array<{ line: number, source: string, message: string }>
  warnings: Array<{ line: number, source: string, message: string }>
  preview: Array<{ line: number, source: string, target: string, status: number, action: ImportAction }>
}

export interface RobotsSettings {
  body: string
  effectiveBody: string
  defaultBody: string
  environment: string
  overriddenByEnvironment: boolean
}

export interface RobotsIssue {
  severity: 'error' | 'warning'
  line: number | null
  message: string
}

export interface RobotsPreview {
  normalizedBody: string | null
  effectiveBody: string
  usesDefault: boolean
  overriddenByEnvironment: boolean
  valid: boolean
  issues: RobotsIssue[]
}

export type NotFoundSort = 'hits' | 'lastSeen'

export interface NotFoundEntry {
  id: string
  path: string
  hitCount: number
  firstSeenAt: string
  lastSeenAt: string
  referrer: string | null
  hasRedirect: boolean
}

export interface NotFoundListParams {
  q: string
  sort: NotFoundSort
  page: number
  perPage: number
}

export interface NotFoundListResponse extends PaginatedResponse<NotFoundEntry> {
  totalHits: number
}

export interface SeoAuditIssue {
  severity: 'P0' | 'P1' | 'P2'
  code: string
  message: string
  field: string
}

export interface SeoAuditResult {
  pageId: string
  path: string
  passed: boolean
  issues: SeoAuditIssue[]
}
