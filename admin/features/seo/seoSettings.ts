import type { SettingItem } from '../../types/api'
import { useSettingsQuery } from '../../entities/settings/api'

export const seoSettingsScope = 'seo'
export const titleTemplateKey = 'title_template'
export const siteNameKey = 'site_name'
export const defaultSiteName = 'ЗаборПрофиль'

export interface SeoTitleSettings {
  titleTemplate: string
  siteName: string
}

export function readSeoTitleSettings(items: SettingItem[] | undefined): SeoTitleSettings {
  const read = (key: string): string | null => {
    const value = items?.find((item) => item.scope === seoSettingsScope && item.key === key)?.value
    return typeof value === 'string' ? value : null
  }

  return {
    titleTemplate: read(titleTemplateKey) ?? '',
    siteName: read(siteNameKey) ?? defaultSiteName,
  }
}

export function useSeoTitleSettings(): SeoTitleSettings {
  const query = useSettingsQuery(seoSettingsScope)
  return readSeoTitleSettings(query.data)
}
