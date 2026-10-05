export type SliderPreset = Record<string, unknown>

export const heroPreset: SliderPreset = {
  effect: 'fade',
  autoplay: true,
  loop: true,
  pagination: true,
  navigation: true,
  delayMs: 5000,
  pauseOnHover: true,
  disableOnInteraction: false,
  speedMs: 650,
  breakpoints: {
    mobileSlidesPerView: 1,
    tabletSlidesPerView: 1,
    desktopSlidesPerView: 1,
    mobileSpaceBetween: 8,
    tabletSpaceBetween: 16,
    desktopSpaceBetween: 24,
  },
}

export const catalogPreset: SliderPreset = {
  effect: 'slide',
  autoplay: false,
  loop: false,
  pagination: true,
  navigation: true,
  delayMs: 4500,
  pauseOnHover: true,
  disableOnInteraction: false,
  speedMs: 450,
  breakpoints: {
    mobileSlidesPerView: 1,
    tabletSlidesPerView: 2,
    desktopSlidesPerView: 3,
    mobileSpaceBetween: 8,
    tabletSpaceBetween: 16,
    desktopSpaceBetween: 20,
  },
}

export const seoContentPreset: SliderPreset = {
  effect: 'fade',
  autoplay: false,
  loop: false,
  pagination: true,
  navigation: true,
  delayMs: 6000,
  pauseOnHover: true,
  disableOnInteraction: false,
  speedMs: 550,
  breakpoints: {
    mobileSlidesPerView: 1,
    tabletSlidesPerView: 1,
    desktopSlidesPerView: 1,
    mobileSpaceBetween: 8,
    tabletSpaceBetween: 12,
    desktopSpaceBetween: 16,
  },
}
