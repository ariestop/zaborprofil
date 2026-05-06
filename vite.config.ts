import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: [
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
    },
  },
})
