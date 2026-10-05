/**
 * Переключатель «Частным клиентам / Бизнесу»: блоки с settings.audience = b2c | b2b обёрнуты в [data-audience]
 * (TwigBlockRenderer), видимостью управляет атрибут data-audience-mode на <html> (стили в site.css).
 */
export type Audience = 'b2c' | 'b2b'

const STORAGE_KEY = 'zp-audience'

/** Режим из ссылки: ?for=b2b (или business) и #b2b — для бизнеса, ?for=b2c (или private) — для частных клиентов. */
export function audienceFromLocation(search: string, hash: string): Audience | null {
    const value = new URLSearchParams(search).get('for')?.toLowerCase() ?? ''
    if (value === 'b2b' || value === 'business' || hash.toLowerCase() === '#b2b') {
        return 'b2b'
    }
    if (value === 'b2c' || value === 'private') {
        return 'b2c'
    }

    return null
}

function readStored(): Audience | null {
    try {
        const value = window.localStorage.getItem(STORAGE_KEY)

        return value === 'b2b' || value === 'b2c' ? value : null
    } catch {
        return null
    }
}

function store(value: Audience): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, value)
    } catch {
        // Хранилище недоступно (приватный режим): выбор действует до перезагрузки.
    }
}

export function initAudienceSwitch(root: Document = document): void {
    const control = root.querySelector<HTMLElement>('[data-audience-switch]')
    if (control === null) {
        return
    }
    const buttons = Array.from(
        control.querySelectorAll<HTMLButtonElement>('button[data-audience-value]'),
    )

    const apply = (value: Audience): void => {
        root.documentElement.dataset.audienceMode = value
        buttons.forEach((button) =>
            button.setAttribute('aria-pressed', String(button.dataset.audienceValue === value)),
        )
    }

    apply(
        audienceFromLocation(window.location.search, window.location.hash) ?? readStored() ?? 'b2c',
    )

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const value: Audience = button.dataset.audienceValue === 'b2b' ? 'b2b' : 'b2c'
            apply(value)
            store(value)
        })
    })
}
