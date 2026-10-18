import { fileURLToPath } from 'node:url'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [react()],
  // Те же алиасы, что в vite.config.ts: без них редактор TipTap нельзя проверить без мока.
  resolve: {
    alias: [
      { find: '@admin', replacement: fileURLToPath(new URL('./admin', import.meta.url)) },
      { find: '@admin-ui', replacement: fileURLToPath(new URL('./admin/shared/ui', import.meta.url)) },
      { find: '@/components', replacement: fileURLToPath(new URL('./admin/components/tiptap-templates', import.meta.url)) },
      { find: '@/hooks', replacement: fileURLToPath(new URL('./admin/components/hooks', import.meta.url)) },
      { find: '@/lib', replacement: fileURLToPath(new URL('./admin/components/lib', import.meta.url)) },
    ],
  },
  test: {
    environment: 'jsdom',
    include: ['assets/**/*.spec.ts', 'assets/**/*.spec.tsx', 'admin/**/*.spec.ts', 'admin/**/*.spec.tsx'],
    globals: true,
    coverage: {
      provider: 'v8',
      include: ['admin/**/*.{ts,tsx}', 'assets/**/*.{ts,tsx}'],
      exclude: ['**/*.spec.{ts,tsx}', '**/*.d.ts', 'admin/**/test-utils.ts', 'admin/**/fixtures.ts'],
      reporter: ['text-summary', 'json-summary'],
      reportsDirectory: 'coverage',
      // Vitest 4+ считает ветви по AST: на том же коде 81% (Vitest 3) = 58% (Vitest 5). Порог ветвей — по новому подсчёту.
      thresholds: { statements: 30, lines: 30, functions: 50, branches: 58 },
    },
    setupFiles: ['admin/shared/testing/setup.ts'],
  },
})
