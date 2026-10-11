import { describe, expect, it } from 'vitest'
import { createBlock } from '../../modules/page-builder/utils/pageBlocks'
import { rememberServerIds, withServerIds, type ServerBlockIds } from './block-ids'

describe('block ids', () => {
  it('remembers server ids of new blocks and sends them on the next save', () => {
    const known = { ...createBlock('text', 0), id: '01JQY3Y5SFSVCE00H9GQWQY56V' }
    const fresh = { ...createBlock('faq', 1), id: 'client-uuid' }
    const serverIds: ServerBlockIds = new Map()

    rememberServerIds([known, fresh], [known, { ...fresh, id: '01JQY3Y5SFSVCE00H9GQWQY57A' }], serverIds)

    expect([...serverIds]).toEqual([['client-uuid', '01JQY3Y5SFSVCE00H9GQWQY57A']])
    expect(withServerIds([known, fresh], serverIds).map((block) => block.id)).toEqual([
      '01JQY3Y5SFSVCE00H9GQWQY56V',
      '01JQY3Y5SFSVCE00H9GQWQY57A',
    ])
  })

  it('ignores a response that does not match the sent document', () => {
    const block = { ...createBlock('text', 0), id: 'client-uuid' }
    const serverIds: ServerBlockIds = new Map()

    rememberServerIds([block], [], serverIds)
    rememberServerIds([block], [{ ...createBlock('faq', 0), id: 'SERVER' }], serverIds)

    expect(serverIds.size).toBe(0)
    expect(withServerIds([block], serverIds)).toEqual([block])
  })
})
