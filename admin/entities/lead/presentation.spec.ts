import { describe, expect, it } from 'vitest'
import {
  describeSpam,
  detectPeriod,
  formatLeadListTime,
  formatLeadReceived,
  leadInitials,
  leadPagePath,
  leadUtmText,
  periodRange,
  spamScoreOutOfTen,
} from './presentation'

const now = new Date(2026, 9, 4, 18, 0)

describe('leadInitials', () => {
  it('takes initials of the first two words and skips legal forms', () => {
    expect(leadInitials('Андрей Смирнов')).toBe('АС')
    expect(leadInitials('ООО «СтройДвор»')).toBe('СД')
    expect(leadInitials('ИП Иванов Пётр')).toBe('ИП')
    expect(leadInitials('Ольга')).toBe('ОЛ')
    expect(leadInitials('+7 900')).toBe('?')
  })
})

describe('lead dates', () => {
  it('formats list time for today, yesterday and older leads', () => {
    expect(formatLeadListTime(new Date(2026, 9, 4, 15, 20).toISOString(), now)).toBe('15:20')
    expect(formatLeadListTime(new Date(2026, 9, 3, 17, 12).toISOString(), now)).toBe('Вчера')
    expect(formatLeadListTime(new Date(2026, 9, 2, 9, 15).toISOString(), now)).toBe('2 окт.')
    expect(formatLeadListTime('broken', now)).toBe('—')
  })

  it('formats the received date for the card', () => {
    expect(formatLeadReceived(new Date(2026, 9, 4, 15, 20).toISOString(), now)).toBe('Сегодня, 15:20')
    expect(formatLeadReceived(new Date(2026, 9, 3, 17, 12).toISOString(), now)).toBe('Вчера, 17:12')
    expect(formatLeadReceived(new Date(2026, 9, 2, 9, 15).toISOString(), now)).toBe('2 октября, 09:15')
  })
})

describe('lead context', () => {
  it('shortens the page url to a path and joins utm tags', () => {
    expect(leadPagePath('https://zaborprofil.ru/zabory/proflist?utm_source=yandex')).toBe('/zabory/proflist')
    expect(leadPagePath(null)).toBe('—')
    expect(leadUtmText({ medium: 'cpc', source: 'yandex', campaign: 'zabor' })).toBe('yandex / cpc / zabor')
    expect(leadUtmText({})).toBe('—')
  })
})

describe('anti-spam', () => {
  it('scales the server score to ten and describes the risk', () => {
    expect(spamScoreOutOfTen(0)).toBe(0)
    expect(spamScoreOutOfTen(60)).toBe(6)
    expect(spamScoreOutOfTen(290)).toBe(10)
    expect(describeSpam(0, []).title).toBe('Чисто')
    expect(describeSpam(50, []).title).toBe('Низкий риск')
    expect(describeSpam(100, ['honeypot_filled'])).toMatchObject({ title: 'Высокий риск спама', text: 'Спам-балл 10 из 10: honeypot_filled', percent: 100 })
  })
})

describe('periods', () => {
  it('builds ranges and detects presets back from dates', () => {
    expect(periodRange('today', now)).toEqual({ from: '2026-10-04', to: '2026-10-04' })
    expect(periodRange('7d', now)).toEqual({ from: '2026-09-28', to: '2026-10-04' })
    expect(detectPeriod('2026-10-04', '2026-10-04', now)).toBe('today')
    expect(detectPeriod('', '', now)).toBe('all')
    expect(detectPeriod('2026-09-01', '', now)).toBe('custom')
  })
})
