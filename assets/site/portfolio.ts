/**
 * Портфолио: галерея работы (один кадр + лента миниатюр) и ряд карточек блока «Портфолио» со стрелками.
 * Без JS кадры галереи идут друг под другом, а ряд прокручивается пальцем или колесом.
 */

export function initPortfolioGalleries(root: ParentNode = document): void {
    root.querySelectorAll<HTMLElement>('[data-pf-gallery]').forEach((gallery) => {
        const slides = Array.from(gallery.querySelectorAll<HTMLElement>('[data-pf-slide]'))
        if (slides.length === 0) {
            return
        }
        const thumbs = Array.from(gallery.querySelectorAll<HTMLButtonElement>('[data-pf-thumb]'))
        const counter = gallery.querySelector<HTMLElement>('[data-pf-counter]')
        const nav = gallery.querySelector<HTMLElement>('[data-pf-nav]')
        const strip = gallery.querySelector<HTMLElement>('[data-pf-strip]')
        let index = 0

        const show = (next: number, focusThumb = false): void => {
            index = (next + slides.length) % slides.length
            slides.forEach((slide, position) => {
                const active = position === index
                slide.classList.toggle('is-active', active)
                // Скрытый кадр не должен играть: ролики на паузу, iframe VK/Rutube выгружаем и возвращаем при показе.
                slide.querySelectorAll('iframe').forEach((frame) => {
                    if (!active && frame.src !== 'about:blank') {
                        frame.dataset.src = frame.src
                        frame.src = 'about:blank'
                    } else if (active && frame.dataset.src !== undefined) {
                        frame.src = frame.dataset.src
                        delete frame.dataset.src
                    }
                })
                if (!active) {
                    slide.querySelectorAll('video').forEach((video) => video.pause())
                }
            })
            thumbs.forEach((thumb, position) => {
                if (position === index) {
                    thumb.setAttribute('aria-current', 'true')
                    thumb.scrollIntoView({ block: 'nearest', inline: 'nearest' })
                    if (focusThumb) {
                        thumb.focus()
                    }
                } else {
                    thumb.removeAttribute('aria-current')
                }
            })
            if (counter !== null) {
                counter.textContent = `${index + 1} / ${slides.length}`
            }
        }

        gallery.classList.add('is-ready')
        if (nav !== null) {
            nav.hidden = false
        }
        if (strip !== null) {
            strip.hidden = false
        }

        gallery.querySelector('[data-pf-prev]')?.addEventListener('click', () => show(index - 1))
        gallery.querySelector('[data-pf-next]')?.addEventListener('click', () => show(index + 1))
        thumbs.forEach((thumb, position) => thumb.addEventListener('click', () => show(position)))
        strip?.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                event.preventDefault()
                show(index + (event.key === 'ArrowRight' ? 1 : -1), true)
            }
        })

        // Свайп по кадру на телефоне.
        let startX: number | null = null
        const stage = gallery.querySelector<HTMLElement>('.zp-pf-gallery__stage')
        stage?.addEventListener('touchstart', (event) => {
            startX = event.touches[0]?.clientX ?? null
        }, { passive: true })
        stage?.addEventListener('touchend', (event) => {
            const endX = event.changedTouches[0]?.clientX
            if (startX !== null && endX !== undefined && Math.abs(endX - startX) > 50 && slides[index].querySelector('media-controller, iframe') === null) {
                show(index + (endX < startX ? 1 : -1))
            }
            startX = null
        })

        show(0)
    })
}

export function initPortfolioRows(root: ParentNode = document): void {
    root.querySelectorAll<HTMLElement>('[data-pf-row]').forEach((row) => {
        const scroller = row.querySelector<HTMLElement>('[data-pf-scroller]')
        const prev = row.querySelector<HTMLButtonElement>('[data-pf-row-prev]')
        const next = row.querySelector<HTMLButtonElement>('[data-pf-row-next]')
        if (scroller === null || prev === null || next === null) {
            return
        }

        const update = (): void => {
            const max = scroller.scrollWidth - scroller.clientWidth
            prev.disabled = scroller.scrollLeft <= 1
            next.disabled = scroller.scrollLeft >= max - 1
            row.querySelector<HTMLElement>('[data-pf-row-arrows]')?.toggleAttribute('hidden', max <= 1)
        }
        const step = (direction: number): void => {
            const card = scroller.firstElementChild
            const width = card instanceof HTMLElement ? card.getBoundingClientRect().width + 16 : scroller.clientWidth
            scroller.scrollBy({ left: direction * width, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })
        }

        prev.addEventListener('click', () => step(-1))
        next.addEventListener('click', () => step(1))
        scroller.addEventListener('scroll', update, { passive: true })
        window.addEventListener('resize', update)
        update()
    })
}
