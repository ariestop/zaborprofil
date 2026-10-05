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
  actionsAllowed: boolean
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

export interface AssetBuildStatus {
  status: 'idle' | 'running' | 'success' | 'failed'
  command: string
  selectedTargets: string[]
  availableTargets: Array<{
    id: string
    label: string
    description: string
  }>
  startedAt: string | null
  finishedAt: string | null
  exitCode: number | null
  progress: number
  logs: string
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
  metaTitle: string | null
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
  status: 'draft' | 'review' | 'approved' | 'published' | 'scheduled' | 'unpublished' | 'archived' | 'deleted'
  template: string
  sortOrder: number
  parentId?: string | null
  isIndexable: boolean
  visibility: 'public' | 'hidden' | 'unlisted'
  publishedAt: string | null
  scheduledPublishAt: string | null
  scheduledUnpublishAt: string | null
  seo: PageSeoPayload
}

export interface ContentBlockItem {
  id: string
  pageId: string
  type: string
  name: string
  position: number
  isEnabled: boolean
  visibility: 'public' | 'hidden' | 'unlisted'
  content: Record<string, unknown>
  settings: Record<string, unknown>
  createdAt: string
  updatedAt: string
}

export interface ContentPageDetail extends ContentPageItem {
  blocks: ContentBlockItem[]
}

export interface BlockSchemaItem {
  type: string
  label: string
  description: string
  requiredContentFields: string[]
  recommendedPageTypes: string[]
  defaultContent: Record<string, unknown>
  defaultSettings: Record<string, unknown>
  priority: string
  seoImpact: string
  isLegacy: boolean
}

export interface PageTemplateBlock {
  type: string
  name: string
  position: number
  content: Record<string, unknown>
  settings: Record<string, unknown>
  isEnabled: boolean
  hint?: string
}

export interface PageTemplateItem {
  id: string
  code: string
  name: string
  description: string | null
  kind: 'page' | 'section'
  pageType: string
  blocksSchema: PageTemplateBlock[]
  defaultSeo: Record<string, unknown>
  defaultSettings: Record<string, unknown>
  isSystem: boolean
  isActive: boolean
}

export interface PageRevisionItem {
  id: string
  pageId: string
  version: number
  title: string
  h1: string
  path: string
  type: string
  template: string
  createdAt: string
  comment: string | null
  changeSummary: Record<string, unknown>
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
  alt: string | null
  title: string | null
  description?: string | null
  folder?: string | null
  fileHash?: string | null
  focalX?: number | null
  focalY?: number | null
  usageCount?: number
  duplicate?: boolean
  createdAt: string
}

export interface MediaUsageItem {
  type: 'page_seo' | 'page_block' | 'product' | 'category' | 'menu_item' | 'setting'
  sourceId: string
  title: string
  location: string
  adminPath: string | null
  status: string | null
}

export interface MediaUsageResponse {
  total: number
  usages: MediaUsageItem[]
}

export interface MediaFolderItem {
  name: string
  count: number
}

export interface MediaPagination {
  page: number
  perPage: number
  total: number
  totalPages: number
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

export type LeadStatus = 'new' | 'in_progress' | 'done' | 'spam'

export interface LeadAssigneeRef {
  id: string
  email: string | null
}

export interface LeadItem {
  id: string
  source: string
  name: string
  phone: string
  email: string | null
  status: LeadStatus
  assignee: LeadAssigneeRef | null
  spamScore: number
  b2b: boolean
  readAt: string | null
  /** Страница сайта, с которой отправлена форма. */
  pageUrl: string | null
  messagePreview: string | null
  createdAt: string
  updatedAt: string
}

export interface LeadEvent {
  id: string
  type: 'status_changed' | 'note' | 'assigned'
  actorId: string | null
  actorLabel: string
  body: string | null
  data: Record<string, unknown>
  createdAt: string
}

export interface LeadDetail {
  id: string
  source: string
  name: string
  phone: string
  email: string | null
  message: string | null
  consentSnapshot: Record<string, unknown>
  status: LeadStatus
  assignee: LeadAssigneeRef | null
  spamScore: number
  spamReasons: string[]
  pageUrl: string | null
  utm: Record<string, string>
  b2b: boolean
  readAt: string | null
  createdAt: string
  updatedAt: string
  events: LeadEvent[]
}

export interface SystemOverviewResponse extends SystemHealthResponse {
  canManageDangerousActions: boolean
}

export interface SystemProcessStatusResponse {
  host: string
  environment: string
  supportedActions: {
    restart: string[]
    reload: string[]
  }
  checkedAt: string
}

export interface SystemCommandResult {
  action: string
  command: string
  exitCode: number
  output: string
  startedAt: string
  finishedAt: string
}

export interface SystemLogsResponse {
  channel: string
  path: string
  content: string
  readAt: string
}

export interface SystemQueuesResponse {
  transports: {
    async: number
    failed: number
  }
  checkedAt: string
}

export interface SystemObservabilityResponse {
  serverErrors: {
    lastHour: number
    last24Hours: number
  }
  queue: {
    pending: number
    failed: number
  }
  disk: {
    status: 'ok' | 'warning' | 'fail'
    freeBytes: number | null
    totalBytes: number | null
    usedPercent: number | null
  }
  checkedAt: string
}

export interface SystemCacheResponse {
  adapter: string
  namespace: string
  checkedAt: string
}

export interface SystemDatabaseResponse {
  databaseName: string
  platform: string
  serverVersion: string
  connected: boolean
  checkedAt: string
}

export interface SystemSecurityResponse {
  csrfRequired: boolean
  originCheckRequired: boolean
  actor: {
    identifier?: string
    roles: string[]
  }
  dangerousActions: {
    requiresRole: string
    requiresConfirmToken: boolean
    requiresAuditLog: boolean
  }
  checkedAt: string
}

export interface SystemBackupFile {
  name: string
  size: number
  modifiedAt: string | null
}

export interface SystemBackupsResponse {
  backupDirectory: string
  latestBackup: SystemBackupFile | null
  files: SystemBackupFile[]
  checkedAt: string
}

export interface SystemDeployResponse {
  release: string | null
  commit: string | null
  builtAt: string | null
  deployedAt: string | null
  releaseInfoPath: string
  checkedAt: string
}
