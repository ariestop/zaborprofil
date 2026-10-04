import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [react()],
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
      thresholds: { statements: 30, lines: 30, functions: 50, branches: 70 },
    },
  },
})
