import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { initPortfolioGalleries, initPortfolioViewToggle } from './portfolio'

function mount(html: string): HTMLElement {
    const root = document.createElement('div')
    root.innerHTML = html
    document.body.append(root)

    return root
}

const gallery = (slides: number) => `
    <div data-pf-gallery>
        <div class="zp-pf-gallery__stage">
            <div data-pf-gallery-track>${Array.from({ length: slides }, (_, i) => `<figure data-pf-slide>${i}</figure>`).join('')}</div>
            <span data-pf-badge hidden>1 / ${slides}</span>
            <span data-pf-dots hidden></span>
            <div data-pf-nav hidden><button data-pf-prev></button><span data-pf-counter></span><button data-pf-next></button></div>
        </div>
        <div data-pf-strip hidden>${Array.from({ length: slides }, (_, i) => `<button data-pf-thumb="${i}"></button>`).join('')}</div>
    </div>`

afterEach(() => {
    document.body.innerHTML = ''
})

function setMobile(matches: boolean): void {
    window.matchMedia = vi.fn().mockReturnValue({ matches, addEventListener: vi.fn() })
}

describe('portfolio gallery', () => {
    beforeEach(() => {
        Element.prototype.scrollIntoView = () => undefined
        setMobile(false)
    })

    it('shows one slide, the counter badge and dots that follow the active slide', () => {
        const root = mount(gallery(3))
        initPortfolioGalleries(root)

        const badge = root.querySelector<HTMLElement>('[data-pf-badge]')!
        const dots = root.querySelectorAll('[data-pf-dots] i')
        expect(badge.hidden).toBe(false)
        expect(badge.textContent).toBe('1 / 3')
        expect(dots).toHaveLength(3)
        expect(dots[0].classList.contains('is-active')).toBe(true)

        root.querySelector<HTMLButtonElement>('[data-pf-thumb="2"]')!.click()
        expect(badge.textContent).toBe('3 / 3')
        expect(dots[2].classList.contains('is-active')).toBe(true)
        expect(dots[0].classList.contains('is-active')).toBe(false)
        expect(root.querySelectorAll('[data-pf-slide].is-active')).toHaveLength(1)

        root.querySelector<HTMLButtonElement>('[data-pf-next]')!.click()
        expect(badge.textContent).toBe('1 / 3')
    })

    it('keeps only the counter when there are too many slides for dots', () => {
        const root = mount(gallery(20))
        initPortfolioGalleries(root)

        expect(root.querySelectorAll('[data-pf-dots] i')).toHaveLength(0)
        expect(root.querySelector<HTMLElement>('[data-pf-dots]')!.hidden).toBe(true)
        expect(root.querySelector<HTMLElement>('[data-pf-badge]')!.textContent).toBe('1 / 20')
    })

    it('follows the swiped frame on a phone instead of the thumbnail strip', () => {
        setMobile(true)
        vi.spyOn(window, 'requestAnimationFrame').mockImplementation((callback) => {
            callback(0)
            return 1
        })
        const root = mount(gallery(3))
        initPortfolioGalleries(root)
        const track = root.querySelector<HTMLElement>('[data-pf-gallery-track]')!
        Object.defineProperty(track, 'clientWidth', { value: 390 })

        track.scrollLeft = 390
        track.dispatchEvent(new Event('scroll'))
        expect(root.querySelector<HTMLElement>('[data-pf-badge]')!.textContent).toBe('2 / 3')
        expect(root.querySelectorAll('[data-pf-dots] i')[1].classList.contains('is-active')).toBe(
            true,
        )
        expect(root.querySelector('[data-pf-slide].is-active')!.textContent).toBe('1')
    })
})

describe('portfolio view toggle', () => {
    const html = `<div><div data-pf-view hidden>
        <button data-pf-view-mode="cards" aria-pressed="true"></button><button data-pf-view-mode="grid" aria-pressed="false"></button>
    </div><div data-pf-grid></div></div>`

    beforeEach(() => {
        window.localStorage.clear()
    })

    it('switches between cards and the compact grid and remembers the choice', () => {
        const root = mount(html)
        initPortfolioViewToggle(root)
        const grid = root.querySelector<HTMLElement>('[data-pf-grid]')!
        const gridButton = root.querySelector<HTMLButtonElement>('[data-pf-view-mode="grid"]')!

        expect(root.querySelector<HTMLElement>('[data-pf-view]')!.hidden).toBe(false)
        expect(grid.classList.contains('is-compact')).toBe(false)

        gridButton.click()
        expect(grid.classList.contains('is-compact')).toBe(true)
        expect(gridButton.getAttribute('aria-pressed')).toBe('true')
        expect(window.localStorage.getItem('zp-portfolio-view')).toBe('grid')

        document.body.innerHTML = ''
        const again = mount(html)
        initPortfolioViewToggle(again)
        expect(again.querySelector('[data-pf-grid]')!.classList.contains('is-compact')).toBe(true)
    })
})
