import {
  createPaginatedRowModel,
  rowPaginationFeature,
  tableFeatures,
  useTable,
  type ColumnDef,
  type PaginationState,
} from '@tanstack/react-table'
import { useState } from 'react'
import { Button } from './button'

/** Возможности таблиц админки: только постраничный вывод. Колонки создаются через createColumnHelper<DataTableFeatures, T>(). */
export const dataTableFeatures = tableFeatures({
  rowPaginationFeature,
  paginatedRowModel: createPaginatedRowModel(),
})

export type DataTableFeatures = typeof dataTableFeatures

interface DataTableProps<TData extends object> {
  columns: Array<ColumnDef<DataTableFeatures, TData, any>>
  data: TData[]
  pageSize?: number
}

export function DataTable<TData extends object>({ columns, data, pageSize = 10 }: DataTableProps<TData>) {
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize,
  })

  const table = useTable({
    features: dataTableFeatures,
    data,
    columns,
    state: {
      pagination,
    },
    onPaginationChange: setPagination,
  })

  return (
    <div className="space-y-3">
      <div className="overflow-hidden rounded-xl border border-line dark:border-slate-800">
        <table className="min-w-full divide-y divide-line dark:divide-slate-800">
          <thead className="bg-surface dark:bg-slate-900">
            {table.getHeaderGroups().map((headerGroup) => (
              <tr key={headerGroup.id}>
                {headerGroup.headers.map((header) => (
                  <th key={header.id} className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-graphite dark:text-slate-500">
                    {header.isPlaceholder ? null : <table.FlexRender header={header} />}
                  </th>
                ))}
              </tr>
            ))}
          </thead>
          <tbody className="divide-y divide-surface-strong bg-white dark:divide-slate-800 dark:bg-slate-950">
            {table.getRowModel().rows.map((row) => (
              <tr key={row.id}>
                {row.getAllCells().map((cell) => (
                  <td key={cell.id} className="px-3 py-2 text-sm text-ink dark:text-slate-200">
                    <table.FlexRender cell={cell} />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="flex items-center justify-between">
        <p className="text-xs text-graphite dark:text-slate-400">
          Страница {pagination.pageIndex + 1} из {table.getPageCount()}
        </p>
        <div className="flex items-center gap-2">
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => table.previousPage()}
            disabled={!table.getCanPreviousPage()}
          >
            Назад
          </Button>
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => table.nextPage()}
            disabled={!table.getCanNextPage()}
          >
            Далее
          </Button>
        </div>
      </div>
    </div>
  )
}
