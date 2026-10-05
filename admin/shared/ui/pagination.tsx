import { Button } from './button'

interface PaginationProps {
  page: number
  pages: number
  total: number
  onPageChange: (page: number) => void
}

export function Pagination({ page, pages, total, onPageChange }: PaginationProps) {
  return (
    <div className="flex items-center justify-between">
      <p className="text-xs text-graphite dark:text-slate-400">
        Страница {page} из {pages} · всего {total}
      </p>
      <div className="flex items-center gap-2">
        <Button type="button" size="sm" variant="outline" disabled={page <= 1} onClick={() => onPageChange(page - 1)}>
          Назад
        </Button>
        <Button type="button" size="sm" variant="outline" disabled={page >= pages} onClick={() => onPageChange(page + 1)}>
          Далее
        </Button>
      </div>
    </div>
  )
}
