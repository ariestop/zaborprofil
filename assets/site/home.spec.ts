import { afterEach, describe, expect, it } from 'vitest'
import { initHomeChoice } from './home'

function mount(): HTMLDetailsElement {
    document.body.innerHTML = `
        <a href="#home-choice" data-home-choice>Выбрать забор</a>
        <details id="home-choice" data-home-choice-panel>
            <summary>Помочь с выбором</summary>
            <a href="/zabor-jaluzi/">Жалюзи</a>
        </details>
    `
    return document.querySelector<HTMLDetailsElement>('details')!
}

afterEach(() => {
    document.body.innerHTML = ''
    window.history.replaceState(null, '', '/')
})

describe('подбор на главной', () => {
    it('не меняет внутренние страницы без подбора', () => {
        document.body.innerHTML = '<main><h1>О компании</h1></main>'
        initHomeChoice()
        expect(document.querySelector('h1')?.textContent).toBe('О компании')
        expect(document.querySelector('details')).toBeNull()
    })

    it('при загрузке оставляет подбор свёрнутым и доступным через summary', () => {
        const panel = mount()
        initHomeChoice()
        expect(panel.open).toBe(false)
        expect(panel.querySelector('summary')?.textContent).toBe('Помочь с выбором')
        expect(panel.querySelector('a')?.getAttribute('href')).toBe('/zabor-jaluzi/')
    })

    it('раскрывает подбор по CTA и передаёт фокус заголовку', () => {
        const panel = mount()
        initHomeChoice()
        const link = document.querySelector<HTMLAnchorElement>('[data-home-choice]')!
        // Не запускаем переход jsdom; обработчик раскрытия от этого не зависит.
        link.addEventListener('click', (event) => event.preventDefault())
        link.click()
        expect(panel.open).toBe(true)
        expect(document.activeElement).toBe(panel.querySelector('summary'))
        expect(link.getAttribute('href')).toBe('#home-choice')
    })

    it('раскрывает подбор при прямом входе по якорной ссылке', () => {
        window.history.replaceState(null, '', '/#home-choice')
        const panel = mount()
        initHomeChoice()
        expect(panel.open).toBe(true)
        expect(document.activeElement).toBe(panel.querySelector('summary'))
    })
})
