export interface SettingItem {
  id: string
  scope: string
  key: string
  value: unknown
  description: string | null
  updatedAt: string
}

export interface MigrationItem {
  version: string
  file: string
  description: string
  isApplied: boolean
  executedAt: string | null
  executionTime: number | null
  canApply: boolean
  canRollback: boolean
}

export interface RedirectItem {
  id: string
  sourcePath: string
  targetPath: string
  statusCode: 301 | 302 | 307 | 308
  isActive: boolean
  hitCount: number
  lastHitAt: string | null
  updatedAt: string
}

export interface HealthCheckItem {
  name: string
  label: string
  status: 'ok' | 'warning' | 'fail'
  message: string
  details: Record<string, string | number | boolean | null>
  checkedAt: string
}

export interface SystemWarningItem {
  code: string
  severity: 'warning' | 'critical'
  message: string
}

export interface SystemHealthResponse {
  status: 'ok' | 'error'
  environment: {
    appEnv: string
    appDebug: boolean
    phpVersion: string
    databasePlatform: string
  }
  checks: HealthCheckItem[]
  warnings: SystemWarningItem[]
  checkedAt: string
}

export interface MaintenanceStatus {
  enabled: boolean
  message?: string | null
  allowedIps?: string[]
  enabledAt?: string | null
}

export interface AuditLogEntryItem {
  id: string
  occurredAt: string
  actorId: string | null
  actorEmail: string | null
  ip: string | null
  userAgent: string | null
  requestId: string | null
  action: string
  entityType: string
  entityId: string | null
  oldValues: Record<string, unknown>
  newValues: Record<string, unknown>
}

export interface PageSeoPayload {
  metaDescription: string | null
  canonicalUrl: string | null
  ogTitle: string | null
  ogDescription: string | null
  ogImage: string | null
  ogType: string | null
  jsonLd: Record<string, unknown>[] | null
}

export interface ContentPageItem {
  id: string
  type: string
  title: string
  slug: string
  path: string
  h1: string
  status: 'draft' | 'published' | 'archived'
  template: string
  sortOrder: number
  isIndexable: boolean
  publishedAt: string | null
  seo: PageSeoPayload
}

export interface ContentBlockItem {
  id: string
  pageId: string
  type: string
  name: string
  position: number
  isEnabled: boolean
  content: Record<string, unknown>
  settings: Record<string, unknown>
  createdAt: string
  updatedAt: string
}

export interface ContentPageDetail extends ContentPageItem {
  blocks: ContentBlockItem[]
}

export interface MediaAssetItem {
  id: string
  originalName: string
  filename: string
  publicPath: string
  mimeType: string
  size: number
  width: number | null
  height: number | null
  variants: Array<{
    type: string
    publicPath: string
    width: number | null
    height: number | null
    mimeType: string
    size: number
  }>
  createdAt: string
}

export interface MenuItem {
  id: string
  position: string
  label: string
  url: string
  sortOrder: number
  isActive: boolean
  updatedAt: string
}

export interface LeadItem {
  id: string
  source: string
  name: string
  phone: string
  email: string | null
  message: string | null
  consentSnapshot: Record<string, unknown>
  status: 'new' | 'in_progress' | 'done' | 'spam'
  spamScore: number
  spamReasons: string[]
  createdAt: string
  updatedAt: string
}
