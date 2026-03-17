import React from 'react'
import { NotFoundPage } from '@payloadcms/next/views'
import config from '@payload-config'
import { importMap } from '../importMap'

const NotFound = () => (
  <NotFoundPage
    config={config}
    importMap={importMap}
    params={Promise.resolve({ segments: [] })}
    searchParams={Promise.resolve({})}
  />
)

export default NotFound
