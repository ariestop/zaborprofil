import type { MediaFormatFilter, MediaUsageFilter } from '../../entities/media/api'
import { MEDIA_FOLDER_NONE } from '../../entities/media/api'
import { Button, Input } from '../../shared/ui'
import type { MediaFolderItem } from '../../types/api'
import { FORMAT_OPTIONS, SELECT_CLASS, USAGE_OPTIONS } from './mediaLibraryOptions'
import type { MediaLibraryFilters } from './useMediaLibraryFilters'

/** Расширенные фильтры медиатеки: формат, использование, папка, даты загрузки. */
export function MediaFilterRow({
    filters,
    folders,
    imagesOnly,
}: {
    filters: MediaLibraryFilters
    folders: MediaFolderItem[]
    imagesOnly: boolean
}) {
    return (
        <>
            <div
                className="flex flex-wrap items-center gap-2"
                aria-label="Фильтры медиатеки"
                role="group"
            >
                <select
                    aria-label="Формат"
                    className={SELECT_CLASS}
                    value={filters.formatFilter}
                    onChange={(event) =>
                        filters.setFormatFilter(event.target.value as MediaFormatFilter)
                    }
                >
                    {FORMAT_OPTIONS.filter((option) => !imagesOnly || option.value !== 'pdf').map(
                        (option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ),
                    )}
                </select>
                <select
                    aria-label="Использование"
                    className={SELECT_CLASS}
                    value={filters.usageFilter}
                    onChange={(event) =>
                        filters.setUsageFilter(event.target.value as MediaUsageFilter)
                    }
                >
                    {USAGE_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <select
                    aria-label="Папка"
                    className={SELECT_CLASS}
                    value={filters.folderFilter}
                    onChange={(event) => filters.setFolderFilter(event.target.value)}
                >
                    <option value="">Все папки</option>
                    <option value={MEDIA_FOLDER_NONE}>Без папки</option>
                    {folders.map((folder) => (
                        <option
                            key={folder.name}
                            value={folder.name}
                        >{`${folder.name} (${folder.count})`}</option>
                    ))}
                </select>
                <label className="flex items-center gap-1 text-xs text-graphite dark:text-slate-300">
                    Загружены с
                    <Input
                        type="date"
                        aria-label="Загружены с"
                        className="h-12 w-40"
                        value={filters.dateFrom}
                        max={filters.dateTo || undefined}
                        onChange={(event) => filters.setDateFrom(event.target.value)}
                    />
                </label>
                <label className="flex items-center gap-1 text-xs text-graphite dark:text-slate-300">
                    по
                    <Input
                        type="date"
                        aria-label="Загружены по"
                        className="h-12 w-40"
                        value={filters.dateTo}
                        min={filters.dateFrom || undefined}
                        onChange={(event) => filters.setDateTo(event.target.value)}
                    />
                </label>
                {filters.filtersActive ? (
                    <Button type="button" size="sm" variant="ghost" onClick={filters.resetFilters}>
                        Сбросить фильтры
                    </Button>
                ) : null}
            </div>
            {filters.invalidRange ? (
                <p className="text-xs text-red-700 dark:text-red-400" role="alert">
                    Дата «с» не может быть позже даты «по».
                </p>
            ) : null}
        </>
    )
}
