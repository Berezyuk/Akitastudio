import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    vueDevTools(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    watch: {
      usePolling: true,
    },
  },
  // Юнит-тесты (vitest, `npm run test:unit`) — только чистая логика в tests/unit/.
  // API-смоук в tests/api.test.js гоняется через node:test (`npm test`) и стенд,
  // сюда не подключается.
  test: {
    environment: 'happy-dom',
    include: ['tests/unit/**/*.test.js'],
  },
})
