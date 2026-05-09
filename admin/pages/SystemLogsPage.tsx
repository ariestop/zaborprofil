import { useState } from 'react'
import { useSystemLogsQuery } from '../entities/system/api'
import { Button, Card, ErrorState, Input, PageHeader, PageLoadingState } from '../shared/ui'

export default function SystemLogsPage() {
  const [channel, setChannel] = useState('')
  const [submittedChannel, setSubmittedChannel] = useState('')
  const logsQuery = useSystemLogsQuery(submittedChannel)

  if (logsQuery.isPending) {
    return <PageLoadingState />
  }

  if (logsQuery.isError || logsQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить логи"
        description="Проверьте endpoint /admin/api/system/logs и права system.view."
      />
    )
  }

  return (
    <div>
      <PageHeader title="Логи" description="Просмотр последних записей из выбранного канала." />
      <Card title="Фильтр канала">
        <div className="flex gap-2">
          <Input value={channel} onChange={(event) => setChannel(event.target.value)} placeholder="app, admin, security..." />
          <Button onClick={() => setSubmittedChannel(channel)}>Применить</Button>
        </div>
      </Card>
      <Card className="mt-4" title={logsQuery.data.path}>
        <pre className="max-h-[520px] overflow-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-100">
          {logsQuery.data.content || 'Лог пуст.'}
        </pre>
      </Card>
    </div>
  )
}
