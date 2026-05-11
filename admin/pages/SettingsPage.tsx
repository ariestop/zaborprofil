import { useEffect, useState } from 'react'
import { useSettingsQuery, useUpsertSettingMutation } from '../entities/settings/api'
import { PageHeader, Card, Button, ErrorState, PageLoadingState, Select } from '../shared/ui'
import { useToast } from '../app/providers/toast-provider'
import { Link } from 'react-router-dom'

type PublicBlocksSource = 'snapshot' | 'live'

const contentScope = 'content'
const publicBlocksSourceKey = 'public_page_blocks_source'

export default function SettingsPage() {
  const settingsQuery = useSettingsQuery(contentScope)
  const upsertSetting = useUpsertSettingMutation()
  const { push } = useToast()
  const [publicBlocksSourceDraft, setPublicBlocksSourceDraft] = useState<PublicBlocksSource>('snapshot')

  const currentPublicBlocksSource: PublicBlocksSource = (() => {
    const item = settingsQuery.data?.find((setting) => setting.key === publicBlocksSourceKey)
    return item?.value === 'live' ? 'live' : 'snapshot'
  })()

  useEffect(() => {
    if (settingsQuery.data === undefined) {
      return
    }

    setPublicBlocksSourceDraft(currentPublicBlocksSource)
  }, [currentPublicBlocksSource, settingsQuery.data])

  const savePublicBlocksSource = async (): Promise<void> => {
    try {
      await upsertSetting.mutateAsync({
        scope: contentScope,
        key: publicBlocksSourceKey,
        value: publicBlocksSourceDraft,
        description: 'Источник блоков для публичного рендера: snapshot ревизии или live блоки.',
      })
      push({
        title: 'Настройка сохранена',
        description: publicBlocksSourceDraft === 'live'
          ? 'Публичный фронт использует live блоки.'
          : 'Публичный фронт использует snapshot ревизии.',
      })
    } catch {
      push({
        title: 'Не удалось сохранить настройку',
        description: 'Проверьте доступ settings.edit и повторите попытку.',
      })
    }
  }

  if (settingsQuery.isPending) {
    return <PageLoadingState />
  }

  if (settingsQuery.isError || settingsQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить настройки"
        description="Проверьте endpoint /admin/api/settings и права settings.edit."
      />
    )
  }

  return (
    <div>
      <PageHeader title="Settings" description="Foundation для системных настроек, миграций и feature flags." />
      <Card title="Публичный рендер блоков">
        <div className="space-y-3">
          <p className="text-sm text-slate-600 dark:text-slate-300">
            Выберите источник блоков для публичных страниц:
            <span className="font-semibold"> snapshot ревизии</span> или <span className="font-semibold">live блоки</span>.
          </p>
          <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
            <p>
              <span className="font-semibold">Рекомендуется для production:</span> <code>snapshot</code>, так как это
              стабильный и предсказуемый рендер опубликованной ревизии.
            </p>
            <p className="mt-1">
              <span className="font-semibold">Режим</span> <code>live</code> удобен для оперативных правок и предпросмотра,
              но отображает текущие builder-блоки без новой публикации ревизии.
            </p>
          </div>
          <Select
            value={publicBlocksSourceDraft}
            onValueChange={(value) => setPublicBlocksSourceDraft(value as PublicBlocksSource)}
            options={[
              { value: 'snapshot', label: 'Snapshot ревизии (по умолчанию)' },
              { value: 'live', label: 'Live блоки из builder' },
            ]}
          />
          <div className="flex items-center gap-3">
            <Button
              type="button"
              onClick={() => { void savePublicBlocksSource() }}
              disabled={upsertSetting.isPending || publicBlocksSourceDraft === currentPublicBlocksSource}
            >
              {upsertSetting.isPending ? 'Сохранение...' : 'Сохранить'}
            </Button>
            <span className="text-xs text-slate-500 dark:text-slate-400">
              Текущее значение: {currentPublicBlocksSource === 'live' ? 'live' : 'snapshot'}
            </span>
          </div>
        </div>
      </Card>
      <Card className="mt-4" title="Системные инструменты">
        <div className="text-sm">
          <Link
            to="/admin/settings/migrations"
            className="text-emerald-700 hover:underline dark:text-emerald-400"
          >
            Открыть Миграции
          </Link>
        </div>
      </Card>
    </div>
  )
}
