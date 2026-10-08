import type { LeadListParams, LeadSortField } from '../../entities/lead/model'
import type { LeadStatus } from '../../types/api'

/**
 * Фильтры CRM живут в адресе страницы (`/admin/crm?status=new&b2b=1`), поэтому сохраняются при переходе
 * между заявками и по ссылке. Значения по умолчанию в адрес не пишутся.
 */
export const CRM_PAGE_SIZE = 25
export const LEAD_STATUSES: LeadStatus[] = ['new', 'in_progress', 'done', 'spam']
const SORTS: LeadSortField[] = ['createdAt', 'updatedAt', 'name', 'status', 'source']

export function isLeadStatus(value: string): value is LeadStatus {
    return LEAD_STATUSES.includes(value as LeadStatus)
}

export function readLeadListParams(search: URLSearchParams): LeadListParams {
    const status = search.get('status')
    const sort = search.get('sort')
    const waiting = Number.parseInt(search.get('waiting') ?? '', 10)

    return {
        q: '',
        status: status !== null && isLeadStatus(status) ? status : 'all',
        source: search.get('source') ?? '',
        from: search.get('from') ?? '',
        to: search.get('to') ?? '',
        assignee: search.get('assignee') ?? 'all',
        b2b: search.get('b2b') === '1',
        waitingHours: Number.isFinite(waiting) && waiting > 0 ? waiting : 0,
        sort: SORTS.includes(sort as LeadSortField) ? (sort as LeadSortField) : 'createdAt',
        direction: search.get('direction') === 'asc' ? 'asc' : 'desc',
        page: Math.max(1, Number.parseInt(search.get('page') ?? '1', 10) || 1),
        perPage: CRM_PAGE_SIZE,
    }
}

export function writeLeadListParams(params: LeadListParams): URLSearchParams {
    const search = new URLSearchParams()
    const entries: Array<[string, string, string]> = [
        ['status', params.status, 'all'],
        ['source', params.source, ''],
        ['from', params.from, ''],
        ['to', params.to, ''],
        ['assignee', params.assignee, 'all'],
        ['b2b', params.b2b ? '1' : '', ''],
        ['waiting', params.waitingHours > 0 ? String(params.waitingHours) : '', ''],
        ['sort', params.sort, 'createdAt'],
        ['direction', params.direction, 'desc'],
        ['page', String(params.page), '1'],
    ]
    for (const [key, value, fallback] of entries) {
        if (value !== fallback) {
            search.set(key, value)
        }
    }

    return search
}

export function withSearch(path: string, search: URLSearchParams): string {
    const query = search.toString()

    return query === '' ? path : `${path}?${query}`
}

export function statusMatchesTab(tab: LeadStatus | 'all', status: LeadStatus): boolean {
    return tab === 'all' ? status !== 'spam' : tab === status
}
