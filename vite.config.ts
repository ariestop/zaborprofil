import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  base: '/build/',
  plugins: [react()],
  resolve: {
    alias: [
      { find: '@admin', replacement: fileURLToPath(new URL('./assets/admin', import.meta.url)) },
      { find: '@admin-ui', replacement: fileURLToPath(new URL('./assets/admin/shared/ui', import.meta.url)) },
      { find: '@/components', replacement: fileURLToPath(new URL('./assets/admin/components/tiptap-templates', import.meta.url)) },
      { find: '@/hooks', replacement: fileURLToPath(new URL('./assets/admin/components/hooks', import.meta.url)) },
      { find: '@/lib', replacement: fileURLToPath(new URL('./assets/admin/components/lib', import.meta.url)) },
    ],
  },
  build: {
    outDir: 'public_html/build',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        site: 'assets/site/app.ts',
        admin: 'assets/admin/app.ts',
      },
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) {
            return
          }

          if (id.includes('grapesjs')) {
            return 'vendor-grapesjs'
          }

          if (id.includes('@tiptap') || id.includes('prosemirror') || id.includes('lowlight')) {
            return 'vendor-tiptap'
          }

          if (id.includes('recharts') || id.includes('d3-')) {
            return 'vendor-charts'
          }

          if (id.includes('@tanstack/react-table')) {
            return 'vendor-table'
          }

          if (id.includes('@dnd-kit')) {
            return 'vendor-dnd'
          }

          return
        },
      },
    },
  },
})
