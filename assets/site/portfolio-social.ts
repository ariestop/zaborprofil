/**
 * Лента портфолио на телефоне: свайп фото в карточке (счётчик и точки), лайки и «поделиться».
 * Страницы портфолио кэшируются целиком, поэтому счётчики лайков и «мой лайк» запрашиваются отдельно (/api/portfolio/likes).
 * Без JS кнопки лайка и «поделиться» скрыты, фото в карточке листаются пальцем.
 */
import { copyText } from './ui'

export const MOBILE_QUERY = '(max-width: 767px)'

interface LikeState {
    count: number
    liked: boolean
}

/** Счётчик и точки ленты фото в карточке работы. */
export function initPortfolioTracks(root: ParentNode = document): void {
    root.querySelectorAll<HTMLElement>('[data-pf-track]').forEach((track) => {
        const card = track.closest('article') ?? track.parentElement
        const counter = card?.querySelector<HTMLElement>('[data-pf-track-counter]') ?? null
        const dots = Array.from(card?.querySelectorAll<HTMLElement>('[data-pf-track-dots] i') ?? [])
        const total = track.children.length
        let index = -1
        let frame = 0

        const update = (): void => {
            frame = 0
            const width = track.clientWidth
            const next =
                width > 0
                    ? Math.min(total - 1, Math.max(0, Math.round(track.scrollLeft / width)))
                    : 0
            if (next === index) {
                return
            }
            index = next
            if (counter !== null) {
                counter.textContent = `${index + 1} / ${total}`
            }
            dots.forEach((dot, position) => dot.classList.toggle('is-active', position === index))
        }

        if (counter !== null) {
            counter.hidden = false
        }
        track.addEventListener(
            'scroll',
            () => {
                if (frame === 0) {
                    frame = window.requestAnimationFrame(update)
                }
            },
            { passive: true },
        )
        update()
    })
}

function likeButtons(root: ParentNode, id?: string): HTMLButtonElement[] {
    return Array.from(root.querySelectorAll<HTMLButtonElement>('[data-pf-like]')).filter(
        (button) => id === undefined || button.dataset.pfLike === id,
    )
}

function renderLike(root: ParentNode, id: string, state: LikeState): void {
    likeButtons(root, id).forEach((button) => {
        button.setAttribute('aria-pressed', String(state.liked))
        const count = button.querySelector<HTMLElement>('[data-pf-like-count]')
        if (count !== null) {
            count.textContent = state.count > 0 ? String(state.count) : ''
        }
    })
}

function isLikeState(value: unknown): value is LikeState {
    return (
        typeof value === 'object' &&
        value !== null &&
        typeof (value as LikeState).count === 'number' &&
        typeof (value as LikeState).liked === 'boolean'
    )
}

/** Лайки: загрузка счётчиков (только на телефоне, где кнопки видны) и постановка/снятие лайка с откатом при ошибке. */
export function initPortfolioLikes(root: ParentNode = document): void {
    const buttons = likeButtons(root)
    if (buttons.length === 0) {
        return
    }
    const states = new Map<string, LikeState>()
    root.querySelectorAll<HTMLElement>('[data-pf-actions]').forEach((actions) => {
        actions.hidden = false
    })

    let loaded = false
    const load = async (): Promise<void> => {
        if (loaded) {
            return
        }
        loaded = true
        const ids = Array.from(
            new Set(buttons.map((button) => button.dataset.pfLike ?? '')),
        ).filter(Boolean)
        try {
            const response = await fetch(
                `/api/portfolio/likes?ids=${ids.map(encodeURIComponent).join(',')}`,
                {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                },
            )
            const data: unknown = await response.json()
            const likes = (data as { likes?: Record<string, unknown> }).likes ?? {}
            Object.entries(likes).forEach(([id, state]) => {
                if (isLikeState(state)) {
                    states.set(id, state)
                    renderLike(root, id, state)
                }
            })
        } catch {
            // Без счётчиков кнопка всё равно работает: число придёт после первого нажатия.
        }
    }

    const mobile = window.matchMedia(MOBILE_QUERY)
    if (mobile.matches) {
        void load()
    } else {
        mobile.addEventListener('change', () => void load(), { once: true })
    }

    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            const id = button.dataset.pfLike ?? ''
            if (id === '' || button.getAttribute('aria-busy') === 'true') {
                return
            }
            const before = states.get(id) ?? {
                count: 0,
                liked: button.getAttribute('aria-pressed') === 'true',
            }
            const wanted = !before.liked
            // Сразу показываем результат нажатия, сервер затем присылает точный счётчик.
            renderLike(root, id, {
                count: Math.max(0, before.count + (wanted ? 1 : -1)),
                liked: wanted,
            })
            button.classList.toggle('is-popping', wanted)
            button.setAttribute('aria-busy', 'true')
            try {
                const response = await fetch(`/api/portfolio/likes/${encodeURIComponent(id)}`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ liked: wanted }),
                })
                const data: unknown = await response.json()
                if (!response.ok || !isLikeState(data)) {
                    throw new Error('like failed')
                }
                states.set(id, data)
                renderLike(root, id, data)
            } catch {
                renderLike(root, id, before)
                button.classList.remove('is-popping')
            } finally {
                button.removeAttribute('aria-busy')
            }
        })
        button.addEventListener('animationend', () => button.classList.remove('is-popping'))
    })
}

/** «Поделиться»: копирует ссылку на работу и открывает меню отправки в Telegram или MAX. */
export function initPortfolioShare(root: ParentNode = document): void {
    const toggles = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-pf-share]'))
    if (toggles.length === 0) {
        return
    }

    const close = (toggle: HTMLButtonElement): void => {
        const menu = toggle.parentElement?.querySelector<HTMLElement>('[data-pf-share-menu]')
        if (menu !== null && menu !== undefined) {
            menu.hidden = true
        }
        toggle.setAttribute('aria-expanded', 'false')
    }
    const closeAll = (except?: HTMLButtonElement): void => {
        toggles.filter((toggle) => toggle !== except).forEach(close)
    }

    toggles.forEach((toggle) => {
        const menu = toggle.parentElement?.querySelector<HTMLElement>('[data-pf-share-menu]')
        const note = menu?.querySelector<HTMLElement>('[data-pf-share-note]')
        if (menu === null || menu === undefined) {
            return
        }
        toggle.addEventListener('click', async () => {
            if (!menu.hidden) {
                close(toggle)
                return
            }
            closeAll(toggle)
            menu.hidden = false
            toggle.setAttribute('aria-expanded', 'true')
            const url = toggle.dataset.pfShare ?? window.location.href
            const copied = await copyText(url)
            if (note !== null && note !== undefined) {
                note.textContent = copied ? 'Ссылка скопирована' : url
            }
        })
        menu.querySelectorAll('a').forEach((link) =>
            link.addEventListener('click', () => close(toggle)),
        )
    })

    document.addEventListener('click', (event) => {
        const target = event.target
        if (
            target instanceof Node &&
            !toggles.some((toggle) => toggle.parentElement?.contains(target))
        ) {
            closeAll()
        }
    })
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return
        }
        const open = toggles.find((toggle) => toggle.getAttribute('aria-expanded') === 'true')
        if (open !== undefined) {
            close(open)
            open.focus()
        }
    })
}
