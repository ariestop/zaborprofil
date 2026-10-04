import { describe, expect, it } from 'vitest'
import { makePageItem } from './fixtures'
import { buildParentOptions } from './parent-options'

const root = makePageItem({ id: 'root', title: 'Корень', path: '/root/' })
const child = makePageItem({ id: 'child', title: 'Потомок', path: '/root/child/', parentId: 'root' })
const grandchild = makePageItem({ id: 'grand', title: 'Внук', path: '/root/child/grand/', parentId: 'child' })
const other = makePageItem({ id: 'other', title: 'Другая', path: '/other/' })
const removed = makePageItem({ id: 'removed', title: 'Удалённая', path: '/removed/', status: 'deleted' })

describe('buildParentOptions', () => {
  const pages = [root, child, grandchild, other, removed]

  it('excludes the page itself, its descendants and deleted pages', () => {
    const values = buildParentOptions(pages, 'root', '').map((option) => option.value)

    expect(values).toEqual(['other'])
  })

  it('offers every living page when creating a new page', () => {
    expect(buildParentOptions(pages, null, '').map((option) => option.value)).toEqual(['root', 'child', 'grand', 'other'])
  })

  it('keeps the current parent selectable even if it is not in the list', () => {
    const options = buildParentOptions([other], 'x', 'missing-parent')

    expect(options.at(-1)).toEqual({ value: 'missing-parent', label: 'Текущая родительская страница' })
  })
})
