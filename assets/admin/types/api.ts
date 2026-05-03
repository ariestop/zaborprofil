export interface SettingItem {
  id: string
  scope: string
  key: string
  value: unknown
  description: string | null
  updatedAt: string
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
