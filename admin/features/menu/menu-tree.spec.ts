import { describe, expect, it } from 'vitest'
import type { MenuItem } from '../../entities/menu/api'
import { buildTree, displayUrl, moveBy, moveOver, nestInto, nestTargets, toOrder, unnest } from './menu-tree'

function item(id: string, sortOrder: number, parentId: string | null = null, position: MenuItem['position'] = 'header'): MenuItem {
  return { id, position, parentId, label: id, url: `/${id}/`, hint: null, sortOrder, isActive: true, updatedAt: '2026-10-14T10:00:00+00:00' }
}

const items = [
  item('fences', 10),
  item('contacts', 20),
  item('rancho', 20, 'fences'),
  item('jaluzi', 10, 'fences'),
  item('about', 10, null, 'footer'),
]

const ids = (order: { id: string, parentId: string | null }[]) => order.map((entry) => `${entry.parentId === null ? '' : '  '}${entry.id}`)

describe('menu tree', () => {
  it('builds sections with sorted children for one position', () => {
    const tree = buildTree(items, 'header')

    expect(tree.map((node) => node.id)).toEqual(['fences', 'contacts'])
    expect(tree[0].children.map((child) => child.id)).toEqual(['jaluzi', 'rancho'])
    expect(ids(toOrder(tree))).toEqual(['fences', '  jaluzi', '  rancho', 'contacts'])
  })

  it('shows an orphaned child on the top level', () => {
    expect(buildTree([item('lost', 10, 'deleted')], 'header').map((node) => node.id)).toEqual(['lost'])
  })

  it('moves items among their siblings only', () => {
    const tree = buildTree(items, 'header')

    expect(ids(toOrder(moveBy(tree, 'contacts', -1)!))).toEqual(['contacts', 'fences', '  jaluzi', '  rancho'])
    expect(ids(toOrder(moveBy(tree, 'jaluzi', 1)!))).toEqual(['fences', '  rancho', '  jaluzi', 'contacts'])
    expect(moveBy(tree, 'jaluzi', -1)).toBeNull()
    expect(moveBy(tree, 'contacts', 1)).toBeNull()
  })

  it('drags within one level and ignores drops onto another level', () => {
    const tree = buildTree(items, 'header')

    expect(ids(toOrder(moveOver(tree, 'rancho', 'jaluzi')!))).toEqual(['fences', '  rancho', '  jaluzi', 'contacts'])
    expect(moveOver(tree, 'contacts', 'jaluzi')).toBeNull()
    expect(moveOver(tree, 'contacts', 'contacts')).toBeNull()
  })

  it('nests an item as the last child and lifts it back right after its section', () => {
    const tree = buildTree(items, 'header')

    const nested = nestInto(tree, 'contacts', 'fences')
    expect(ids(toOrder(nested))).toEqual(['fences', '  jaluzi', '  rancho', '  contacts'])
    expect(ids(toOrder(unnest(nested, 'jaluzi')))).toEqual(['fences', '  rancho', '  contacts', 'jaluzi'])
  })

  it('does not offer to nest a section with children', () => {
    const tree = buildTree(items, 'header')

    expect(nestTargets(tree, 'fences')).toEqual([])
    expect(nestTargets(tree, 'contacts').map((node) => node.id)).toEqual(['fences'])
    expect(nestTargets(tree, 'jaluzi').map((node) => node.id)).toEqual(['contacts'])
  })

  it('shows external links without the protocol', () => {
    expect(displayUrl('https://t.me/zabortop')).toBe('t.me/zabortop')
    expect(displayUrl('/zabor-jaluzi/')).toBe('/zabor-jaluzi/')
  })
})
