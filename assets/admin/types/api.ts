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
