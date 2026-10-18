import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

const apiTarget = process.env.VITE_API_PROXY_TARGET ?? 'http://localhost:8000'
const realtimeTarget = process.env.VITE_REALTIME_PROXY_TARGET ?? 'http://localhost:8080'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    proxy: {
      '/api': { target: apiTarget, changeOrigin: true },
      '/sanctum': { target: apiTarget, changeOrigin: true },
      '/app': { target: realtimeTarget, ws: true },
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
  },
})
