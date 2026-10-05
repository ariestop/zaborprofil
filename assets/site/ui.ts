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
