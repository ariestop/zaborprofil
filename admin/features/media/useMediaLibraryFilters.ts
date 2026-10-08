import { useState } from 'react'
import type {
    MediaFormatFilter,
    MediaListParams,
    MediaSort,
    MediaTypeFilter,
    MediaUsageFilter,
} from '../../entities/media/api'
import { useDebouncedValue } from '../../shared/hooks/use-debounced-value'

export interface MediaLibraryFilters {
    searchInput: string
    page: number
    pageSize: number
    typeFilter: MediaTypeFilter
    sort: MediaSort
    formatFilter: MediaFormatFilter
    usageFilter: MediaUsageFilter
    folderFilter: string
    dateFrom: string
    dateTo: string
    /** Дата «с» позже даты «по»: диапазон не отправляется на сервер. */
    invalidRange: boolean
    /** Задан поиск или фильтр, кроме сортировки (и типа, если медиатека показывает только изображения). */
    filtersActive: boolean
    params: MediaListParams
    setSearch: (value: string) => void
    setPage: (page: number) => void
    setPageSize: (value: number) => void
    setTypeFilter: (value: MediaTypeFilter) => void
    setSort: (value: MediaSort) => void
    setFormatFilter: (value: MediaFormatFilter) => void
    setUsageFilter: (value: MediaUsageFilter) => void
    setFolderFilter: (value: string) => void
    setDateFrom: (value: string) => void
    setDateTo: (value: string) => void
    resetFilters: () => void
}

/**
 * Поиск, фильтры и пагинация медиатеки. Любое изменение поиска или фильтра возвращает на первую страницу,
 * поиск уходит на сервер с задержкой 300 мс.
 */
export function useMediaLibraryFilters(imagesOnly: boolean, perPage: number): MediaLibraryFilters {
    const [searchInput, setSearchInput] = useState('')
    const search = useDebouncedValue(searchInput, 300)
    const [page, setPage] = useState(1)
    const [typeFilter, setTypeFilter] = useState<MediaTypeFilter>(imagesOnly ? 'image' : '')
    const [sort, setSort] = useState<MediaSort>('newest')
    const [formatFilter, setFormatFilter] = useState<MediaFormatFilter>('')
    const [usageFilter, setUsageFilter] = useState<MediaUsageFilter>('')
    const [folderFilter, setFolderFilter] = useState('')
    const [dateFrom, setDateFrom] = useState('')
    const [dateTo, setDateTo] = useState('')
    const [pageSize, setPageSize] = useState(perPage)

    const invalidRange = dateFrom !== '' && dateTo !== '' && dateFrom > dateTo
    const params: MediaListParams = {
        page,
        perPage: pageSize,
        q: search.trim(),
        type: typeFilter,
        sort,
        folder: folderFilter,
        format: formatFilter,
        usage: usageFilter,
        from: invalidRange ? '' : dateFrom,
        to: invalidRange ? '' : dateTo,
    }
    const filtersActive =
        search.trim() !== '' ||
        (!imagesOnly && typeFilter !== '') ||
        formatFilter !== '' ||
        usageFilter !== '' ||
        folderFilter !== '' ||
        dateFrom !== '' ||
        dateTo !== ''

    const resetPage =
        <T>(setter: (value: T) => void) =>
        (value: T) => {
            setter(value)
            setPage(1)
        }

    const resetFilters = (): void => {
        setSearchInput('')
        setTypeFilter(imagesOnly ? 'image' : '')
        setFormatFilter('')
        setUsageFilter('')
        setFolderFilter('')
        setDateFrom('')
        setDateTo('')
        setPage(1)
    }

    return {
        searchInput,
        page,
        pageSize,
        typeFilter,
        sort,
        formatFilter,
        usageFilter,
        folderFilter,
        dateFrom,
        dateTo,
        invalidRange,
        filtersActive,
        params,
        setSearch: resetPage(setSearchInput),
        setPage,
        setPageSize: resetPage(setPageSize),
        setTypeFilter: resetPage(setTypeFilter),
        setSort: resetPage(setSort),
        setFormatFilter: resetPage(setFormatFilter),
        setUsageFilter: resetPage(setUsageFilter),
        setFolderFilter: resetPage(setFolderFilter),
        setDateFrom: resetPage(setDateFrom),
        setDateTo: resetPage(setDateTo),
        resetFilters,
    }
}
