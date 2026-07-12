import path from 'node:path'
import { fileURLToPath } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

/** App code shared by the entry bundle and lazy route chunks (never import from index.js). */
function isDashboardSharedSrc(id: string): boolean {
  const norm = id.replace(/\\/g, '/')
  if (!norm.includes('/src/')) {
    return false
  }
  if (norm.includes('/src/pages/')) {
    return false
  }
  if (norm.includes('/src/routes/')) {
    return false
  }
  if (norm.includes('/src/main.tsx') || norm.includes('/src/App.tsx')) {
    return false
  }
  return (
    norm.includes('/src/lib/') ||
    norm.includes('/src/components/') ||
    norm.includes('/src/hooks/') ||
    norm.includes('/src/layouts/') ||
    norm.includes('/src/theme/') ||
    norm.includes('/src/i18n/')
  )
}

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  base: './',
  build: {
    outDir: '../assets/dashboard-build',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      output: {
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
        manualChunks(id) {
          if (id.includes('node_modules')) {
            if (id.includes('react-dom')) return 'vendor-react-dom'
            if (id.includes('/react/') || id.endsWith('node_modules/react/index.js')) {
              return 'vendor-react'
            }
            if (id.includes('react-router')) return 'vendor-react-router'
            if (id.includes('i18next') || id.includes('react-i18next')) return 'vendor-i18n'
            if (id.includes('@tanstack/react-query')) return 'vendor-tanstack-query'
            if (id.includes('@tanstack/react-table')) return 'vendor-tanstack-table'
            if (id.includes('@dnd-kit')) return 'vendor-dnd-kit'
            if (id.includes('recharts')) return 'vendor-recharts'
            if (id.includes('lucide-react')) return 'vendor-lucide'
            if (id.includes('@radix-ui')) return 'vendor-radix'
            if (id.includes('zod')) return 'vendor-zod'
            if (id.includes('dayjs') || id.includes('jalaliday')) return 'vendor-dayjs'
            if (id.includes('sonner')) return 'vendor-sonner'
            if (id.includes('vaul')) return 'vendor-vaul'
            return 'vendor'
          }
          if (isDashboardSharedSrc(id)) {
            return 'dashboard-shared'
          }
          return undefined
        },
      },
    },
  },
})
