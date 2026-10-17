import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { initPortfolioLikes, initPortfolioShare, initPortfolioTracks } from './portfolio-social'

function mount(html: string): HTMLElement {
    const root = document.createElement('div')
    root.innerHTML = html
    document.body.append(root)

    return root
}

function setMobile(matches: boolean): void {
    window.matchMedia = vi.fn().mockReturnValue({ matches, addEventListener: vi.fn() })
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

const post = (id: string) => `
    <article>
        <div data-pf-track><a></a><a></a><a></a></div>
        <span data-pf-track-counter hidden>1 / 3</span>
        <div data-pf-actions hidden>
            <button data-pf-like="${id}" aria-pressed="false"><span data-pf-like-count></span></button>
            <div class="zp-pf-share">
                <button data-pf-share="https://zaborprofil.test/portfolio/a/${id}/" aria-expanded="false"></button>
                <div data-pf-share-menu hidden><p data-pf-share-note></p><a href="https://t.me/share/url">Telegram</a></div>
            </div>
            <span data-pf-track-dots><i class="is-active"></i><i></i><i></i></span>
        </div>
    </article>`

afterEach(() => {
    document.body.innerHTML = ''
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
})

describe('photo track in a feed card', () => {
    it('shows the counter and moves the dots with the scrolled photo', () => {
        const root = mount(post('p1'))
        const track = root.querySelector<HTMLElement>('[data-pf-track]')!
        Object.defineProperty(track, 'clientWidth', { value: 300 })
        vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
            callback(0)
            return 1
        })
        initPortfolioTracks(root)

        const counter = root.querySelector<HTMLElement>('[data-pf-track-counter]')!
        expect(counter.hidden).toBe(false)
        track.scrollLeft = 600
        track.dispatchEvent(new Event('scroll'))
        expect(counter.textContent).toBe('3 / 3')
        const dots = root.querySelectorAll('[data-pf-track-dots] i')
        expect(dots[2].classList.contains('is-active')).toBe(true)
        expect(dots[0].classList.contains('is-active')).toBe(false)
    })
})

describe('likes', () => {
    beforeEach(() => setMobile(true))

    it('loads counters, marks my like and toggles it on the server', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ likes: { p1: { count: 4, liked: false } } }),
            })
            .mockResolvedValueOnce({ ok: true, json: async () => ({ count: 5, liked: true }) })
        vi.stubGlobal('fetch', fetchMock)
        const root = mount(post('p1'))
        initPortfolioLikes(root)
        await flush()

        const button = root.querySelector<HTMLButtonElement>('[data-pf-like]')!
        expect(root.querySelector<HTMLElement>('[data-pf-actions]')!.hidden).toBe(false)
        expect(fetchMock.mock.calls[0][0]).toBe('/api/portfolio/likes?ids=p1')
        expect(button.textContent?.trim()).toBe('4')

        button.click()
        expect(button.getAttribute('aria-pressed')).toBe('true')
        await flush()
        expect(fetchMock.mock.calls[1][0]).toBe('/api/portfolio/likes/p1')
        expect(JSON.parse(String(fetchMock.mock.calls[1][1].body))).toEqual({ liked: true })
        expect(button.textContent?.trim()).toBe('5')
    })

    it('rolls the like back when the server refuses it', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ likes: { p1: { count: 2, liked: false } } }),
            })
            .mockResolvedValueOnce({ ok: false, json: async () => ({ code: 'LIKE_LIMIT' }) })
        vi.stubGlobal('fetch', fetchMock)
        const root = mount(post('p1'))
        initPortfolioLikes(root)
        await flush()

        const button = root.querySelector<HTMLButtonElement>('[data-pf-like]')!
        button.click()
        await flush()
        expect(button.getAttribute('aria-pressed')).toBe('false')
        expect(button.textContent?.trim()).toBe('2')
    })

    it('does not ask for counters on a computer, where the buttons are hidden', () => {
        setMobile(false)
        const fetchMock = vi.fn()
        vi.stubGlobal('fetch', fetchMock)
        initPortfolioLikes(mount(post('p1')))
        expect(fetchMock).not.toHaveBeenCalled()
    })
})

describe('share', () => {
    it('copies the work link and opens the messenger menu', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined)
        vi.stubGlobal('navigator', { clipboard: { writeText } })
        const root = mount(post('p1'))
        initPortfolioShare(root)

        const toggle = root.querySelector<HTMLButtonElement>('[data-pf-share]')!
        const menu = root.querySelector<HTMLElement>('[data-pf-share-menu]')!
        toggle.click()
        await flush()
        expect(writeText).toHaveBeenCalledWith('https://zaborprofil.test/portfolio/a/p1/')
        expect(menu.hidden).toBe(false)
        expect(toggle.getAttribute('aria-expanded')).toBe('true')
        expect(root.querySelector('[data-pf-share-note]')!.textContent).toBe('Ссылка скопирована')

        document.body.click()
        expect(menu.hidden).toBe(true)
    })
})
