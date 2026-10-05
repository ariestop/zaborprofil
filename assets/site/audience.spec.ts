import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { audienceFromLocation, initAudienceSwitch } from './audience'

describe('audienceFromLocation', () => {
    it('reads the business mode from ?for= and #b2b', () => {
        expect(audienceFromLocation('?for=b2b', '')).toBe('b2b')
        expect(audienceFromLocation('?for=Business', '')).toBe('b2b')
        expect(audienceFromLocation('', '#b2b')).toBe('b2b')
        expect(audienceFromLocation('?for=private', '')).toBe('b2c')
        expect(audienceFromLocation('?utm_source=x', '#catalog')).toBeNull()
    })
})

describe('initAudienceSwitch', () => {
    beforeEach(() => {
        window.localStorage.clear()
        window.history.replaceState(null, '', '/')
        document.documentElement.removeAttribute('data-audience-mode')
        document.body.innerHTML = `
            <div data-audience-switch>
                <button type="button" data-audience-value="b2c" aria-pressed="true">Частным клиентам</button>
                <button type="button" data-audience-value="b2b" aria-pressed="false">Бизнесу</button>
            </div>`
    })

    afterEach(() => {
        document.body.innerHTML = ''
        document.documentElement.removeAttribute('data-audience-mode')
    })

    const pressed = (): string | undefined =>
        document.querySelector<HTMLButtonElement>('button[aria-pressed="true"]')?.dataset
            .audienceValue

    it('starts with private clients and switches to business on click, remembering the choice', () => {
        initAudienceSwitch(document)
        expect(document.documentElement.dataset.audienceMode).toBe('b2c')
        expect(pressed()).toBe('b2c')

        document.querySelector<HTMLButtonElement>('[data-audience-value="b2b"]')!.click()
        expect(document.documentElement.dataset.audienceMode).toBe('b2b')
        expect(pressed()).toBe('b2b')
        expect(window.localStorage.getItem('zp-audience')).toBe('b2b')
    })

    it('restores the remembered mode, but a link with ?for= wins', () => {
        window.localStorage.setItem('zp-audience', 'b2b')
        initAudienceSwitch(document)
        expect(document.documentElement.dataset.audienceMode).toBe('b2b')

        window.history.replaceState(null, '', '/?for=b2c')
        initAudienceSwitch(document)
        expect(document.documentElement.dataset.audienceMode).toBe('b2c')
    })

    it('does nothing on pages without the switch', () => {
        document.body.innerHTML = ''
        initAudienceSwitch(document)
        expect(document.documentElement.hasAttribute('data-audience-mode')).toBe(false)
    })
})
