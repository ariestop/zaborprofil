import '../shared/styles/app.css'
import Swiper from 'swiper'
import { A11y, Autoplay, EffectCards, EffectCoverflow, EffectFade, Navigation, Pagination } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/effect-cards'
import 'swiper/css/effect-coverflow'
import 'swiper/css/effect-fade'
import 'swiper/css/navigation'
import 'swiper/css/pagination'

interface SiteSliderBreakpoints {
  mobileSlidesPerView: number
  tabletSlidesPerView: number
  desktopSlidesPerView: number
  mobileSpaceBetween: number
  tabletSpaceBetween: number
  desktopSpaceBetween: number
}

interface SiteSliderA11yLabels {
  prevSlide: string
  nextSlide: string
  paginationBullet: string
}

interface SiteSliderCardsEffect {
  perSlideOffset: number
  perSlideRotate: number
  rotate: boolean
  slideShadows: boolean
}

interface SiteSliderCoverflowEffect {
  rotate: number
  stretch: number
  depth: number
  modifier: number
  slideShadows: boolean
}

interface SiteSliderSettings {
  autoplay: boolean
  loop: boolean
  pagination: boolean
  navigation: boolean
  delayMs: number
  effect: 'slide' | 'fade' | 'cards' | 'coverflow'
  pauseOnHover: boolean
  disableOnInteraction: boolean
  speedMs: number
  breakpoints: SiteSliderBreakpoints
  a11yLabels: SiteSliderA11yLabels
  cardsEffect: SiteSliderCardsEffect
  coverflowEffect: SiteSliderCoverflowEffect
}

const defaultSliderSettings: SiteSliderSettings = {
  autoplay: true,
  loop: true,
  pagination: true,
  navigation: true,
  delayMs: 4500,
  effect: 'slide',
  pauseOnHover: true,
  disableOnInteraction: false,
  speedMs: 500,
  breakpoints: {
    mobileSlidesPerView: 1,
    tabletSlidesPerView: 1,
    desktopSlidesPerView: 1,
    mobileSpaceBetween: 8,
    tabletSpaceBetween: 16,
    desktopSpaceBetween: 24,
  },
  a11yLabels: {
    prevSlide: 'Предыдущий слайд',
    nextSlide: 'Следующий слайд',
    paginationBullet: 'Перейти к слайду {{index}}',
  },
  cardsEffect: {
    perSlideOffset: 6,
    perSlideRotate: 1,
    rotate: true,
    slideShadows: false,
  },
  coverflowEffect: {
    rotate: 18,
    stretch: 0,
    depth: 90,
    modifier: 1,
    slideShadows: false,
  },
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
      effect: parsed.effect === 'fade' || parsed.effect === 'cards' || parsed.effect === 'coverflow'
        ? parsed.effect
        : defaultSliderSettings.effect,
      pauseOnHover: parsed.pauseOnHover ?? defaultSliderSettings.pauseOnHover,
      disableOnInteraction: parsed.disableOnInteraction ?? defaultSliderSettings.disableOnInteraction,
      speedMs: parsed.speedMs ?? defaultSliderSettings.speedMs,
      breakpoints: {
        mobileSlidesPerView: parsed.breakpoints?.mobileSlidesPerView ?? defaultSliderSettings.breakpoints.mobileSlidesPerView,
        tabletSlidesPerView: parsed.breakpoints?.tabletSlidesPerView ?? defaultSliderSettings.breakpoints.tabletSlidesPerView,
        desktopSlidesPerView: parsed.breakpoints?.desktopSlidesPerView ?? defaultSliderSettings.breakpoints.desktopSlidesPerView,
        mobileSpaceBetween: parsed.breakpoints?.mobileSpaceBetween ?? defaultSliderSettings.breakpoints.mobileSpaceBetween,
        tabletSpaceBetween: parsed.breakpoints?.tabletSpaceBetween ?? defaultSliderSettings.breakpoints.tabletSpaceBetween,
        desktopSpaceBetween: parsed.breakpoints?.desktopSpaceBetween ?? defaultSliderSettings.breakpoints.desktopSpaceBetween,
      },
      a11yLabels: {
        prevSlide: parsed.a11yLabels?.prevSlide ?? defaultSliderSettings.a11yLabels.prevSlide,
        nextSlide: parsed.a11yLabels?.nextSlide ?? defaultSliderSettings.a11yLabels.nextSlide,
        paginationBullet: parsed.a11yLabels?.paginationBullet ?? defaultSliderSettings.a11yLabels.paginationBullet,
      },
      cardsEffect: {
        perSlideOffset: parsed.cardsEffect?.perSlideOffset ?? defaultSliderSettings.cardsEffect.perSlideOffset,
        perSlideRotate: parsed.cardsEffect?.perSlideRotate ?? defaultSliderSettings.cardsEffect.perSlideRotate,
        rotate: parsed.cardsEffect?.rotate ?? defaultSliderSettings.cardsEffect.rotate,
        slideShadows: parsed.cardsEffect?.slideShadows ?? defaultSliderSettings.cardsEffect.slideShadows,
      },
      coverflowEffect: {
        rotate: parsed.coverflowEffect?.rotate ?? defaultSliderSettings.coverflowEffect.rotate,
        stretch: parsed.coverflowEffect?.stretch ?? defaultSliderSettings.coverflowEffect.stretch,
        depth: parsed.coverflowEffect?.depth ?? defaultSliderSettings.coverflowEffect.depth,
        modifier: parsed.coverflowEffect?.modifier ?? defaultSliderSettings.coverflowEffect.modifier,
        slideShadows: parsed.coverflowEffect?.slideShadows ?? defaultSliderSettings.coverflowEffect.slideShadows,
      },
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
      modules: [A11y, Autoplay, EffectFade, EffectCards, EffectCoverflow, Navigation, Pagination],
      slidesPerView: settings.breakpoints.mobileSlidesPerView,
      spaceBetween: settings.breakpoints.mobileSpaceBetween,
      speed: settings.speedMs,
      loop: settings.loop,
      effect: settings.effect,
      fadeEffect: settings.effect === 'fade' ? { crossFade: true } : undefined,
      cardsEffect: settings.effect === 'cards'
        ? {
          perSlideOffset: settings.cardsEffect.perSlideOffset,
          perSlideRotate: settings.cardsEffect.perSlideRotate,
          rotate: settings.cardsEffect.rotate,
          slideShadows: settings.cardsEffect.slideShadows,
        }
        : undefined,
      coverflowEffect: settings.effect === 'coverflow'
        ? {
          rotate: settings.coverflowEffect.rotate,
          stretch: settings.coverflowEffect.stretch,
          depth: settings.coverflowEffect.depth,
          modifier: settings.coverflowEffect.modifier,
          slideShadows: settings.coverflowEffect.slideShadows,
        }
        : undefined,
      autoplay: settings.autoplay
        ? {
          delay: settings.delayMs,
          disableOnInteraction: settings.disableOnInteraction,
          pauseOnMouseEnter: settings.pauseOnHover,
        }
        : false,
      breakpoints: {
        768: {
          slidesPerView: settings.breakpoints.tabletSlidesPerView,
          spaceBetween: settings.breakpoints.tabletSpaceBetween,
        },
        1280: {
          slidesPerView: settings.breakpoints.desktopSlidesPerView,
          spaceBetween: settings.breakpoints.desktopSpaceBetween,
        },
      },
      a11y: {
        prevSlideMessage: settings.a11yLabels.prevSlide,
        nextSlideMessage: settings.a11yLabels.nextSlide,
        paginationBulletMessage: settings.a11yLabels.paginationBullet,
      },
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
