import { useId, useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import type { MenuItemPayload, MenuPosition } from '../../entities/menu/api'
import { usePagesQuery } from '../../entities/page/api'
import { Button, Dialog, Input, Switch } from '../../shared/ui'
import type { MenuNode } from './menu-tree'

export const HINT_MAX_LENGTH = 160

export interface MenuItemDraft {
  label: string
  url: string
  hint: string
  isActive: boolean
  parentId: string | null
}

/** Окно монтируется при открытии (MenuPage), поэтому форма каждый раз начинает с `initial`. */
interface MenuItemDialogProps {
  open: boolean
  mode: 'create' | 'edit'
  position: MenuPosition
  positionLabel: string
  initial: MenuItemDraft
  /** Разделы, в которые можно вложить пункт; пусто — только верхний уровень. */
  sections: MenuNode[]
  pending: boolean
  serverError: string | null
  onCancel: () => void
  onSubmit: (payload: MenuItemPayload) => void
}

const selectClass =
  'h-12 w-full rounded-lg border border-line-strong bg-white px-3 text-sm text-ink outline-hidden ring-brand-500 focus:ring-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100'

/** Проверка адреса — та же, что на сервере: путь, якорь или полная ссылка. */
export function urlError(url: string): string | null {
  const value = url.trim()
  if (value === '') {
    return 'Укажите адрес или выберите страницу'
  }

  return /^(\/|#|https?:\/\/)/i.test(value) ? null : 'Адрес начинается с «/», «#» или «https://»'
}

export function MenuItemDialog({ open, mode, position, positionLabel, initial, sections, pending, serverError, onCancel, onSubmit }: MenuItemDialogProps) {
  const [draft, setDraft] = useState<MenuItemDraft>(initial)
  const [touched, setTouched] = useState(false)
  const pagesQuery = usePagesQuery({ enabled: open })
  const ids = { label: useId(), url: useId(), hint: useId(), page: useId(), parent: useId(), active: useId() }

  const pages = useMemo(
    () => (pagesQuery.data ?? []).filter((page) => page.status === 'published').sort((left, right) => left.path.localeCompare(right.path)),
    [pagesQuery.data],
  )

  const labelError = draft.label.trim() === '' ? 'Укажите название' : null
  const addressError = urlError(draft.url)
  const hintError = draft.hint.trim().length > HINT_MAX_LENGTH ? `Не длиннее ${HINT_MAX_LENGTH} символов` : null
  const invalid = labelError !== null || addressError !== null || hintError !== null

  const set = <K extends keyof MenuItemDraft>(key: K, value: MenuItemDraft[K]): void => setDraft((current) => ({ ...current, [key]: value }))

  const pickPage = (path: string): void => {
    const page = pages.find((candidate) => candidate.path === path)
    if (page === undefined) {
      return
    }
    setDraft((current) => ({ ...current, url: page.path, label: current.label.trim() === '' ? page.h1 || page.title : current.label }))
  }

  const submit = (event: FormEvent): void => {
    event.preventDefault()
    setTouched(true)
    if (invalid) {
      return
    }
    onSubmit({
      position,
      label: draft.label.trim(),
      url: draft.url.trim(),
      hint: draft.hint.trim() === '' ? null : draft.hint.trim(),
      isActive: draft.isActive,
      parentId: draft.parentId,
    })
  }

  const fieldError = (message: string | null, id: string) =>
    touched && message !== null ? <p id={`${id}-error`} className="mt-1.5 text-sm text-danger">{message}</p> : null

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => (next ? undefined : onCancel())}
      title={mode === 'create' ? 'Новый пункт меню' : 'Пункт меню'}
      description={`Меню: ${positionLabel}. Изменения появятся на сайте сразу после сохранения.`}
      contentClassName="max-w-xl"
    >
      <form className="grid gap-4" onSubmit={submit} noValidate>
        {pages.length > 0 ? (
          <div>
            <label htmlFor={ids.page} className="mb-1.5 block text-sm font-semibold">Страница сайта</label>
            <select id={ids.page} className={selectClass} value={pages.some((page) => page.path === draft.url) ? draft.url : ''} onChange={(event) => pickPage(event.target.value)}>
              <option value="">Выберите страницу или впишите адрес ниже</option>
              {pages.map((page) => (
                <option key={page.id} value={page.path}>{`${page.h1 || page.title} — ${page.path}`}</option>
              ))}
            </select>
          </div>
        ) : null}

        <div>
          <label htmlFor={ids.label} className="mb-1.5 block text-sm font-semibold">Название</label>
          <Input id={ids.label} value={draft.label} maxLength={180} onChange={(event) => set('label', event.target.value)} aria-invalid={touched && labelError !== null} aria-describedby={touched && labelError !== null ? `${ids.label}-error` : undefined} placeholder="Например: Забор Жалюзи" />
          {fieldError(labelError, ids.label)}
        </div>

        <div>
          <label htmlFor={ids.url} className="mb-1.5 block text-sm font-semibold">Адрес</label>
          <Input id={ids.url} value={draft.url} maxLength={1024} onChange={(event) => set('url', event.target.value)} aria-invalid={touched && addressError !== null} aria-describedby={`${ids.url}-help`} placeholder="/zabor-jaluzi/" className="font-mono" />
          <p id={`${ids.url}-help`} className="mt-1.5 text-sm text-graphite dark:text-slate-400">Путь на сайте (/zabor/), якорь (#contacts) или полная ссылка (https://…).</p>
          {fieldError(addressError, ids.url)}
        </div>

        <div>
          <label htmlFor={ids.hint} className="mb-1.5 flex items-baseline justify-between text-sm font-semibold">
            <span>Подпись <span className="font-normal text-graphite">— необязательно</span></span>
            <span className="font-normal tabular-nums text-graphite">{draft.hint.trim().length}/{HINT_MAX_LENGTH}</span>
          </label>
          <Input id={ids.hint} value={draft.hint} onChange={(event) => set('hint', event.target.value)} aria-invalid={touched && hintError !== null} placeholder="Например: от 3 400 ₽/м²" />
          <p className="mt-1.5 text-sm text-graphite dark:text-slate-400">В раскрытом меню шапки цена («от 3 400 ₽/м²») встанет справа от названия, другой текст — под ним.</p>
          {fieldError(hintError, ids.hint)}
        </div>

        {sections.length > 0 ? (
          <div>
            <label htmlFor={ids.parent} className="mb-1.5 block text-sm font-semibold">Где показывать</label>
            <select id={ids.parent} className={selectClass} value={draft.parentId ?? ''} onChange={(event) => set('parentId', event.target.value === '' ? null : event.target.value)}>
              <option value="">Отдельным пунктом меню</option>
              {sections.map((section) => (
                <option key={section.id} value={section.id}>{`Внутри раздела «${section.label}»`}</option>
              ))}
            </select>
          </div>
        ) : null}

        <div className="flex items-center justify-between gap-4 rounded-lg bg-surface px-4 py-3 dark:bg-slate-800/60">
          <label htmlFor={ids.active} className="text-sm">
            <span className="block font-semibold">Показывать на сайте</span>
            <span className="text-graphite dark:text-slate-400">Скрытый пункт остаётся здесь, но на сайте его нет.</span>
          </label>
          <Switch id={ids.active} checked={draft.isActive} onCheckedChange={(checked) => set('isActive', checked)} />
        </div>

        {serverError !== null ? <p role="alert" className="rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger">{serverError}</p> : null}

        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onCancel}>Отмена</Button>
          <Button type="submit" disabled={pending}>{mode === 'create' ? 'Добавить пункт' : 'Сохранить'}</Button>
        </div>
      </form>
    </Dialog>
  )
}
