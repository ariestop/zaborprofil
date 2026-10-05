import { useState } from 'react'
import { useToast } from '../../app/providers/toast-provider'
import { useBulkPagesMutation, type BulkPagesPayload, type BulkPagesResponse, type PageStatus } from '../../entities/page/api'
import type { ContentPageItem } from '../../types/api'
import { describeApiError } from '../seo/redirects/redirect-rules'
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

  const darkButton = 'h-9 rounded-lg bg-graphite px-3 text-[13px] font-semibold text-white hover:bg-graphite disabled:opacity-60'

  return (
    <div
      role="region"
      aria-label="Массовые действия"
      className="flex flex-wrap items-center gap-2.5 rounded-xl bg-ink py-2.5 pl-4 pr-3 text-white dark:bg-slate-800"
    >
      <p className="font-semibold" data-testid="bulk-count">Выбрано страниц: {selectedIds.length}</p>
      <span className="hidden flex-1 sm:block" />
      <label className="flex items-center gap-2 text-[13px] font-semibold text-line-strong">
        Статус
        <select
          value={status}
          onChange={(event) => setStatus(event.target.value as PageStatus)}
          className="h-9 rounded-lg border-0 bg-graphite px-2.5 text-[13px] font-semibold text-white outline-hidden focus:ring-2 focus:ring-brand-400"
        >
          {BULK_STATUSES.map((value) => <option key={value} value={value}>{pageStatusLabels[value]}</option>)}
        </select>
      </label>
      <button type="button" className={darkButton} disabled={bulk.isPending} onClick={() => run({ ids: selectedIds, action: 'status', status }, 'Статус изменён')}>
        Сменить статус
      </button>
      <button type="button" className={darkButton} disabled={bulk.isPending} onClick={() => run({ ids: selectedIds, action: 'indexable', indexable: false }, 'Закрыто от индексации')}>
        Закрыть от индексации
      </button>
      <button type="button" className={darkButton} disabled={bulk.isPending} onClick={() => run({ ids: selectedIds, action: 'indexable', indexable: true }, 'Открыто для индексации')}>
        Открыть для индексации
      </button>
      <button type="button" onClick={onClear} className="h-9 rounded-lg px-3 text-[13px] font-semibold text-line-strong hover:text-white">Снять выделение</button>
    </div>
  )
}
