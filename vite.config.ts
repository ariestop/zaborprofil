import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'
import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath, URL } from 'node:url'

const buildTarget = process.env.VITE_BUILD_TARGET === 'admin' || process.env.VITE_BUILD_TARGET === 'site'
  ? process.env.VITE_BUILD_TARGET
  : 'all'
const outDir = 'public_html/build'
const manifestRelativePath = '.vite/manifest.json'
const manifestPath = resolve(process.cwd(), outDir, manifestRelativePath)
const shouldMergeManifest = buildTarget !== 'all'
const previousManifestSnapshot = shouldMergeManifest ? readManifest(manifestPath) : {}

function readManifest(manifestFilePath: string): Record<string, unknown> {
  if (!existsSync(manifestFilePath)) {
    return {}
  }

  try {
    const raw = readFileSync(manifestFilePath, 'utf-8')
    const parsed = JSON.parse(raw) as unknown
    return typeof parsed === 'object' && parsed !== null ? parsed as Record<string, unknown> : {}
  } catch {
    return {}
  }
}

export default defineConfig({
  base: '/build/',
  plugins: [
    react(),
    {
      name: 'merge-selective-manifest',
      closeBundle() {
        if (!shouldMergeManifest) {
          return
        }

        const currentManifest = readManifest(manifestPath)
        writeFileSync(manifestPath, JSON.stringify({ ...previousManifestSnapshot, ...currentManifest }, null, 2) + '\n', 'utf-8')
      },
    },
  ],
  resolve: {
    alias: [
      { find: '@admin', replacement: fileURLToPath(new URL('./admin', import.meta.url)) },
      { find: '@admin-ui', replacement: fileURLToPath(new URL('./admin/shared/ui', import.meta.url)) },
      { find: '@/components', replacement: fileURLToPath(new URL('./admin/components/tiptap-templates', import.meta.url)) },
      { find: '@/hooks', replacement: fileURLToPath(new URL('./admin/components/hooks', import.meta.url)) },
      { find: '@/lib', replacement: fileURLToPath(new URL('./admin/components/lib', import.meta.url)) },
    ],
  },
  build: {
    outDir,
    emptyOutDir: buildTarget === 'all',
    manifest: true,
    rollupOptions: {
      input: {
        ...(buildTarget !== 'admin' ? { site: 'assets/site/app.ts' } : {}),
        ...(buildTarget !== 'site' ? { admin: 'admin/app.ts' } : {}),
      },
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) {
            return
          }

          if (id.includes('@tanstack/react-query') || id.includes('@tanstack/query-core')) {
            return 'vendor-query'
          }

          if (id.includes('@tanstack/react-table')) {
            return 'vendor-table'
          }

          if (id.includes('@dnd-kit')) {
            return 'vendor-dnd'
          }

          if (id.includes('react-hook-form') || id.includes('@hookform/resolvers') || id.includes('/zod/')) {
            return 'vendor-forms'
          }

          if (id.includes('recharts') || id.includes('/d3-')) {
            return 'vendor-charts'
          }

          if (id.includes('@tiptap')) {
            return 'vendor-tiptap'
          }

          if (id.includes('prosemirror')) {
            return 'vendor-prosemirror'
          }

          if (id.includes('lowlight') || id.includes('highlight.js')) {
            return 'vendor-highlight'
          }

          if (id.includes('lodash.throttle')) {
            return 'vendor-utils'
          }

          return
        },
      },
    },
  },
})
