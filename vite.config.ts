import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [vue()],
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
