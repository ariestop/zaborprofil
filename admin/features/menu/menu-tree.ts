import type { MenuItem, MenuOrderEntry, MenuPosition } from '../../entities/menu/api'

/** Раздел меню верхнего уровня с подпунктами. Меню двухуровневое — как на сайте. */
export interface MenuNode extends MenuItem {
  children: MenuItem[]
}

const bySortOrder = (left: MenuItem, right: MenuItem): number => left.sortOrder - right.sortOrder

/** Дерево позиции. Подпункт без раздела (раздел удалён в другой вкладке) показывается на верхнем уровне. */
export function buildTree(items: MenuItem[], position: MenuPosition): MenuNode[] {
  const inPosition = items.filter((item) => item.position === position).sort(bySortOrder)
  const ids = new Set(inPosition.map((item) => item.id))
  const isChild = (item: MenuItem): boolean => item.parentId !== null && ids.has(item.parentId)

  return inPosition
    .filter((item) => !isChild(item))
    .map((item) => ({ ...item, children: inPosition.filter((child) => isChild(child) && child.parentId === item.id) }))
}

/** Порядок для API: разделы и сразу за каждым — его подпункты. */
export function toOrder(tree: MenuNode[]): MenuOrderEntry[] {
  return tree.flatMap((node) => [
    { id: node.id, parentId: null },
    ...node.children.map((child) => ({ id: child.id, parentId: node.id })),
  ])
}

export function findParent(tree: MenuNode[], id: string): MenuNode | null {
  return tree.find((node) => node.children.some((child) => child.id === id)) ?? null
}

function move<T>(list: T[], from: number, to: number): T[] {
  const next = [...list]
  const [item] = next.splice(from, 1)
  next.splice(to, 0, item)
  return next
}

/** Перестановка среди соседей (тот же уровень, тот же раздел). null — двигать некуда. */
export function moveBy(tree: MenuNode[], id: string, delta: -1 | 1): MenuNode[] | null {
  const parent = findParent(tree, id)
  if (parent === null) {
    const index = tree.findIndex((node) => node.id === id)
    const target = index + delta
    return index < 0 || target < 0 || target >= tree.length ? null : move(tree, index, target)
  }

  const index = parent.children.findIndex((child) => child.id === id)
  const target = index + delta
  if (target < 0 || target >= parent.children.length) {
    return null
  }

  return tree.map((node) => (node.id === parent.id ? { ...node, children: move(node.children, index, target) } : node))
}

/** Перетаскивание: пункт встаёт на место соседа `overId`. Между уровнями перетаскивание не работает. */
export function moveOver(tree: MenuNode[], activeId: string, overId: string): MenuNode[] | null {
  if (activeId === overId) {
    return null
  }

  const topFrom = tree.findIndex((node) => node.id === activeId)
  const topTo = tree.findIndex((node) => node.id === overId)
  if (topFrom >= 0 && topTo >= 0) {
    return move(tree, topFrom, topTo)
  }

  const parent = findParent(tree, activeId)
  if (parent === null || findParent(tree, overId)?.id !== parent.id) {
    return null
  }
  const from = parent.children.findIndex((child) => child.id === activeId)
  const to = parent.children.findIndex((child) => child.id === overId)

  return tree.map((node) => (node.id === parent.id ? { ...node, children: move(node.children, from, to) } : node))
}

/** Разделы, в которые можно вложить пункт: верхний уровень, кроме самого пункта. Раздел с подпунктами вложить нельзя. */
export function nestTargets(tree: MenuNode[], id: string): MenuNode[] {
  const own = tree.find((node) => node.id === id)
  if (own !== undefined && own.children.length > 0) {
    return []
  }
  const currentParent = findParent(tree, id)

  return tree.filter((node) => node.id !== id && node.id !== currentParent?.id)
}

/** Вложить пункт последним подпунктом раздела `sectionId`. */
export function nestInto(tree: MenuNode[], id: string, sectionId: string): MenuNode[] {
  const parent = findParent(tree, id)
  const item: MenuItem | undefined = parent === null
    ? tree.find((node) => node.id === id)
    : parent.children.find((child) => child.id === id)
  if (item === undefined) {
    return tree
  }
  const plain: MenuItem = { ...item }
  delete (plain as Partial<MenuNode>).children

  return tree
    .filter((node) => node.id !== id)
    .map((node) => ({
      ...node,
      children: [
        ...node.children.filter((child) => child.id !== id),
        ...(node.id === sectionId ? [{ ...plain, parentId: sectionId }] : []),
      ],
    }))
}

/** Вынести подпункт на верхний уровень — сразу после его раздела. */
export function unnest(tree: MenuNode[], id: string): MenuNode[] {
  const parent = findParent(tree, id)
  const item = parent?.children.find((child) => child.id === id)
  if (parent === null || item === undefined) {
    return tree
  }

  return tree.flatMap((node) =>
    node.id === parent.id
      ? [{ ...node, children: node.children.filter((child) => child.id !== id) }, { ...item, parentId: null, children: [] }]
      : [node],
  )
}

/** Подпись адреса: путь как есть, внешняя ссылка — без протокола. */
export function displayUrl(url: string): string {
  return url.replace(/^https?:\/\//, '')
}
