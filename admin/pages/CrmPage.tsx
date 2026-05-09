import { createColumnHelper } from '@tanstack/react-table'
import { PageHeader, Card, DataTable, ErrorState, PageLoadingState, Select } from '../shared/ui'
import { useLeadStatusMutation, useLeadsQuery } from '../entities/lead/api'
import type { LeadItem } from '../types/api'

export default function CrmPage() {
  const leadsQuery = useLeadsQuery()
  const leadStatusMutation = useLeadStatusMutation()
  const columnHelper = createColumnHelper<LeadItem>()

  const columns = [
    columnHelper.accessor('name', { header: 'Имя' }),
    columnHelper.accessor('phone', { header: 'Телефон' }),
    columnHelper.accessor('source', { header: 'Источник' }),
    columnHelper.accessor('status', {
      header: 'Статус',
      cell: ({ row, getValue }) => (
        <Select
          value={getValue()}
          onValueChange={(nextStatus) => {
            void leadStatusMutation.mutateAsync({
              leadId: row.original.id,
              status: nextStatus as LeadItem['status'],
            })
          }}
          options={(leadsQuery.data?.statuses ?? []).map((status) => ({ value: status, label: status }))}
        />
      ),
    }),
    columnHelper.accessor('createdAt', { header: 'Создана' }),
  ]

  if (leadsQuery.isPending) {
    return <PageLoadingState />
  }

  if (leadsQuery.isError || leadsQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить CRM-данные"
        description="Проверьте endpoint /admin/api/leads и права leads.view."
      />
    )
  }

  return (
    <div>
      <PageHeader title="CRM" description="Лиды и статусы через data-grid + mutation flow." />
      <Card title="Leads">
        <DataTable columns={columns} data={leadsQuery.data.leads} />
      </Card>
    </div>
  )
}
