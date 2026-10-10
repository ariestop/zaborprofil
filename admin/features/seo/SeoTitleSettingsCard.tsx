import { useState } from 'react'
import { useSettingsQuery, useUpsertSettingMutation } from '../../entities/settings/api'
import { useToast } from '../../app/providers/toast-provider'
import { Button, Card, Input } from '../../shared/ui'
import { LengthCounter } from './LengthCounter'
import { readSeoTitleSettings, seoSettingsScope, siteNameKey, titleTemplateKey } from './seoSettings'
import { resolveSeoTitle, titleLimits } from './snippet'

const exampleTitle = 'Забор из профнастила'
const exampleH1 = 'Установка заборов из профнастила'

export function SeoTitleSettingsCard() {
  const settingsQuery = useSettingsQuery(seoSettingsScope)
  const upsert = useUpsertSettingMutation()
  const { push } = useToast()
  const saved = readSeoTitleSettings(settingsQuery.data)
  const [templateOverride, setTemplateOverride] = useState<string | null>(null)
  const [siteNameOverride, setSiteNameOverride] = useState<string | null>(null)

  const template = templateOverride ?? saved.titleTemplate
  const siteName = siteNameOverride ?? saved.siteName
  const dirty = template !== saved.titleTemplate || siteName !== saved.siteName
  const example = resolveSeoTitle({ metaTitle: '', title: exampleTitle, h1: exampleH1, template, siteName })

  const save = async (): Promise<void> => {
    try {
      await upsert.mutateAsync({
        scope: seoSettingsScope,
        key: titleTemplateKey,
        value: template.trim(),
        description: 'Шаблон SEO-title по умолчанию: {title}, {h1}, {site_name}.',
      })
      await upsert.mutateAsync({
        scope: seoSettingsScope,
        key: siteNameKey,
        value: siteName.trim(),
        description: 'Название сайта для подстановки {site_name} в шаблон SEO-title.',
      })
      setTemplateOverride(null)
      setSiteNameOverride(null)
      push({ title: 'Шаблон SEO-title сохранён', description: 'Публичные страницы обновятся автоматически.' })
    } catch {
      push({ title: 'Не удалось сохранить шаблон', description: 'Проверьте доступ settings.edit и повторите попытку.' })
    }
  }

  return (
    <Card
      className="mt-4"
      title="Шаблон SEO-title по умолчанию"
      description="Применяется к страницам без собственного SEO-title. Явно заданный SEO-title страницы шаблон не меняет."
    >
      <div className="space-y-3">
        <label className="block text-sm font-medium">
          Шаблон
          <Input
            className="mt-1"
            value={template}
            placeholder="{h1} — заборы в Москве | {site_name}"
            onChange={(event) => setTemplateOverride(event.target.value)}
          />
          <span className="mt-1 block text-xs font-normal text-graphite dark:text-slate-400">
            Доступные подстановки: <code>{'{title}'}</code>, <code>{'{h1}'}</code>, <code>{'{site_name}'}</code>. Пустой шаблон — в title попадает название страницы.
          </span>
        </label>
        <label className="block text-sm font-medium">
          Название сайта
          <Input className="mt-1" value={siteName} onChange={(event) => setSiteNameOverride(event.target.value)} />
        </label>
        <div className="rounded-lg border border-line p-3 text-sm dark:border-slate-700">
          <div className="text-xs text-graphite dark:text-slate-400">
            Пример для страницы «{exampleTitle}» с H1 «{exampleH1}»:
          </div>
          <div className="mt-1 font-medium" data-testid="seo-template-example">{example}</div>
          <LengthCounter value={example} limits={titleLimits} />
        </div>
        <Button type="button" onClick={() => { void save() }} disabled={upsert.isPending || !dirty}>
          {upsert.isPending ? 'Сохранение...' : 'Сохранить'}
        </Button>
      </div>
    </Card>
  )
}
