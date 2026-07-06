import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    base: env.VITE_BASE_PATH || '/',
    plugins: [vue(), tailwindcss()],
    build: {
      rollupOptions: {
        output: {
          manualChunks(id) {
            if (id.includes('node_modules')) {
              return
            }

            if (id.includes('/src/views/rental/')) {
              return 'rental-views'
            }

            if (id.includes('/src/views/sales/')) {
              return 'sales-views'
            }

            if (id.includes('/src/views/admin/')) {
              return 'admin-views'
            }

            if (
              id.includes('LoginView.vue')
              || id.includes('ForgotPasswordView.vue')
              || id.includes('ResetPasswordView.vue')
            ) {
              return 'auth-views'
            }
          },
        },
      },
    },
    server: {
      port: 5173,
      proxy: {
        '/api': 'http://localhost:8000',
        '/sanctum': 'http://localhost:8000',
      },
    },
  }
})
