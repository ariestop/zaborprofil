import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { ADMIN_PERMISSIONS } from './permissions'

describe('ADMIN_PERMISSIONS', () => {
  it('matches the permissions declared on the backend', () => {
    const source = readFileSync(resolve(__dirname, '../../../src/Module/Auth/Domain/Security/AdminPermission.php'), 'utf8')
    const backend = [...source.matchAll(/public const string [A-Z_]+ = '([a-z_.]+)';/g)].map((match) => match[1])

    expect([...ADMIN_PERMISSIONS].sort()).toEqual(backend.sort())
  })
})
