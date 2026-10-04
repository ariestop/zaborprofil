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
  server: {
    // Страницы отдаёт nginx на другом порту, поэтому ссылки на ассеты внутри CSS/JS
    // (шрифты, картинки) должны вести на dev-сервер, а не на origin страницы.
    origin: process.env.VITE_DEV_SERVER_URL ?? 'http://localhost:5173',
    strictPort: true,
    // Нужен только если проект лежит на файловой системе, где не работают события inotify
    // (например, на C:\ под Docker Desktop): VITE_USE_POLLING=1 make npm-dev
    watch: process.env.VITE_USE_POLLING === '1' ? { usePolling: true, interval: 300 } : undefined,
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
