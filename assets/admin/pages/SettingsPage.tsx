import { PageHeader, Card, Switch } from '../shared/ui'
import { useToast } from '../app/providers/toast-provider'
import { useState } from 'react'

export default function SettingsPage() {
  const [maintenanceDraft, setMaintenanceDraft] = useState(false)
  const { push } = useToast()

  return (
    <div>
      <PageHeader title="Settings" description="Foundation для системных настроек, миграций и feature flags." />
      <Card title="Maintenance draft toggle">
        <div className="flex items-center gap-3">
          <Switch checked={maintenanceDraft} onCheckedChange={setMaintenanceDraft} />
          <button
            type="button"
            className="text-sm text-emerald-700 hover:underline dark:text-emerald-400"
            onClick={() => push({
              title: 'Черновик обновлён',
              description: maintenanceDraft ? 'Maintenance mode будет включен после сохранения.' : 'Maintenance mode будет выключен после сохранения.',
            })}
          >
            Показать toast
          </button>
        </div>
      </Card>
    </div>
  )
}
