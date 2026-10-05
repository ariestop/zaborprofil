/**
 * Лёгкие интерактивные элементы публичного сайта без зависимостей:
 * мобильное меню, панель связи, галерея с просмотром фото, видео по нажатию и выбор варианта в форме.
 */
import { applyLeadPlan } from './leadForm'

const DESKTOP_NAV_QUERY = '(min-width: 1024px)'

export function initMobileNav(root: ParentNode = document): void {
    const toggle = root.querySelector<HTMLButtonElement>('[data-nav-toggle]')
    const panel = root.querySelector<HTMLElement>('[data-nav-panel]')
    if (toggle === null || panel === null) {
        return
    }

    const iconOpen = toggle.querySelector<SVGElement>('[data-icon-open]')
    const iconClose = toggle.querySelector<SVGElement>('[data-icon-close]')

    const setOpen = (open: boolean): void => {
        panel.hidden = !open
        toggle.setAttribute('aria-expanded', String(open))
        iconOpen?.classList.toggle('hidden', open)
        iconClose?.classList.toggle('hidden', !open)
        document.documentElement.classList.toggle('overflow-hidden', open)
    }

    toggle.addEventListener('click', () => setOpen(panel.hidden))
    panel.addEventListener('click', (event) => {
        if ((event.target as HTMLElement).closest('a') !== null) {
            setOpen(false)
        }
    })
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false)
            toggle.focus()
        }
    })
    window.matchMedia?.(DESKTOP_NAV_QUERY).addEventListener('change', (event) => {
        if (event.matches) {
            setOpen(false)
        }
    })
}

/**
 * Нижняя панель «Позвонить / Рассчитать» на телефоне. Пока на экране первый экран страницы (там свои кнопки)
 * или уже сама форма заявки, панель убирается, чтобы не дублировать призыв и не закрывать контент.
 */
export function initCallbar(root: ParentNode = document): void {
    const bar = root.querySelector<HTMLElement>('[data-callbar]')
    const targets = Array.from(
        root.querySelectorAll<HTMLElement>('[data-lead-form-section], .zp-hero'),
    )
    if (bar === null || targets.length === 0 || !('IntersectionObserver' in window)) {
        return
    }

    const visible = new Set<Element>()
    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    visible.add(entry.target)
                } else {
                    visible.delete(entry.target)
                }
            }
            bar.dataset.hidden = String(visible.size > 0)
        },
        { threshold: 0.15 },
    )
    targets.forEach((target) => observer.observe(target))
}

function createLightbox(): HTMLDialogElement {
    const dialog = document.createElement('dialog')
    dialog.className = 'zp-lightbox'
    dialog.setAttribute('aria-label', 'Просмотр фото')
    dialog.innerHTML = `
    <div class="relative flex h-dvh w-screen items-center justify-center p-2 sm:p-6">
      <img class="max-h-full max-w-full rounded-lg object-contain" alt="" data-lightbox-image>
      <button type="button" class="absolute top-3 right-3 inline-flex size-12 items-center justify-center rounded-full bg-black/60 text-2xl text-white" data-lightbox-close aria-label="Закрыть">×</button>
      <button type="button" class="absolute left-2 inline-flex size-12 items-center justify-center rounded-full bg-black/60 text-2xl text-white sm:left-4" data-lightbox-prev aria-label="Предыдущее фото">‹</button>
      <button type="button" class="absolute right-2 inline-flex size-12 items-center justify-center rounded-full bg-black/60 text-2xl text-white sm:right-4" data-lightbox-next aria-label="Следующее фото">›</button>
      <p class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-black/60 px-3 py-1 text-sm" data-lightbox-counter></p>
    </div>`
    document.body.append(dialog)

    return dialog
}

export function initGalleries(root: ParentNode = document): void {
    let lightbox: HTMLDialogElement | null = null

    root.querySelectorAll<HTMLElement>('[data-gallery]').forEach((gallery) => {
        const extras = gallery.querySelectorAll<HTMLElement>('[data-gallery-extra]')
        const more = gallery.querySelector<HTMLElement>('[data-gallery-more]')
        const toggle = gallery.querySelector<HTMLButtonElement>('[data-gallery-toggle]')
        const links = Array.from(gallery.querySelectorAll<HTMLAnchorElement>('a[data-lightbox]'))

        if (extras.length > 0 && more !== null && toggle !== null) {
            extras.forEach((figure) => {
                figure.hidden = true
            })
            more.hidden = false
            toggle.addEventListener('click', () => {
                extras.forEach((figure) => {
                    figure.hidden = false
                })
                more.hidden = true
            })
        }

        let index = 0
        const show = (next: number): void => {
            if (lightbox === null) {
                return
            }
            index = (next + links.length) % links.length
            const link = links[index]
            const image = lightbox.querySelector<HTMLImageElement>('[data-lightbox-image]')
            const counter = lightbox.querySelector<HTMLElement>('[data-lightbox-counter]')
            if (image !== null) {
                image.src = link.href
                image.alt = link.querySelector('img')?.alt ?? ''
            }
            if (counter !== null) {
                counter.textContent = `${index + 1} / ${links.length}`
            }
        }

        links.forEach((link, position) => {
            link.addEventListener('click', (event) => {
                if (typeof HTMLDialogElement === 'undefined') {
                    return
                }
                event.preventDefault()
                if (lightbox === null) {
                    lightbox = createLightbox()
                    const dialog = lightbox
                    dialog.addEventListener('click', (click) => {
                        const target = click.target as HTMLElement
                        if (target.closest('[data-lightbox-prev]') !== null) {
                            show(index - 1)
                        } else if (target.closest('[data-lightbox-next]') !== null) {
                            show(index + 1)
                        } else if (
                            target.closest('[data-lightbox-close]') !== null ||
                            target === dialog ||
                            target.tagName === 'DIV'
                        ) {
                            dialog.close()
                        }
                    })
                    dialog.addEventListener('keydown', (key) => {
                        if (key.key === 'ArrowLeft') {
                            show(index - 1)
                        } else if (key.key === 'ArrowRight') {
                            show(index + 1)
                        }
                    })
                }
                show(position)
                lightbox.showModal()
            })
        })
    })
}

const ALLOWED_EMBED_HOSTS = [
    'rutube.ru',
    'vkvideo.ru',
    'vk.com',
    'youtube.com',
    'youtube-nocookie.com',
]

export function isAllowedEmbedUrl(url: string): boolean {
    try {
        const parsed = new URL(url, window.location.href)

        return (
            parsed.protocol === 'https:' &&
            ALLOWED_EMBED_HOSTS.some(
                (host) => parsed.hostname === host || parsed.hostname.endsWith(`.${host}`),
            )
        )
    } catch {
        return false
    }
}

export function initVideoEmbeds(root: ParentNode = document): void {
    root.querySelectorAll<HTMLButtonElement>('[data-video-embed]').forEach((button) => {
        button.addEventListener('click', () => {
            const url = button.dataset.videoEmbed ?? ''
            if (!isAllowedEmbedUrl(url)) {
                return
            }
            const frame = document.createElement('iframe')
            frame.src = url
            frame.title = button.dataset.videoTitle ?? 'Видео'
            frame.allow = 'autoplay; encrypted-media; fullscreen; picture-in-picture'
            frame.allowFullscreen = true
            button.replaceWith(frame)
        })
    })
}

/** Кнопка варианта («Рассчитать этот вариант») переносит выбранный вариант в поле сообщения формы. */
export function initLeadPlans(root: ParentNode = document): void {
    root.querySelectorAll<HTMLAnchorElement>('a[data-lead-plan]').forEach((link) => {
        link.addEventListener('click', () => {
            const plan = link.dataset.leadPlan ?? ''
            const message = root.querySelector<HTMLTextAreaElement>(
                '#lead-form textarea[name="message"]',
            )
            if (message !== null && plan !== '') {
                message.value = applyLeadPlan(message.value, plan)
            }
        })
    })
}

export interface WorkingHoursRow {
    days: number[]
    open: string
    close: string
}

export interface OfficeStatus {
    state: 'open' | 'closed'
    text: string
    /** Номер дня недели в часовом поясе офиса (0 — воскресенье). */
    weekday: number
}

const WEEKDAY_INDEX: Record<string, number> = {
    Sun: 0,
    Mon: 1,
    Tue: 2,
    Wed: 3,
    Thu: 4,
    Fri: 5,
    Sat: 6,
}
const WEEKDAY_LABEL = [
    'в воскресенье',
    'в понедельник',
    'во вторник',
    'в среду',
    'в четверг',
    'в пятницу',
    'в субботу',
]

function toMinutes(value: string): number {
    const [hours = '0', minutes = '0'] = value.split(':')

    return Number(hours) * 60 + Number(minutes)
}

/** Время и день недели в часовом поясе офиса: посетитель из другого региона видит статус по местному времени офиса. */
export function localClock(now: Date, timeZone: string): { weekday: number; minutes: number } {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone,
        weekday: 'short',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(now)
    const value = (type: string): string => parts.find((part) => part.type === type)?.value ?? ''

    return {
        weekday: WEEKDAY_INDEX[value('weekday')] ?? 0,
        minutes: Number(value('hour')) * 60 + Number(value('minute')),
    }
}

/** «Сейчас открыто · до 18:00» или «Закрыто · откроемся завтра в 09:00». */
export function officeStatus(
    rows: WorkingHoursRow[],
    now: Date,
    timeZone: string,
): OfficeStatus | null {
    const schedule = rows.filter((row) => row.open !== '' && row.close !== '')
    if (schedule.length === 0) {
        return null
    }

    const { weekday, minutes } = localClock(now, timeZone)
    const today = schedule.find((row) => row.days.includes(weekday))
    if (
        today !== undefined &&
        minutes >= toMinutes(today.open) &&
        minutes < toMinutes(today.close)
    ) {
        return { state: 'open', text: `Сейчас открыто · до ${today.close}`, weekday }
    }

    const sameDayLater = today !== undefined && minutes < toMinutes(today.open)
    for (let offset = sameDayLater ? 0 : 1; offset <= 7; offset += 1) {
        const day = (weekday + offset) % 7
        const row = schedule.find((candidate) => candidate.days.includes(day))
        if (row !== undefined) {
            const when = offset === 0 ? 'сегодня' : offset === 1 ? 'завтра' : WEEKDAY_LABEL[day]

            return { state: 'closed', text: `Закрыто · откроемся ${when} в ${row.open}`, weekday }
        }
    }

    return null
}

/** Подсвечивает сегодняшний день в режиме работы и показывает чип «открыто сейчас». */
export function initOfficeStatus(root: ParentNode = document, now: Date = new Date()): void {
    root.querySelectorAll<HTMLElement>('[data-office]').forEach((office) => {
        const rows = Array.from(office.querySelectorAll<HTMLElement>('[data-days]'))
        const chip = office.querySelector<HTMLElement>('[data-office-status]')
        const timeZone = office.dataset.timezone || 'Europe/Saratov'
        const hours: WorkingHoursRow[] = rows.map((row) => ({
            days: (row.dataset.days ?? '')
                .split(',')
                .filter((day) => day !== '')
                .map(Number),
            open: row.dataset.open ?? '',
            close: row.dataset.close ?? '',
        }))

        try {
            const status = officeStatus(hours, now, timeZone)
            if (status === null) {
                return
            }
            rows.forEach((row, index) => {
                row.dataset.today = String(hours[index].days.includes(status.weekday))
            })
            if (chip !== null) {
                chip.textContent = status.text
                chip.dataset.state = status.state
                chip.hidden = false
            }
        } catch {
            // Часовой пояс не поддерживается браузером — остаётся обычное расписание без статуса.
        }
    })
}

/** Кнопки «скопировать» у адреса и реквизитов: копируют в буфер и на секунду меняют вид кнопки. */
export function initCopyButtons(root: ParentNode = document): void {
    root.querySelectorAll<HTMLButtonElement>('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.copy ?? ''
            try {
                await navigator.clipboard.writeText(value)
            } catch {
                const field = document.createElement('textarea')
                field.value = value
                field.setAttribute('readonly', '')
                field.style.position = 'fixed'
                field.style.opacity = '0'
                document.body.append(field)
                field.select()
                document.execCommand('copy')
                field.remove()
            }
            const label = button.getAttribute('aria-label') ?? ''
            button.dataset.copied = 'true'
            button.setAttribute('aria-label', 'Скопировано')
            window.setTimeout(() => {
                delete button.dataset.copied
                button.setAttribute('aria-label', label)
            }, 1600)
        })
    })
}

const ALLOWED_MAP_HOSTS = ['2gis.ru', '2gis.com']

export function isAllowedMapUrl(url: string): boolean {
    try {
        const parsed = new URL(url, window.location.href)

        return (
            parsed.protocol === 'https:' &&
            ALLOWED_MAP_HOSTS.some(
                (host) => parsed.hostname === host || parsed.hostname.endsWith(`.${host}`),
            )
        )
    } catch {
        return false
    }
}

/** Карта подключается по нажатию: страница не тянет тяжёлый виджет, пока он не нужен. */
export function initMapEmbeds(root: ParentNode = document): void {
    root.querySelectorAll<HTMLButtonElement>('[data-map-embed]').forEach((button) => {
        button.addEventListener('click', () => {
            const url = button.dataset.mapEmbed ?? ''
            const container = button.closest<HTMLElement>('[data-map]')
            if (!isAllowedMapUrl(url) || container === null) {
                return
            }
            const frame = document.createElement('iframe')
            frame.src = url
            frame.title = button.dataset.mapTitle ?? 'Карта'
            frame.loading = 'lazy'
            frame.referrerPolicy = 'no-referrer-when-downgrade'
            container.replaceChildren(frame)
        })
    })
}

/** Цена варианта для выбранной строки (профиля); `null` — такой комбинации не выпускают. */
export function priceFor(prices: Record<string, number | null>, row: string): number | null {
    const value = prices[row]

    return typeof value === 'number' ? value : null
}

export function formatMoney(value: number): string {
    return value.toLocaleString('ru-RU').replace(/\s/g, ' ')
}

function pluralizeOptions(count: number): string {
    const mod10 = count % 10
    const mod100 = count % 100
    if (mod10 === 1 && mod100 !== 11) {
        return `${count} вариант`
    }
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
        return `${count} варианта`
    }

    return `${count} вариантов`
}

/**
 * Прайс-лист с выбором: вкладки групп, выбор профиля и фильтр по классу. Цены берутся из data-prices,
 * варианты без цены для выбранного профиля скрываются, счётчик озвучивается экранным диктором.
 */
export function initPriceMatrices(root: ParentNode = document): void {
    root.querySelectorAll<HTMLElement>('[data-price-matrix]').forEach((matrix) => {
        const tabs = Array.from(matrix.querySelectorAll<HTMLButtonElement>('[data-pm-tab]'))
        const panels = Array.from(matrix.querySelectorAll<HTMLElement>('[data-pm-panel]'))

        const selectTab = (index: number): void => {
            tabs.forEach((tab, position) =>
                tab.setAttribute('aria-selected', String(position === index)),
            )
            panels.forEach((panel, position) => {
                panel.hidden = position !== index
            })
        }

        if (tabs.length > 0) {
            tabs.forEach((tab, position) =>
                tab.addEventListener('click', () => selectTab(position)),
            )
            tabs.forEach((tab, position) =>
                tab.addEventListener('keydown', (event) => {
                    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
                        return
                    }
                    const next =
                        (position + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) %
                        tabs.length
                    selectTab(next)
                    tabs[next].focus()
                }),
            )
            selectTab(0)
        }

        panels.forEach((panel) => {
            const options = Array.from(panel.querySelectorAll<HTMLElement>('[data-pm-option]'))
            const count = panel.querySelector<HTMLElement>('[data-pm-count]')
            const empty = panel.querySelector<HTMLElement>('[data-pm-empty]')
            const list = panel.querySelector<HTMLElement>('[data-pm-list]')
            const more = panel.querySelector<HTMLButtonElement>('[data-pm-more]')
            const moreWrap = panel.querySelector<HTMLElement>('[data-pm-more-wrap]')
            const limit = Number(list?.dataset.pmLimit ?? 0)
            let expanded = false

            const render = (): void => {
                const row =
                    panel.querySelector<HTMLInputElement>('[data-pm-row]:checked')?.value ?? ''
                const group =
                    panel.querySelector<HTMLInputElement>('[data-pm-filter]:checked')?.value ?? ''
                let shown = 0

                options.forEach((option) => {
                    let prices: Record<string, number | null> = {}
                    try {
                        prices = JSON.parse(option.dataset.prices ?? '{}') as Record<
                            string,
                            number | null
                        >
                    } catch {
                        // Повреждённые данные — вариант просто не показываем.
                    }
                    const price = priceFor(prices, row)
                    const matchesGroup = group === '' || option.dataset.group === group
                    const visible = price !== null && matchesGroup
                    if (!visible) {
                        option.hidden = true
                        return
                    }
                    shown += 1
                    // Сначала показываем первые `limit` вариантов; остальные раскрывает кнопка «Показать ещё».
                    option.hidden = limit > 0 && !expanded && shown > limit
                    const target = option.querySelector<HTMLElement>('[data-pm-price]')
                    if (target !== null) {
                        target.textContent = formatMoney(price)
                    }
                    const order = option.querySelector<HTMLElement>('[data-pm-order]')
                    if (order !== null) {
                        const details = order.dataset.details ?? ''
                        order.dataset.leadPlan = `${order.dataset.title ?? ''}${details !== '' ? ` (${details})` : ''}, профиль ${row}`
                    }
                })

                if (count !== null) {
                    count.textContent =
                        shown > 0 ? `Профиль ${row}: ${pluralizeOptions(shown)}` : ''
                }
                if (moreWrap !== null && more !== null) {
                    const hiddenCount = limit > 0 && !expanded ? Math.max(0, shown - limit) : 0
                    moreWrap.hidden = hiddenCount === 0
                    more.textContent = hiddenCount > 0 ? `Показать ещё ${hiddenCount}` : ''
                }
                if (empty !== null) {
                    empty.hidden = shown > 0
                }
            }

            panel
                .querySelectorAll<HTMLInputElement>('[data-pm-row], [data-pm-filter]')
                .forEach((input) =>
                    input.addEventListener('change', () => {
                        expanded = false
                        render()
                    }),
                )
            more?.addEventListener('click', () => {
                expanded = true
                render()
            })
            render()
        })
    })
}
