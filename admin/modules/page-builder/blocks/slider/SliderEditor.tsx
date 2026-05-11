import type { BuilderBlock } from '../../types'
import { Button } from '../../../../shared/ui'

interface SliderEditorProps {
  block: BuilderBlock
  onChange?: (nextBlock: BuilderBlock) => void
}

const heroPreset = {
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

const catalogPreset = {
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

const seoContentPreset = {
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

export function SliderEditor({ block, onChange }: SliderEditorProps) {
  const slides = Array.isArray(block.content.items) ? block.content.items : []
  const firstSlide = (slides[0] ?? {}) as Record<string, unknown>
  const settings = block.settings as Record<string, unknown>

  const effect = settings.effect === 'fade' ? 'fade' : 'slide'
  const autoplay = settings.autoplay !== false
  const delayMs = typeof settings.delayMs === 'number' ? settings.delayMs : 4500
  const speedMs = typeof settings.speedMs === 'number' ? settings.speedMs : 500
  const canApplyPreset = onChange !== undefined

  const applyPreset = (preset: Record<string, unknown>) => {
    if (onChange === undefined) {
      return
    }

    onChange({
      ...block,
      settings: {
        ...block.settings,
        ...preset,
      },
      metadata: {
        ...block.metadata,
        updatedAt: new Date().toISOString(),
      },
    })
  }

  return (
    <div className="space-y-3 rounded-md border border-slate-200 p-3 text-xs dark:border-slate-700">
      <p>
        Блок <span className="font-semibold">{block.type}</span> поддерживает расширенные настройки.
        Редактирование выполняется через JSON-панель ниже.
      </p>

      <div className="rounded-md bg-slate-50 p-3 text-slate-700 dark:bg-slate-900 dark:text-slate-200">
        <p className="font-semibold">Быстрый статус</p>
        <ul className="mt-2 list-disc space-y-1 pl-4">
          <li>Слайдов: <span className="font-semibold">{slides.length}</span></li>
          <li>Эффект: <span className="font-semibold">{effect}</span></li>
          <li>Autoplay: <span className="font-semibold">{autoplay ? 'on' : 'off'}</span> ({delayMs}ms)</li>
          <li>Скорость анимации: <span className="font-semibold">{speedMs}ms</span></li>
        </ul>
      </div>

      <div className="rounded-md border border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-900/40 dark:bg-indigo-950/30">
        <p className="font-semibold text-indigo-900 dark:text-indigo-100">Пресеты в один клик</p>
        <p className="mt-1 text-indigo-800 dark:text-indigo-200">
          Применяют рекомендуемые `settings` без ручного редактирования JSON.
        </p>
        <div className="mt-2 flex flex-wrap gap-2">
          <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={!canApplyPreset}
            onClick={() => applyPreset(heroPreset)}
          >
            Применить Hero preset
          </Button>
          <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={!canApplyPreset}
            onClick={() => applyPreset(catalogPreset)}
          >
            Применить Catalog preset
          </Button>
          <Button
            type="button"
            size="sm"
            variant="outline"
            disabled={!canApplyPreset}
            onClick={() => applyPreset(seoContentPreset)}
          >
            Применить SEO/Content preset
          </Button>
        </div>
      </div>

      <div className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
        <p className="font-semibold">Рекомендации для эффективного слайдера</p>
        <ul className="mt-2 list-disc space-y-1 pl-4">
          <li>`effect`: `slide` для каталога, `fade` для hero.</li>
          <li>`delayMs`: 4000-5500, `speedMs`: 350-700.</li>
          <li>`pauseOnHover`: `true` на desktop, чтобы не мешать чтению.</li>
          <li>`disableOnInteraction`: `false`, если автопрокрутка должна продолжаться после свайпа.</li>
          <li>Добавляйте осмысленные `a11yLabels` для навигации и пагинации.</li>
        </ul>
      </div>

      <div className="rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <p className="font-semibold">Минимально полезный слайд</p>
        <p className="mt-1 text-slate-600 dark:text-slate-300">
          `src` + `alt` + `title` + `buttonLabel/buttonHref`.
        </p>
        <p className="mt-1 text-slate-600 dark:text-slate-300">
          Первый слайд сейчас: <span className="font-semibold">{String(firstSlide.title ?? '(без title)')}</span>.
        </p>
      </div>
    </div>
  )
}
