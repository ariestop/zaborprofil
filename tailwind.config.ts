import type { Config } from 'tailwindcss'
import typography from '@tailwindcss/typography'

export default {
  darkMode: 'class',
  content: [
    './templates/**/*.twig',
    './assets/**/*.{ts,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#ecfdf5',
          500: '#059669',
          700: '#047857',
          900: '#064e3b',
        },
      },
    },
  },
  plugins: [typography],
} satisfies Config
