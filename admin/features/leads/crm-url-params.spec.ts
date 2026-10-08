import { describe, expect, it } from 'vitest'
import {
    CRM_PAGE_SIZE,
    readLeadListParams,
    statusMatchesTab,
    withSearch,
    writeLeadListParams,
} from './crm-url-params'

describe('crm url params', () => {
    it('reads defaults from an empty address', () => {
        expect(readLeadListParams(new URLSearchParams())).toEqual({
            q: '',
            status: 'all',
            source: '',
            from: '',
            to: '',
            assignee: 'all',
            b2b: false,
            waitingHours: 0,
            sort: 'createdAt',
            direction: 'desc',
            page: 1,
            perPage: CRM_PAGE_SIZE,
        })
    })

    it('ignores unknown status, sort and broken numbers', () => {
        const params = readLeadListParams(
            new URLSearchParams('status=archived&sort=phone&page=-3&waiting=abc&direction=up'),
        )

        expect(params.status).toBe('all')
        expect(params.sort).toBe('createdAt')
        expect(params.page).toBe(1)
        expect(params.waitingHours).toBe(0)
        expect(params.direction).toBe('desc')
    })

    it('writes only non-default values and reads them back', () => {
        const params = {
            ...readLeadListParams(new URLSearchParams()),
            status: 'in_progress' as const,
            b2b: true,
            waitingHours: 2,
            sort: 'name' as const,
            direction: 'asc' as const,
            page: 3,
        }
        const search = writeLeadListParams(params)

        expect(search.toString()).toBe(
            'status=in_progress&b2b=1&waiting=2&sort=name&direction=asc&page=3',
        )
        expect(readLeadListParams(search)).toEqual(params)
        expect(writeLeadListParams(readLeadListParams(new URLSearchParams())).toString()).toBe('')
    })

    it('appends the query only when there is one', () => {
        expect(withSearch('/admin/crm', new URLSearchParams())).toBe('/admin/crm')
        expect(withSearch('/admin/crm/1', new URLSearchParams('status=new'))).toBe(
            '/admin/crm/1?status=new',
        )
    })

    it('hides spam from the "all" tab', () => {
        expect(statusMatchesTab('all', 'new')).toBe(true)
        expect(statusMatchesTab('all', 'spam')).toBe(false)
        expect(statusMatchesTab('done', 'done')).toBe(true)
        expect(statusMatchesTab('done', 'new')).toBe(false)
    })
})
