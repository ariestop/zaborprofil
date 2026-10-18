import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { createColumnHelper } from '@tanstack/react-table'
import { afterEach, describe, expect, it } from 'vitest'
import { DataTable, type DataTableFeatures } from './data-table'

interface Item {
  id: number
  name: string
}

const columnHelper = createColumnHelper<DataTableFeatures, Item>()

afterEach(cleanup)

function Table({ items }: { items: Item[] }) {
  // Как на страницах админки: колонки и массив данных создаются заново на каждый рендер.
  const columns = [
    columnHelper.accessor('name', { header: 'Имя' }),
    columnHelper.display({
      id: 'actions',
      header: 'Действия',
      cell: ({ row }) => <span>#{row.original.id}</span>,
    }),
  ]

  return <DataTable columns={columns} data={items.map((item) => ({ ...item }))} pageSize={2} />
}

describe('DataTable', () => {
  const items: Item[] = [
    { id: 1, name: 'Первый' },
    { id: 2, name: 'Второй' },
    { id: 3, name: 'Третий' },
  ]

  it('выводит заголовки, ячейки и листает страницы', () => {
    render(<Table items={items} />)

    expect(screen.getByRole('columnheader', { name: 'Имя' })).toBeTruthy()
    expect(screen.getByText('Первый')).toBeTruthy()
    expect(screen.getByText('#2')).toBeTruthy()
    expect(screen.queryByText('Третий')).toBeNull()
    expect(screen.getByText('Страница 1 из 2')).toBeTruthy()
    expect((screen.getByRole('button', { name: 'Назад' }) as HTMLButtonElement).disabled).toBe(
      true,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Далее' }))

    expect(screen.getByText('Третий')).toBeTruthy()
    expect(screen.queryByText('Первый')).toBeNull()
    expect(screen.getByText('Страница 2 из 2')).toBeTruthy()
    expect((screen.getByRole('button', { name: 'Далее' }) as HTMLButtonElement).disabled).toBe(
      true,
    )
  })

  it('показывает пустую таблицу без данных', () => {
    render(<Table items={[]} />)

    expect(screen.getByRole('columnheader', { name: 'Действия' })).toBeTruthy()
    expect(screen.queryAllByRole('cell')).toHaveLength(0)
  })
})
