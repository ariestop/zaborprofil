import { Button } from '../../shared/ui'
import { cn } from '../../shared/lib/cn'
import type { MediaPagination as MediaPaginationData } from '../../types/api'
import { PER_PAGE_OPTIONS, SELECT_CLASS } from './mediaLibraryOptions'

export function MediaPagination({
    pagination,
    page,
    pageSize,
    perPage,
    onPageChange,
    onPageSizeChange,
}: {
    pagination: MediaPaginationData
    /** Запрошенная страница (может отличаться от `pagination.page`, пока ответ не пришёл). */
    page: number
    pageSize: number
    /** Размер страницы по умолчанию: всегда есть в списке вариантов. */
    perPage: number
    onPageChange: (page: number) => void
    onPageSizeChange: (size: number) => void
}) {
    return (
        <nav
            className="flex flex-wrap items-center justify-between gap-3"
            aria-label="Страницы медиатеки"
        >
            <span className="text-xs text-graphite dark:text-slate-500" data-testid="media-range">
                Показано {(pagination.page - 1) * pagination.perPage + 1}–
                {Math.min(pagination.page * pagination.perPage, pagination.total)} из{' '}
                {pagination.total}
            </span>
            <div className="flex flex-wrap items-center gap-2">
                <select
                    aria-label="Файлов на странице"
                    className={cn(SELECT_CLASS, 'h-8 px-2 text-xs')}
                    value={pageSize}
                    onChange={(event) => onPageSizeChange(Number(event.target.value))}
                >
                    {Array.from(new Set([...PER_PAGE_OPTIONS, perPage]))
                        .sort((left, right) => left - right)
                        .map((option) => (
                            <option key={option} value={option}>
                                {option} на странице
                            </option>
                        ))}
                </select>
                {pagination.totalPages > 1 ? (
                    <>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={page <= 1}
                            onClick={() => onPageChange(1)}
                        >
                            В начало
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={page <= 1}
                            onClick={() => onPageChange(page - 1)}
                        >
                            Назад
                        </Button>
                        <span className="text-sm" data-testid="media-page-indicator">
                            Страница {pagination.page} из {pagination.totalPages}
                        </span>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={page >= pagination.totalPages}
                            onClick={() => onPageChange(page + 1)}
                        >
                            Вперёд
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={page >= pagination.totalPages}
                            onClick={() => onPageChange(pagination.totalPages)}
                        >
                            В конец
                        </Button>
                    </>
                ) : null}
            </div>
        </nav>
    )
}
