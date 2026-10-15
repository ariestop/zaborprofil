import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import { SortableContext, rectSortingStrategy, sortableKeyboardCoordinates, useSortable } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { useEffect, useId, useRef, useState, type ChangeEvent, type DragEvent, type ReactNode } from 'react'
import type { PortfolioMedia } from '../../entities/portfolio/api'
import { MediaPickerDialog } from '../media/MediaPickerDialog'
import { MediaUploadList } from '../media/MediaUploadList'
import { useMediaUploads } from '../media/useMediaUploads'
import { isImageAsset, isVideoAsset, VIDEO_ACCEPT } from '../media/utils'
import { Button, Input } from '../../shared/ui'
import { cn } from '../../shared/lib/cn'
import type { MediaAssetItem } from '../../types/api'
import { coverIndex, mediaKey, moveMedia, newMedia, parseVideoLink, PORTFOLIO_LIMITS, providerLabel, setCover } from './portfolio-utils'

/** Папка медиатеки для файлов, загруженных из карточки работы. */
export const PORTFOLIO_MEDIA_FOLDER = 'Портфолио'

const PHOTO_ACCEPT = '.jpg,.jpeg,.png,.webp,.avif,image/jpeg,image/png,image/webp,image/avif'

interface ProjectMediaEditorProps {
  media: PortfolioMedia[]
  onChange: (media: PortfolioMedia[]) => void
  disabled?: boolean
}

function Icon({ children, size = 18 }: { children: ReactNode; size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.9} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {children}
    </svg>
  )
}

const tileButton =
  'flex size-10 items-center justify-center rounded-lg bg-white/95 text-ink shadow-sm transition hover:bg-white focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 disabled:opacity-40 dark:bg-slate-900/95 dark:text-slate-100'

function kindLabel(item: PortfolioMedia): string {
  if (item.kind === 'photo') {
    return 'Фото'
  }

  return item.kind === 'video' ? 'Видео' : providerLabel(item.src)
}

function TilePreview({ item }: { item: PortfolioMedia }) {
  if (item.kind === 'photo') {
    return <img src={item.src} alt="" loading="lazy" className="size-full object-cover" />
  }

  if (item.kind === 'video' && item.poster === null) {
    return <video src={`${item.src}#t=0.5`} preload="metadata" muted playsInline className="size-full bg-night object-cover" />
  }

  return (
    <div className="relative flex size-full items-center justify-center bg-night">
      {item.poster !== null ? <img src={item.poster} alt="" loading="lazy" className="absolute inset-0 size-full object-cover opacity-80" /> : null}
      {item.kind === 'embed' && item.poster === null ? (
        <span className="text-sm font-semibold tracking-wide text-white/80">{providerLabel(item.src)}</span>
      ) : null}
    </div>
  )
}

function SortableTile({
  tile,
  tileKey,
  index,
  total,
  isCover,
  disabled,
  onCover,
  onCaption,
  onPoster,
  onMove,
  onRemove,
}: {
  tile: PortfolioMedia
  tileKey: string
  index: number
  total: number
  isCover: boolean
  disabled: boolean
  onCover: () => void
  onCaption: (caption: string) => void
  onPoster: () => void
  onMove: (to: number) => void
  onRemove: () => void
}) {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({ id: tileKey, disabled })
  const captionId = useId()
  const label = `${kindLabel(tile)} ${index + 1} из ${total}`

  return (
    <li
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn(
        'group relative flex min-w-0 flex-col overflow-hidden rounded-xl border bg-white dark:bg-slate-900',
        isCover ? 'border-brand-600 ring-2 ring-brand-600/30' : 'border-line dark:border-slate-700',
        isDragging && 'z-10 shadow-xl',
      )}
      data-testid="portfolio-media-tile"
      aria-label={label}
    >
      <div className="relative aspect-4/3 overflow-hidden bg-surface-strong dark:bg-slate-800">
        <TilePreview item={tile} />
        <button
          ref={setActivatorNodeRef}
          type="button"
          className="absolute inset-0 cursor-grab touch-none focus-visible:outline-hidden focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-brand-500 active:cursor-grabbing"
          aria-label={`Перетащить: ${label}`}
          disabled={disabled}
          {...attributes}
          {...listeners}
        />
        <span className="pointer-events-none absolute top-2 left-2 rounded-md bg-night/80 px-2 py-1 text-xs font-semibold text-white">
          {tile.kind === 'photo' ? index + 1 : `▶ ${kindLabel(tile)}`}
        </span>
        {isCover ? (
          <span className="pointer-events-none absolute bottom-2 left-2 rounded-md bg-badge px-2 py-1 text-xs font-bold text-ink">★ Главное фото</span>
        ) : null}
        <div className="absolute top-2 right-2 flex gap-1.5">
          {tile.kind === 'photo' ? (
            <button
              type="button"
              className={cn(tileButton, isCover && 'text-badge')}
              aria-label={isCover ? 'Это главное фото' : 'Сделать главным фото'}
              aria-pressed={isCover}
              title={isCover ? 'Главное фото работы' : 'Сделать главным фото'}
              disabled={disabled}
              onClick={onCover}
            >
              <svg width={18} height={18} viewBox="0 0 24 24" fill={isCover ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={1.9} strokeLinejoin="round" aria-hidden="true">
                <path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z" />
              </svg>
            </button>
          ) : (
            <button type="button" className={tileButton} aria-label="Выбрать обложку видео" title="Обложка видео" disabled={disabled} onClick={onPoster}>
              <Icon>
                <rect x="3.5" y="5" width="17" height="14" rx="2" />
                <circle cx="9" cy="10" r="1.6" />
                <path d="M20.5 15.5l-4.5-4.5-8 8" />
              </Icon>
            </button>
          )}
          <button type="button" className={tileButton} aria-label={`Убрать: ${label}`} title="Убрать из работы" disabled={disabled} onClick={onRemove}>
            <Icon>
              <path d="M5 7h14M10 11v6M14 11v6M6.5 7l1 12.5h9l1-12.5M9.5 7V4.5h5V7" />
            </Icon>
          </button>
        </div>
      </div>
      <div className="flex items-center gap-1 p-2">
        <label htmlFor={captionId} className="sr-only">{`Подпись: ${label}`}</label>
        <input
          id={captionId}
          value={tile.caption ?? ''}
          maxLength={PORTFOLIO_LIMITS.caption}
          placeholder="Подпись"
          disabled={disabled}
          onChange={(event) => onCaption(event.target.value)}
          className="h-10 min-w-0 flex-1 rounded-lg border border-transparent bg-transparent px-2 text-sm text-ink outline-hidden placeholder:text-graphite/70 hover:border-line focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 dark:text-slate-100"
        />
        <button type="button" className="flex size-10 shrink-0 items-center justify-center rounded-lg text-graphite hover:bg-surface-strong disabled:opacity-30 dark:hover:bg-slate-800" aria-label={`Сдвинуть влево: ${label}`} disabled={disabled || index === 0} onClick={() => onMove(index - 1)}>
          <Icon size={16}><path d="M14.5 6l-6 6 6 6" /></Icon>
        </button>
        <button type="button" className="flex size-10 shrink-0 items-center justify-center rounded-lg text-graphite hover:bg-surface-strong disabled:opacity-30 dark:hover:bg-slate-800" aria-label={`Сдвинуть вправо: ${label}`} disabled={disabled || index === total - 1} onClick={() => onMove(index + 1)}>
          <Icon size={16}><path d="M9.5 6l6 6-6 6" /></Icon>
        </button>
      </div>
    </li>
  )
}

/**
 * Галерея работы: фото, загруженные видео и ссылки VK/Rutube в одном списке.
 * Порядок меняется перетаскиванием (мышь, палец, клавиатура) или стрелками; звёздочка отмечает главное фото —
 * обложку работы в списках и превью в соцсетях. Файлы загружаются в медиатеку (папка «Портфолио»).
 */
export function ProjectMediaEditor({ media, onChange, disabled = false }: ProjectMediaEditorProps) {
  const [picker, setPicker] = useState<null | 'photo' | 'video' | { posterFor: number }>(null)
  const [link, setLink] = useState('')
  const [linkError, setLinkError] = useState<string | null>(null)
  const [dragging, setDragging] = useState(false)
  const photoInput = useRef<HTMLInputElement>(null)
  const videoInput = useRef<HTMLInputElement>(null)
  const linkId = useId()
  // Загрузки завершаются асинхронно, по одной: добавляем к последнему известному списку, а не к снимку из замыкания.
  const mediaRef = useRef(media)
  useEffect(() => {
    mediaRef.current = media
  }, [media])

  const keys = media.map(mediaKey)
  const cover = coverIndex(media)
  const full = media.length >= PORTFOLIO_LIMITS.media

  const append = (items: PortfolioMedia[]): void => {
    const current = mediaRef.current
    const room = PORTFOLIO_LIMITS.media - current.length
    if (room <= 0) {
      return
    }
    const fresh = items.filter((item) => !current.some((existing) => existing.kind === item.kind && existing.src === item.src)).slice(0, room)
    if (fresh.length > 0) {
      const next = [...current, ...fresh]
      mediaRef.current = next
      onChange(next)
    }
  }

  const fromAsset = (asset: MediaAssetItem): PortfolioMedia | null => {
    if (isImageAsset(asset)) {
      return newMedia('photo', asset.publicPath)
    }
    if (isVideoAsset(asset)) {
      return newMedia('video', asset.publicPath)
    }

    return null
  }

  const uploads = useMediaUploads((asset) => {
    const item = fromAsset(asset)
    if (item !== null) {
      append([item])
    }
  }, PORTFOLIO_MEDIA_FOLDER)

  const update = (index: number, patch: Partial<PortfolioMedia>): void => {
    onChange(media.map((item, position) => (position === index ? { ...item, ...patch } : item)))
  }

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  const onDragEnd = ({ active, over }: DragEndEvent): void => {
    if (over === null || active.id === over.id) {
      return
    }
    const from = keys.indexOf(String(active.id))
    const to = keys.indexOf(String(over.id))
    onChange(moveMedia(media, from, to))
  }

  const addLink = (): void => {
    const parsed = parseVideoLink(link)
    if (parsed === null) {
      setLinkError('Вставьте ссылку на видео VK или Rutube, например https://rutube.ru/video/… или https://vkvideo.ru/video-123_456')
      return
    }
    if (media.some((item) => item.kind === 'embed' && item.src === parsed.embedUrl)) {
      setLinkError('Это видео уже есть в работе')
      return
    }
    append([newMedia('embed', parsed.embedUrl)])
    setLink('')
    setLinkError(null)
  }

  const onFiles = (event: ChangeEvent<HTMLInputElement>): void => {
    if (event.target.files !== null) {
      uploads.addFiles(Array.from(event.target.files))
    }
    event.target.value = ''
  }

  const onDrop = (event: DragEvent<HTMLDivElement>): void => {
    if (!Array.from(event.dataTransfer.types).includes('Files')) {
      return
    }
    event.preventDefault()
    setDragging(false)
    uploads.addFiles(Array.from(event.dataTransfer.files))
  }

  const parsedLink = link.trim() === '' ? null : parseVideoLink(link)

  return (
    <section aria-label="Фото и видео работы" className="grid gap-4">
      <div
        className={cn(
          'grid gap-3 rounded-[14px] border-2 border-dashed p-4 transition',
          dragging ? 'border-brand-600 bg-brand-50 dark:bg-brand-950/30' : 'border-line-strong dark:border-slate-700',
        )}
        onDragOver={(event) => {
          if (Array.from(event.dataTransfer.types).includes('Files')) {
            event.preventDefault()
            setDragging(true)
          }
        }}
        onDragLeave={() => setDragging(false)}
        onDrop={onDrop}
        data-testid="portfolio-dropzone"
      >
        <p className="text-sm text-graphite dark:text-slate-400">
          Перетащите сюда фото (JPG, PNG, WebP до 10 МБ) и видео (MP4, WebM до 60 МБ) или выберите кнопками.
          Файлы попадут в медиатеку, в папку «{PORTFOLIO_MEDIA_FOLDER}».
        </p>
        <div className="flex flex-wrap gap-2">
          <Button type="button" onClick={() => photoInput.current?.click()} disabled={disabled || full}>
            Загрузить фото
          </Button>
          <Button type="button" variant="outline" onClick={() => videoInput.current?.click()} disabled={disabled || full}>
            Загрузить видео
          </Button>
          <Button type="button" variant="ghost" onClick={() => setPicker('photo')} disabled={disabled || full}>
            Фото из медиатеки
          </Button>
          <Button type="button" variant="ghost" onClick={() => setPicker('video')} disabled={disabled || full}>
            Видео из медиатеки
          </Button>
          <input ref={photoInput} type="file" accept={PHOTO_ACCEPT} multiple hidden onChange={onFiles} data-testid="portfolio-photo-input" />
          <input ref={videoInput} type="file" accept={VIDEO_ACCEPT} multiple hidden onChange={onFiles} data-testid="portfolio-video-input" />
        </div>
        <div>
          <label htmlFor={linkId} className="mb-1.5 block text-sm font-semibold">Видео с VK или Rutube</label>
          <div className="flex flex-col gap-2 sm:flex-row">
            <Input
              id={linkId}
              value={link}
              placeholder="https://rutube.ru/video/… или https://vkvideo.ru/video-…"
              disabled={disabled || full}
              aria-invalid={linkError !== null}
              aria-describedby={`${linkId}-help`}
              onChange={(event) => {
                setLink(event.target.value)
                setLinkError(null)
              }}
              onKeyDown={(event) => {
                if (event.key === 'Enter') {
                  event.preventDefault()
                  addLink()
                }
              }}
            />
            <Button type="button" variant="outline" onClick={addLink} disabled={disabled || full || link.trim() === ''} className="shrink-0">
              Добавить видео
            </Button>
          </div>
          <p id={`${linkId}-help`} className={cn('mt-1.5 text-sm', linkError !== null ? 'text-danger' : 'text-graphite dark:text-slate-400')} role={linkError !== null ? 'alert' : undefined}>
            {linkError ?? (parsedLink !== null ? `Распознано: ${parsedLink.provider === 'rutube' ? 'Rutube' : 'VK Видео'}. Нажмите «Добавить видео».` : 'Скопируйте адрес страницы видео из браузера или кнопки «Поделиться».')}
          </p>
        </div>
        {uploads.items.length > 0 ? (
          <MediaUploadList items={uploads.items} onCancel={uploads.cancel} onRetry={uploads.retry} onClearFinished={uploads.clearFinished} />
        ) : null}
      </div>

      {media.length === 0 ? (
        <p className="rounded-[14px] bg-surface px-4 py-6 text-center text-sm text-graphite dark:bg-slate-800/60 dark:text-slate-400">
          Пока нет ни одного фото. Для публикации нужно хотя бы одно — оно станет главным.
        </p>
      ) : (
        <>
          <p className="flex flex-wrap items-baseline justify-between gap-2 text-sm text-graphite dark:text-slate-400">
            <span>Перетащите карточку, чтобы поменять порядок. ★ — главное фото: обложка работы в списках.</span>
            <span className="tabular-nums">{media.length}/{PORTFOLIO_LIMITS.media}</span>
          </p>
          <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
            <SortableContext items={keys} strategy={rectSortingStrategy}>
              <ul className="grid grid-cols-1 gap-3 min-[420px]:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                {media.map((tile, index) => (
                  <SortableTile
                    key={keys[index]}
                    tile={tile}
                    tileKey={keys[index]}
                    index={index}
                    total={media.length}
                    isCover={index === cover}
                    disabled={disabled}
                    onCover={() => onChange(setCover(media, index))}
                    onCaption={(caption) => update(index, { caption })}
                    onPoster={() => setPicker({ posterFor: index })}
                    onMove={(to) => onChange(moveMedia(media, index, to))}
                    onRemove={() => onChange(media.filter((_, position) => position !== index))}
                  />
                ))}
              </ul>
            </SortableContext>
          </DndContext>
        </>
      )}

      <MediaPickerDialog
        open={picker !== null}
        onOpenChange={(open) => (open ? undefined : setPicker(null))}
        imagesOnly={picker !== 'video'}
        videosOnly={picker === 'video'}
        title={picker === 'video' ? 'Видео из медиатеки' : typeof picker === 'object' && picker !== null ? 'Обложка видео' : 'Фото из медиатеки'}
        onSelect={(asset) => {
          if (typeof picker === 'object' && picker !== null) {
            if (isImageAsset(asset)) {
              update(picker.posterFor, { poster: asset.publicPath })
            }
            return
          }
          const item = fromAsset(asset)
          if (item !== null) {
            append([item])
          }
        }}
      />
    </section>
  )
}
