import path from 'path'
import { buildConfig } from 'payload'
import { en } from 'payload/i18n/en'
import { ru } from 'payload/i18n/ru'
import { sqliteAdapter } from '@payloadcms/db-sqlite'
import { lexicalEditor } from '@payloadcms/richtext-lexical'
import sharp from 'sharp'
import { fileURLToPath } from 'url'

import { Users } from './collections/Users'
import { Pages } from './collections/Pages'
import { Articles } from './collections/Articles'
import { Cases } from './collections/Cases'
import { Media } from './collections/Media'
import { Navigation } from './collections/Navigation'

const filename = fileURLToPath(import.meta.url)
const dirname = path.dirname(filename)

export default buildConfig({
  admin: {
    user: 'users',
    importMap: {
      baseDir: path.resolve(dirname),
    },
  },
  i18n: {
    supportedLanguages: { en, ru },
    fallbackLanguage: 'ru',
  },
  collections: [Users, Pages, Articles, Cases, Media, Navigation],
  editor: lexicalEditor(),
  secret: process.env.PAYLOAD_SECRET || 'change-me-in-production',
  typescript: {
    outputFile: path.resolve(dirname, 'payload-types.ts'),
  },
  db: sqliteAdapter({
    client: {
      url: process.env.DATABASE_URI || `file:${path.resolve(dirname, 'payload.db')}`,
    },
  }),
  sharp,
  routes: {
    api: '/cms-api',
  },
})
