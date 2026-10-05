import { afterEach, describe, expect, it } from 'vitest'
import {
    initCallbar,
    initCopyButtons,
    initGalleries,
    initLeadPlans,
    initMobileNav,
    initOfficeStatus,
    initVideoEmbeds,
    isAllowedEmbedUrl,
    isAllowedMapUrl,
    officeStatus,
    type WorkingHoursRow,
} from './ui'

function mount(html: string): HTMLElement {
    const root = document.createElement('div')
    root.innerHTML = html
    document.body.append(root)

    return root
}

afterEach(() => {
    document.body.innerHTML = ''
    document.documentElement.className = ''
})

describe('initMobileNav', () => {
    const markup = `
    <button data-nav-toggle aria-expanded="false"><svg data-icon-open></svg><svg data-icon-close class="hidden"></svg></button>
    <div data-nav-panel hidden><a href="/zabor-jaluzi/">Жалюзи</a></div>`

    it('открывает и закрывает меню, блокируя прокрутку страницы', () => {
        const root = mount(markup)
        initMobileNav(root)
        const toggle = root.querySelector<HTMLButtonElement>('[data-nav-toggle]')!
        const panel = root.querySelector<HTMLElement>('[data-nav-panel]')!

        toggle.click()
        expect(panel.hidden).toBe(false)
        expect(toggle.getAttribute('aria-expanded')).toBe('true')
        expect(document.documentElement.classList.contains('overflow-hidden')).toBe(true)

        toggle.click()
        expect(panel.hidden).toBe(true)
        expect(document.documentElement.classList.contains('overflow-hidden')).toBe(false)
    })

    it('закрывается по Escape и по клику на ссылку', () => {
        const root = mount(markup)
        initMobileNav(root)
        const toggle = root.querySelector<HTMLButtonElement>('[data-nav-toggle]')!
        const panel = root.querySelector<HTMLElement>('[data-nav-panel]')!

        toggle.click()
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
        expect(panel.hidden).toBe(true)

        toggle.click()
        root.querySelector<HTMLAnchorElement>('[data-nav-panel] a')!.click()
        expect(panel.hidden).toBe(true)
    })

    it('не падает на странице без меню', () => {
        expect(() => initMobileNav(mount('<p>без меню</p>'))).not.toThrow()
    })
})

describe('initGalleries', () => {
    it('скрывает лишние фото и раскрывает их по кнопке', () => {
        const root = mount(`
      <section data-gallery>
        <figure><a href="/1.jpg" data-lightbox><img alt="1"></a></figure>
        <figure data-gallery-extra><a href="/2.jpg" data-lightbox><img alt="2"></a></figure>
        <div data-gallery-more hidden><button data-gallery-toggle>Показать</button></div>
      </section>`)
        initGalleries(root)
        const extra = root.querySelector<HTMLElement>('[data-gallery-extra]')!
        const more = root.querySelector<HTMLElement>('[data-gallery-more]')!

        expect(extra.hidden).toBe(true)
        expect(more.hidden).toBe(false)

        root.querySelector<HTMLButtonElement>('[data-gallery-toggle]')!.click()
        expect(extra.hidden).toBe(false)
        expect(more.hidden).toBe(true)
    })
})

describe('isAllowedEmbedUrl', () => {
    it('разрешает только https-плееры известных видеосервисов', () => {
        expect(isAllowedEmbedUrl('https://rutube.ru/play/embed/abc/')).toBe(true)
        expect(isAllowedEmbedUrl('https://vkvideo.ru/video_ext.php?oid=1&id=2')).toBe(true)
        expect(isAllowedEmbedUrl('https://www.youtube.com/embed/abc')).toBe(true)
        expect(isAllowedEmbedUrl('http://rutube.ru/play/embed/abc/')).toBe(false)
        expect(isAllowedEmbedUrl('https://evil.example/rutube.ru')).toBe(false)
        expect(isAllowedEmbedUrl('https://notrutube.ru/x')).toBe(false)
        expect(isAllowedEmbedUrl('javascript:alert(1)')).toBe(false)
    })
})

describe('initVideoEmbeds', () => {
    it('подставляет плеер только по нажатию и только для разрешённых адресов', () => {
        const root = mount(`
      <button data-video-embed="https://rutube.ru/play/embed/abc/" data-video-title="Видео"></button>
      <button id="bad" data-video-embed="https://evil.example/x"></button>`)
        initVideoEmbeds(root)

        expect(root.querySelector('iframe')).toBeNull()
        root.querySelector<HTMLButtonElement>('[data-video-embed]')!.click()
        expect(root.querySelector('iframe')?.getAttribute('src')).toBe(
            'https://rutube.ru/play/embed/abc/',
        )

        root.querySelector<HTMLButtonElement>('#bad')!.click()
        expect(root.querySelectorAll('iframe')).toHaveLength(1)
    })
})

describe('initLeadPlans', () => {
    it('переносит выбранный вариант в сообщение формы', () => {
        const root = mount(`
      <a href="#lead-form" data-lead-plan="Largo Премиум">Рассчитать</a>
      <section id="lead-form"><textarea name="message"></textarea></section>`)
        initLeadPlans(root)

        root.querySelector<HTMLAnchorElement>('a')!.click()
        expect(root.querySelector<HTMLTextAreaElement>('textarea')!.value).toBe(
            'Интересует: Largo Премиум',
        )
    })
})

describe('initCallbar', () => {
    it('не падает без IntersectionObserver и без панели', () => {
        expect(() => initCallbar(mount('<p></p>'))).not.toThrow()
        expect(() =>
            initCallbar(mount('<div data-callbar></div><section class="zp-hero"></section>')),
        ).not.toThrow()
    })
})

describe('officeStatus', () => {
    const week: WorkingHoursRow[] = [
        { days: [1, 2, 3, 4, 5], open: '09:00', close: '18:00' },
        { days: [6], open: '10:00', close: '14:00' },
        { days: [0], open: '', close: '' },
    ]
    // 2026-10-05 — понедельник. Саратов (UTC+4): 09:00 по Москве+1 = 05:00 UTC.
    const at = (iso: string): Date => new Date(iso)

    it('показывает «открыто» в рабочее время по местному времени офиса', () => {
        const status = officeStatus(week, at('2026-10-05T07:00:00Z'), 'Europe/Saratov')

        expect(status).toMatchObject({
            state: 'open',
            text: 'Сейчас открыто · до 18:00',
            weekday: 1,
        })
    })

    it('учитывает часовой пояс: в Оренбурге (UTC+5) то же время UTC уже вечер', () => {
        expect(officeStatus(week, at('2026-10-05T13:30:00Z'), 'Asia/Yekaterinburg')?.state).toBe(
            'closed',
        )
        expect(officeStatus(week, at('2026-10-05T13:30:00Z'), 'Europe/Saratov')?.state).toBe('open')
    })

    it('до открытия обещает сегодня, после закрытия — завтра', () => {
        expect(officeStatus(week, at('2026-10-05T03:00:00Z'), 'Europe/Saratov')?.text).toBe(
            'Закрыто · откроемся сегодня в 09:00',
        )
        expect(officeStatus(week, at('2026-10-05T15:00:00Z'), 'Europe/Saratov')?.text).toBe(
            'Закрыто · откроемся завтра в 09:00',
        )
    })

    it('в субботу после закрытия переносит на понедельник, в воскресенье тоже', () => {
        expect(officeStatus(week, at('2026-10-10T11:00:00Z'), 'Europe/Saratov')?.text).toBe(
            'Закрыто · откроемся в понедельник в 09:00',
        )
        expect(officeStatus(week, at('2026-10-11T08:00:00Z'), 'Europe/Saratov')?.text).toBe(
            'Закрыто · откроемся завтра в 09:00',
        )
    })

    it('без расписания статуса нет', () => {
        expect(officeStatus([], new Date(), 'Europe/Saratov')).toBeNull()
    })
})

describe('initOfficeStatus и копирование', () => {
    it('подсвечивает сегодняшний день и показывает чип', () => {
        const root = mount(`
      <article data-office data-timezone="Europe/Saratov">
        <span data-office-status hidden></span>
        <div data-days="1,2,3,4,5" data-open="09:00" data-close="18:00"></div>
        <div data-days="0" data-open="" data-close=""></div>
      </article>`)
        initOfficeStatus(root, new Date('2026-10-05T07:00:00Z'))

        const rows = root.querySelectorAll<HTMLElement>('[data-days]')
        expect(rows[0].dataset.today).toBe('true')
        expect(rows[1].dataset.today).toBe('false')
        const chip = root.querySelector<HTMLElement>('[data-office-status]')!
        expect(chip.hidden).toBe(false)
        expect(chip.dataset.state).toBe('open')
    })

    it('копирует значение и временно помечает кнопку', async () => {
        const written: string[] = []
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText: async (text: string) => written.push(text) },
            configurable: true,
        })
        const root = mount('<button data-copy="6452128198" aria-label="Скопировать ИНН"></button>')
        initCopyButtons(root)
        const button = root.querySelector<HTMLButtonElement>('button')!

        button.click()
        await Promise.resolve()
        await Promise.resolve()

        expect(written).toEqual(['6452128198'])
        expect(button.dataset.copied).toBe('true')
    })
})

describe('isAllowedMapUrl', () => {
    it('разрешает только https-виджеты 2ГИС', () => {
        expect(isAllowedMapUrl('https://widgets.2gis.com/widget?type=firmsonmap')).toBe(true)
        expect(isAllowedMapUrl('https://2gis.ru/saratov')).toBe(true)
        expect(isAllowedMapUrl('http://widgets.2gis.com/widget')).toBe(false)
        expect(isAllowedMapUrl('https://evil.example/2gis.com')).toBe(false)
    })
})
