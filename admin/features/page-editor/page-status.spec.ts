import { describe, expect, it } from 'vitest'
import { canPublishFrom, describePageStatusError, quickStatusActions, statusTone, visibilityHints, visibilityLabels } from './page-status'

describe('page status helpers', () => {
  it('allows quick publish from draft, but not from published', () => {
    expect(canPublishFrom('draft')).toBe(true)
    expect(canPublishFrom('approved')).toBe(true)
    expect(canPublishFrom('published')).toBe(false)
    expect(canPublishFrom('review')).toBe(false)
  })

  it('leaves workflow transitions for the publishing slot', () => {
    expect(quickStatusActions('draft')).toEqual(['archived'])
    expect(quickStatusActions('published')).toEqual(['unpublished', 'archived'])
  })

  it('resolves badge tone', () => {
    expect(statusTone('published')).toBe('success')
    expect(statusTone('draft')).toBe('warning')
    expect(statusTone('archived')).toBe('neutral')
  })
})

describe('visibility hints', () => {
  it('explains every visibility option shown in the settings tab', () => {
    expect(Object.keys(visibilityHints).sort()).toEqual(Object.keys(visibilityLabels).sort())
  })
})

describe('describePageStatusError', () => {
  it('переводит ошибки смены статуса на понятный язык', () => {
    expect(describePageStatusError('Page status cannot transition from "scheduled" to "archived".')).toBe(
      'нельзя перевести из «Запланировано» в «Архив»',
    )
    expect(describePageStatusError('Current role cannot set page status "published".')).toBe('нет прав на статус «Опубликовано»')
    expect(describePageStatusError('Page not found.')).toBe('Page not found.')
  })
})
