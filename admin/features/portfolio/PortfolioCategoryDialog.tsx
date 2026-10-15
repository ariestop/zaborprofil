import { useId, useState, type FormEvent } from 'react'
import type { PortfolioCategoryPayload } from '../../entities/portfolio/api'
import { Button, Dialog, Input, Textarea } from '../../shared/ui'
import { PORTFOLIO_LIMITS, slugify } from './portfolio-utils'

export interface CategoryDraft {
  title: string
  slug: string
  shortTitle: string
  description: string
}

interface PortfolioCategoryDialogProps {
  mode: 'create' | 'edit'
  initial: CategoryDraft
  pending: boolean
  serverError: string | null
  onCancel: () => void
  onSubmit: (payload: PortfolioCategoryPayload) => void
}

/** Раздел портфолио («Заборы из профнастила», «Ворота»): второй уровень навигации на сайте — /portfolio/<адрес>/. */
export function PortfolioCategoryDialog({ mode, initial, pending, serverError, onCancel, onSubmit }: PortfolioCategoryDialogProps) {
  const [draft, setDraft] = useState(initial)
  const [slugEdited, setSlugEdited] = useState(mode === 'edit')
  const [touched, setTouched] = useState(false)
  const ids = { title: useId(), slug: useId(), short: useId(), description: useId() }

  const slug = slugEdited ? draft.slug : slugify(draft.title)
  const titleError = draft.title.trim() === '' ? 'Укажите название раздела' : null
  const slugError = /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug) ? null : 'Только латиница, цифры и дефисы, например zabory-iz-profnastila'
  const invalid = titleError !== null || slugError !== null

  const submit = (event: FormEvent): void => {
    event.preventDefault()
    setTouched(true)
    if (invalid) {
      return
    }
    onSubmit({
      title: draft.title.trim(),
      slug,
      shortTitle: draft.shortTitle.trim() === '' ? null : draft.shortTitle.trim(),
      description: draft.description.trim() === '' ? null : draft.description.trim(),
    })
  }

  const error = (message: string | null, id: string) =>
    touched && message !== null ? <p id={`${id}-error`} className="mt-1.5 text-sm text-danger">{message}</p> : null

  return (
    <Dialog
      open
      onOpenChange={(next) => (next ? undefined : onCancel())}
      title={mode === 'create' ? 'Новый раздел портфолио' : 'Раздел портфолио'}
      description="Раздел объединяет похожие работы. На сайте он открывается по адресу /portfolio/<адрес раздела>/."
      contentClassName="max-w-xl"
    >
      <form className="grid gap-4" onSubmit={submit} noValidate>
        <div>
          <label htmlFor={ids.title} className="mb-1.5 block text-sm font-semibold">Название</label>
          <Input
            id={ids.title}
            value={draft.title}
            maxLength={PORTFOLIO_LIMITS.categoryTitle}
            placeholder="Например: Заборы из профнастила"
            aria-invalid={touched && titleError !== null}
            onChange={(event) => setDraft((current) => ({ ...current, title: event.target.value }))}
          />
          {error(titleError, ids.title)}
        </div>
        <div>
          <label htmlFor={ids.slug} className="mb-1.5 block text-sm font-semibold">Адрес раздела</label>
          <div className="flex items-center rounded-lg border border-line-strong bg-surface pl-3 text-sm text-graphite focus-within:ring-2 focus-within:ring-brand-500 dark:border-slate-700 dark:bg-slate-800">
            <span className="shrink-0 font-mono">/portfolio/</span>
            <input
              id={ids.slug}
              value={slug}
              maxLength={120}
              aria-invalid={touched && slugError !== null}
              onChange={(event) => {
                setSlugEdited(true)
                setDraft((current) => ({ ...current, slug: event.target.value.toLowerCase() }))
              }}
              className="h-12 min-w-0 flex-1 rounded-r-lg bg-white px-1 font-mono text-ink outline-hidden dark:bg-slate-900 dark:text-slate-100"
            />
          </div>
          {error(slugError, ids.slug)}
        </div>
        <div>
          <label htmlFor={ids.short} className="mb-1.5 block text-sm font-semibold">
            Короткое название <span className="font-normal text-graphite">— необязательно</span>
          </label>
          <Input
            id={ids.short}
            value={draft.shortTitle}
            maxLength={PORTFOLIO_LIMITS.categoryShortTitle}
            placeholder="Например: Профнастил"
            onChange={(event) => setDraft((current) => ({ ...current, shortTitle: event.target.value }))}
          />
          <p className="mt-1.5 text-sm text-graphite dark:text-slate-400">Для плиток и фильтров на сайте, где длинное название не помещается.</p>
        </div>
        <div>
          <label htmlFor={ids.description} className="mb-1.5 flex items-baseline justify-between text-sm font-semibold">
            <span>Описание <span className="font-normal text-graphite">— необязательно</span></span>
            <span className="font-normal tabular-nums text-graphite">{draft.description.trim().length}/{PORTFOLIO_LIMITS.categoryDescription}</span>
          </label>
          <Textarea
            id={ids.description}
            rows={3}
            value={draft.description}
            maxLength={PORTFOLIO_LIMITS.categoryDescription}
            placeholder="Пара предложений над списком работ раздела и в описании для поисковиков."
            onChange={(event) => setDraft((current) => ({ ...current, description: event.target.value }))}
          />
        </div>
        {serverError !== null ? <p role="alert" className="rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger">{serverError}</p> : null}
        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onCancel}>Отмена</Button>
          <Button type="submit" disabled={pending}>{mode === 'create' ? 'Создать раздел' : 'Сохранить'}</Button>
        </div>
      </form>
    </Dialog>
  )
}
