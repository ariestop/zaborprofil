import { useState } from 'react'
import { useToast } from '../../app/providers/toast-provider'
import { useBulkPagesMutation, type BulkPagesPayload, type BulkPagesResponse, type PageStatus } from '../../entities/page/api'
import type { ContentPageItem } from '../../types/api'
import { Button } from '../../shared/ui'
import { describeApiError } from '../seo/redirects/redirect-rules'
import { NativeSelect } from './fields'
import { pageStatusLabels } from './page-status'

/** Публикация и планирование доступны только поштучно: перед публикацией нужна проверка SEO. */
export const BULK_STATUSES: PageStatus[] = ['draft', 'review', 'approved', 'unpublished', 'archived']

interface PagesBulkBarProps {
  pages: ContentPageItem[]
  selectedIds: string[]
  onClear: () => void
}

function failureDetails(pages: ContentPageItem[], response: BulkPagesResponse): string {
  return response.results
    .filter((result) => !result.ok)
    .slice(0, 3)
    .map((result) => `${pages.find((page) => page.id === result.id)?.title ?? result.id}: ${result.error ?? 'ошибка'}`)
    .join('; ')
}

export function PagesBulkBar({ pages, selectedIds, onClear }: PagesBulkBarProps) {
  const { push } = useToast()
  const bulk = useBulkPagesMutation()
  const [status, setStatus] = useState<PageStatus>('draft')

  const run = (payload: BulkPagesPayload, label: string): void => {
    bulk.mutate(payload, {
      onSuccess: (response) => {
        const failed = response.failed > 0
        push({
          title: failed ? `${label}: выполнено ${response.succeeded}, не выполнено ${response.failed}` : `${label}: ${response.succeeded}`,
          description: failed ? failureDetails(pages, response) : undefined,
        })
        onClear()
      },
      onError: (error) => {
        push({ title: 'Не удалось выполнить действие', description: describeApiError(error, 'Проверьте права доступа и повторите попытку.') })
      },
    })
  }

  return (
    <div
      role="region"
      aria-label="Массовые действия"
      className="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900 dark:bg-emerald-950/30"
    >
      <p className="text-sm font-medium" data-testid="bulk-count">Выбрано страниц: {selectedIds.length}</p>
      <label className="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200">
        Статус
        <NativeSelect value={status} onChange={(event) => setStatus(event.target.value as PageStatus)}>
          {BULK_STATUSES.map((value) => <option key={value} value={value}>{pageStatusLabels[value]}</option>)}
        </NativeSelect>
      </label>
      <Button
        type="button"
        size="sm"
        disabled={bulk.isPending}
        onClick={() => run({ ids: selectedIds, action: 'status', status }, 'Статус изменён')}
      >
        Сменить статус
      </Button>
      <Button
        type="button"
        size="sm"
        variant="outline"
        disabled={bulk.isPending}
        onClick={() => run({ ids: selectedIds, action: 'indexable', indexable: false }, 'Закрыто от индексации')}
      >
        Закрыть от индексации
      </Button>
      <Button
        type="button"
        size="sm"
        variant="outline"
        disabled={bulk.isPending}
        onClick={() => run({ ids: selectedIds, action: 'indexable', indexable: true }, 'Открыто для индексации')}
      >
        Открыть для индексации
      </Button>
      <Button type="button" size="sm" variant="ghost" onClick={onClear}>Снять выделение</Button>
    </div>
  )
}
