import { useMemo, useState } from 'react'
import { useToast } from '../app/providers/toast-provider'
import {
  useCreateMenuItemMutation,
  useDeleteMenuItemMutation,
  useMenuQuery,
  useReorderMenuMutation,
  useUpdateMenuItemMutation,
  type MenuItem,
  type MenuItemPayload,
  type MenuPosition,
  type MenuPositionOption,
} from '../entities/menu/api'
import { MenuItemDialog, type MenuItemDraft } from '../features/menu/MenuItemDialog'
import { MenuTreeEditor } from '../features/menu/MenuTreeEditor'
import { buildTree, displayUrl, toOrder, type MenuNode } from '../features/menu/menu-tree'
import { describeApiError } from '../features/seo/redirects/redirect-rules'
import { NavIcon } from '../layouts/nav-icons'
import { cn } from '../shared/lib/cn'
import { Button, ConfirmDialog, ErrorState, PageLoadingState } from '../shared/ui'

const FALLBACK_POSITIONS: MenuPositionOption[] = [
  { value: 'header', label: 'Шапка сайта' },
  { value: 'footer', label: 'Подвал' },
  { value: 'service', label: 'Верхняя строка' },
]

const POSITION_HINTS: Record<MenuPosition, string> = {
  header: 'Главное меню сайта. Разделы с подпунктами раскрываются при наведении, на телефоне — в боковом меню.',
  footer: 'Ссылки в подвале сайта, под заголовком «Разделы».',
  service: 'Тонкая строка над шапкой на компьютере: второстепенные ссылки.',
}

const EMPTY_DRAFT: MenuItemDraft = { label: '', url: '', hint: '', isActive: true, parentId: null }

type DialogState =
  | { mode: 'create'; draft: MenuItemDraft }
  | { mode: 'edit'; item: MenuItem; draft: MenuItemDraft }

/** Как пункты увидит посетитель: только видимые, разделы и их подпункты. */
function MenuPreview({ tree }: { tree: MenuNode[] }) {
  const visible = tree.filter((node) => node.isActive)

  return (
    <section aria-label="Предпросмотр" className="rounded-[14px] border border-line bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
      <h2 className="text-sm font-semibold tracking-wide text-graphite uppercase dark:text-slate-400">Так увидит посетитель</h2>
      {visible.length === 0 ? (
        <p className="mt-3 text-sm text-graphite dark:text-slate-400">Видимых пунктов нет.</p>
      ) : (
        <ul className="mt-3 grid gap-3">
          {visible.map((node) => {
            const children = node.children.filter((child) => child.isActive)
            return (
              <li key={node.id}>
                <span className="flex items-center gap-1.5 font-semibold text-ink dark:text-slate-100">
                  {node.label}
                  {children.length > 0 ? <NavIcon name="chevron" size={14} /> : null}
                </span>
                {children.length > 0 ? (
                  <ul className="mt-1.5 grid gap-1 border-l-2 border-brand-200 pl-3 dark:border-brand-900">
                    {children.map((child) => (
                      <li key={child.id} className="text-sm text-ink dark:text-slate-200">
                        {child.label}
                        {child.hint !== null ? <span className="block text-xs text-graphite dark:text-slate-400">{child.hint}</span> : null}
                      </li>
                    ))}
                  </ul>
                ) : null}
              </li>
            )
          })}
        </ul>
      )}
    </section>
  )
}

/** Меню сайта: шапка, подвал и верхняя строка — создание, правка, порядок, вложенность и удаление пунктов. */
export default function MenuPage() {
  const menuQuery = useMenuQuery()
  const createMutation = useCreateMenuItemMutation()
  const updateMutation = useUpdateMenuItemMutation()
  const deleteMutation = useDeleteMenuItemMutation()
  const reorderMutation = useReorderMenuMutation()
  const { push } = useToast()
  const [position, setPosition] = useState<MenuPosition>('header')
  const [dialog, setDialog] = useState<DialogState | null>(null)
  const [dialogError, setDialogError] = useState<string | null>(null)
  const [toDelete, setToDelete] = useState<{ item: MenuItem; childrenCount: number } | null>(null)

  const positions = menuQuery.data?.positions ?? FALLBACK_POSITIONS
  const items = useMemo(() => menuQuery.data?.items ?? [], [menuQuery.data])
  const tree = useMemo(() => buildTree(items, position), [items, position])
  const positionLabel = positions.find((option) => option.value === position)?.label ?? position
  const busy = reorderMutation.isPending || updateMutation.isPending || deleteMutation.isPending

  const fail = (title: string) => (error: unknown) => push({ title, description: describeApiError(error, 'Обновите страницу и повторите.') })

  const reorder = (next: MenuNode[]): void => {
    reorderMutation.mutate({ position, items: toOrder(next) }, { onError: fail('Не удалось изменить порядок') })
  }

  const payloadOf = (item: MenuItem, overrides: Partial<MenuItemPayload> = {}): MenuItemPayload => ({
    position: item.position,
    label: item.label,
    url: item.url,
    hint: item.hint,
    isActive: item.isActive,
    parentId: item.parentId,
    ...overrides,
  })

  const openCreate = (parentId: string | null = null): void => {
    setDialogError(null)
    setDialog({ mode: 'create', draft: { ...EMPTY_DRAFT, parentId } })
  }

  const openEdit = (item: MenuItem): void => {
    setDialogError(null)
    setDialog({ mode: 'edit', item, draft: { label: item.label, url: item.url, hint: item.hint ?? '', isActive: item.isActive, parentId: item.parentId } })
  }

  const submitDialog = (payload: MenuItemPayload): void => {
    if (dialog === null) {
      return
    }
    const done = (title: string) => () => {
      setDialog(null)
      push({ title, description: `«${payload.label}»` })
    }
    const onError = (error: unknown) => setDialogError(describeApiError(error, 'Проверьте поля и повторите.'))

    if (dialog.mode === 'create') {
      createMutation.mutate(payload, { onSuccess: done('Пункт добавлен'), onError })
    } else {
      updateMutation.mutate({ id: dialog.item.id, payload }, { onSuccess: done('Пункт сохранён'), onError })
    }
  }

  const sectionsForDialog = (): MenuNode[] => {
    if (dialog?.mode === 'edit') {
      const own = tree.find((node) => node.id === dialog.item.id)
      if (own !== undefined && own.children.length > 0) {
        return []
      }
      return tree.filter((node) => node.id !== dialog.item.id)
    }
    return tree
  }

  return (
    <div className="mx-auto flex w-full max-w-[1200px] flex-col gap-5">
      <div className="flex flex-col items-start gap-3 sm:flex-row">
        <div className="min-w-0 flex-1">
          <h1 className="text-[26px] font-bold tracking-[-0.01em]">Меню сайта</h1>
          <p className="mt-1 max-w-2xl text-graphite dark:text-slate-400">
            Пункты шапки, подвала и верхней строки. Порядок меняется перетаскиванием или стрелками, изменения сразу видны на сайте.
          </p>
        </div>
        <Button type="button" onClick={() => openCreate()} className="gap-1.5">
          <NavIcon name="plus" size={18} />
          Добавить пункт
        </Button>
      </div>

      <div role="tablist" aria-label="Меню" className="inline-flex max-w-full gap-1 self-start overflow-x-auto rounded-xl bg-surface-strong p-1 dark:bg-slate-800">
        {positions.map((option) => {
          const count = items.filter((item) => item.position === option.value).length
          const selected = option.value === position
          return (
            <button
              key={option.value}
              type="button"
              role="tab"
              aria-selected={selected}
              onClick={() => setPosition(option.value)}
              className={cn(
                'flex min-h-10 shrink-0 items-center gap-1.5 rounded-lg px-3.5 text-sm font-semibold whitespace-nowrap transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500',
                selected ? 'bg-white text-ink shadow-sm dark:bg-slate-900 dark:text-slate-100' : 'text-graphite hover:text-ink dark:text-slate-400',
              )}
            >
              {option.label}
              <span className="tabular-nums text-graphite/80">{count}</span>
            </button>
          )
        })}
      </div>

      {menuQuery.isPending ? <PageLoadingState /> : null}
      {menuQuery.isError ? <ErrorState title="Не удалось загрузить меню" description="Обновите страницу или попробуйте позже." /> : null}

      {menuQuery.isSuccess ? (
        <div className="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
          <section aria-label={positionLabel} className="grid min-w-0 grid-cols-1 gap-3">
            <p className="text-sm text-graphite dark:text-slate-400">{POSITION_HINTS[position]}</p>
            {tree.length === 0 ? (
              <div className="rounded-[14px] border border-dashed border-line-strong bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
                <p className="font-semibold">В этом меню пока нет пунктов</p>
                <p className="mt-1 text-sm text-graphite dark:text-slate-400">
                  {position === 'header' ? 'Пока шапка пустая, сайт показывает основные разделы по умолчанию.' : 'Добавьте первый пункт — он сразу появится на сайте.'}
                </p>
                <Button type="button" variant="outline" className="mt-4" onClick={() => openCreate()}>Добавить пункт</Button>
              </div>
            ) : (
              <MenuTreeEditor
                tree={tree}
                positions={positions}
                disabled={busy}
                onReorder={reorder}
                onEdit={openEdit}
                onAddChild={(section) => openCreate(section.id)}
                onToggleActive={(item) =>
                  updateMutation.mutate(
                    { id: item.id, payload: payloadOf(item, { isActive: !item.isActive }) },
                    { onSuccess: () => push({ title: item.isActive ? 'Пункт скрыт на сайте' : 'Пункт снова на сайте', description: `«${item.label}»` }), onError: fail('Не удалось сохранить пункт') },
                  )
                }
                onMoveToPosition={(item, target) =>
                  updateMutation.mutate(
                    { id: item.id, payload: payloadOf(item, { position: target, parentId: null }) },
                    { onSuccess: () => push({ title: 'Пункт перенесён', description: `«${item.label}» → ${positions.find((option) => option.value === target)?.label ?? target}` }), onError: fail('Не удалось перенести пункт') },
                  )
                }
                onDelete={(item, childrenCount) => setToDelete({ item, childrenCount })}
              />
            )}
          </section>
          <aside className="lg:sticky lg:top-4">
            <MenuPreview tree={tree} />
          </aside>
        </div>
      ) : null}

      {dialog !== null ? (
        <MenuItemDialog
          open
          mode={dialog.mode}
          position={dialog.mode === 'edit' ? dialog.item.position : position}
          positionLabel={positionLabel}
          initial={dialog.draft}
          sections={sectionsForDialog()}
          pending={createMutation.isPending || updateMutation.isPending}
          serverError={dialogError}
          onCancel={() => setDialog(null)}
          onSubmit={submitDialog}
        />
      ) : null}

      <ConfirmDialog
        open={toDelete !== null}
        title={`Удалить «${toDelete?.item.label ?? ''}»?`}
        description={
          toDelete !== null && toDelete.childrenCount > 0
            ? `Вместе с разделом удалятся подпункты (${toDelete.childrenCount}). Пункт пропадёт с сайта: ${displayUrl(toDelete.item.url)}.`
            : 'Пункт пропадёт из меню на сайте. Сама страница не удаляется.'
        }
        confirmLabel="Удалить"
        onCancel={() => setToDelete(null)}
        onConfirm={() => {
          if (toDelete !== null) {
            const { item } = toDelete
            deleteMutation.mutate(item.id, { onSuccess: () => push({ title: 'Пункт удалён', description: `«${item.label}»` }), onError: fail('Не удалось удалить пункт') })
          }
          setToDelete(null)
        }}
      />
    </div>
  )
}
