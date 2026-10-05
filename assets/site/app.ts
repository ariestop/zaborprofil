import './site.css'
import { collectUtm, formatPhone, initLeadFormContext, validateLeadFields } from './leadForm'
import {
    initCallbar,
    initCopyButtons,
    initGalleries,
    initLeadPlans,
    initMapEmbeds,
    initMobileNav,
    initOfficeStatus,
    initVideoEmbeds,
} from './ui'

function formPayload(form: HTMLFormElement): Record<string, unknown> {
    const data = new FormData(form)

    return {
        source: String(data.get('source') ?? 'public_page_form'),
        name: String(data.get('name') ?? '').trim(),
        phone: String(data.get('phone') ?? '').trim(),
        email: String(data.get('email') ?? '') || null,
        message: String(data.get('message') ?? '').trim() || null,
        consent: data.get('consent') === 'on',
        consentText: String(data.get('consentText') ?? ''),
        website: String(data.get('website') ?? ''),
        formLoadedAt: String(data.get('formLoadedAt') ?? ''),
        pageUrl: String(data.get('pageUrl') || window.location.href),
        policyUrl: String(data.get('policyUrl') ?? '/privacy/'),
        utm: collectUtm(),
    }
}

function markInvalid(form: HTMLFormElement, field: string, invalid: boolean): void {
    const control = form.elements.namedItem(field)
    if (control instanceof HTMLElement) {
        control.setAttribute('aria-invalid', String(invalid))
    }
}

function initLeadForm(form: HTMLFormElement): void {
    initLeadFormContext(form)
    const status = form.querySelector<HTMLElement>('.js-lead-form-status')
    const submit = form.querySelector<HTMLButtonElement>('button[type="submit"]')
    const phone = form.elements.namedItem('phone')

    const say = (text: string, failed = false): void => {
        if (status !== null) {
            status.textContent = text
            status.classList.toggle('text-red-600', failed)
            status.classList.toggle('text-slate-600', !failed)
        }
    }

    if (phone instanceof HTMLInputElement) {
        phone.addEventListener('input', () => {
            phone.value = formatPhone(phone.value)
        })
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault()

        const payload = formPayload(form)
        const errors = validateLeadFields({
            name: String(payload.name),
            phone: String(payload.phone),
            consent: payload.consent === true,
        })
        for (const field of ['name', 'phone', 'consent']) {
            markInvalid(form, field, field in errors)
        }
        const firstError = Object.values(errors)[0]
        if (firstError !== undefined) {
            say(firstError, true)
            const invalid = form.querySelector<HTMLElement>('[aria-invalid="true"]')
            invalid?.focus()

            return
        }

        say('Отправляем…')
        if (submit !== null) {
            submit.disabled = true
            submit.setAttribute('aria-busy', 'true')
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            })

            if (!response.ok) {
                say(
                    response.status === 429
                        ? 'Слишком много попыток. Подождите минуту или позвоните нам.'
                        : 'Не удалось отправить заявку. Проверьте поля или позвоните нам.',
                    true,
                )

                return
            }

            form.reset()
            initLeadFormContext(form)
            say('Спасибо! Заявка отправлена.')
        } catch {
            say('Нет связи с сервером. Проверьте интернет или позвоните нам.', true)
        } finally {
            if (submit !== null) {
                submit.disabled = false
                submit.removeAttribute('aria-busy')
            }
        }
    })
}

document.querySelectorAll<HTMLFormElement>('.js-lead-form').forEach(initLeadForm)

initMobileNav()
initCallbar()
initGalleries()
initVideoEmbeds()
initLeadPlans()
initOfficeStatus()
initCopyButtons()
initMapEmbeds()

// Swiper (~40 КБ JS + CSS) нужен только страницам со слайдером — остальным он не загружается.
if (document.querySelector('.js-site-slider') !== null) {
    void import('./slider').then((module) => module.initSiteSliders())
}
