import '../shared/styles/app.css'
import Swiper from 'swiper'
import { Autoplay, Navigation, Pagination } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'

interface SiteSliderSettings {
  autoplay: boolean
  loop: boolean
  pagination: boolean
  navigation: boolean
  delayMs: number
}

const defaultSliderSettings: SiteSliderSettings = {
  autoplay: true,
  loop: true,
  pagination: true,
  navigation: true,
  delayMs: 4500,
}

function sliderSettings(element: HTMLElement): SiteSliderSettings {
  const payload = element.dataset.sliderSettings
  if (payload === undefined || payload === '') {
    return defaultSliderSettings
  }

  try {
    const parsed = JSON.parse(payload) as Partial<SiteSliderSettings>
    return {
      autoplay: parsed.autoplay ?? defaultSliderSettings.autoplay,
      loop: parsed.loop ?? defaultSliderSettings.loop,
      pagination: parsed.pagination ?? defaultSliderSettings.pagination,
      navigation: parsed.navigation ?? defaultSliderSettings.navigation,
      delayMs: parsed.delayMs ?? defaultSliderSettings.delayMs,
    }
  } catch {
    return defaultSliderSettings
  }
}

function initSiteSliders(): void {
  document.querySelectorAll<HTMLElement>('.js-site-slider').forEach((container) => {
    const settings = sliderSettings(container)
    const pagination = container.querySelector<HTMLElement>('.js-site-slider-pagination')
    const next = container.querySelector<HTMLElement>('.js-site-slider-next')
    const prev = container.querySelector<HTMLElement>('.js-site-slider-prev')

    new Swiper(container, {
      modules: [Autoplay, Navigation, Pagination],
      slidesPerView: 1,
      speed: 500,
      loop: settings.loop,
      autoplay: settings.autoplay
        ? {
          delay: settings.delayMs,
          disableOnInteraction: false,
          pauseOnMouseEnter: true,
        }
        : false,
      pagination: settings.pagination && pagination !== null
        ? {
          el: pagination,
          clickable: true,
        }
        : false,
      navigation: settings.navigation && next !== null && prev !== null
        ? {
          nextEl: next,
          prevEl: prev,
        }
        : false,
    })
  })
}

function formPayload(form: HTMLFormElement): Record<string, unknown> {
  const data = new FormData(form)

  return {
    source: String(data.get('source') ?? 'public_page_form'),
    name: String(data.get('name') ?? ''),
    phone: String(data.get('phone') ?? ''),
    email: String(data.get('email') ?? '') || null,
    message: String(data.get('message') ?? '') || null,
    consent: data.get('consent') === 'on',
    consentText: String(data.get('consentText') ?? ''),
    website: String(data.get('website') ?? ''),
    formLoadedAt: String(data.get('formLoadedAt') ?? ''),
    pageUrl: String(data.get('pageUrl') ?? window.location.href),
    policyUrl: String(data.get('policyUrl') ?? '/privacy/'),
  }
}

document.querySelectorAll<HTMLFormElement>('.js-lead-form').forEach((form) => {
  const status = form.querySelector<HTMLElement>('.js-lead-form-status')

  form.addEventListener('submit', async (event) => {
    event.preventDefault()
    status!.textContent = 'Отправляем...'

    const response = await fetch(form.action, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(formPayload(form)),
    })

    if (!response.ok) {
      status!.textContent = 'Не удалось отправить заявку. Проверьте поля и попробуйте снова.'
      return
    }

    form.reset()
    status!.textContent = 'Спасибо! Заявка отправлена.'
  })
})

initSiteSliders()
