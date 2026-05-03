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
