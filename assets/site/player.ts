/**
 * Плеер для загруженных на сайт видео (MP4, WebM) на веб-компонентах media-chrome.
 *
 * Разметка (`templates/public/partials/video-player.html.twig`) работает и без JS: у <video> стоят
 * нативные `controls`. Модуль грузится отдельным чанком только на страницах с плеером, регистрирует
 * нужные элементы и снимает нативные кнопки, чтобы не было двух панелей управления.
 */
import 'media-chrome/media-controller'
import 'media-chrome/media-control-bar'
import 'media-chrome/media-play-button'
import 'media-chrome/media-mute-button'
import 'media-chrome/media-volume-range'
import 'media-chrome/media-time-range'
import 'media-chrome/media-time-display'
import 'media-chrome/media-playback-rate-button'
import 'media-chrome/media-pip-button'
import 'media-chrome/media-fullscreen-button'
import 'media-chrome/media-loading-indicator'
import { En } from 'media-chrome/lang/en'
import { addTranslation, setLanguage } from 'media-chrome/utils/i18n'
import { RU_PLAYER_LABELS } from './player-ru'

addTranslation('ru', { ...En, ...RU_PLAYER_LABELS })
setLanguage('ru')

export function initVideoPlayers(root: ParentNode = document): void {
    root.querySelectorAll<HTMLElement>('[data-zp-player]').forEach((player) => {
        const video = player.querySelector<HTMLVideoElement>('video[slot="media"]')
        if (video === null) {
            return
        }

        video.removeAttribute('controls')
        player.classList.add('is-ready')

        // Один ролик за раз: запуск видео ставит на паузу остальные плееры страницы.
        video.addEventListener('play', () => {
            document.querySelectorAll<HTMLVideoElement>('[data-zp-player] video').forEach((other) => {
                if (other !== video && !other.paused) {
                    other.pause()
                }
            })
        })
    })
}
