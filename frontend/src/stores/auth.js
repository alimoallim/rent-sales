import { defineStore } from 'pinia'
import api, { ensureCsrfCookie, resetCsrfCookie } from '../api/client'

const MODULE_KEY = 'preferredModule'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    checked: false,
  }),

  getters: {
    isAuthenticated: (state) => state.user !== null,
    role: (state) => state.user?.role ?? null,
    isAdmin: (state) => state.user?.is_admin === true,
    canAccessRental: (state) => state.user?.can_access_rental === true,
    canAccessSales: (state) => state.user?.can_access_sales === true,
  },

  actions: {
    preferredModule() {
      const stored = localStorage.getItem(MODULE_KEY)
      if (stored === 'sales' && this.canAccessSales) return 'sales'
      if (stored === 'rental' && this.canAccessRental) return 'rental'
      if (this.canAccessRental) return 'rental'
      if (this.canAccessSales) return 'sales'
      return 'admin'
    },

    setPreferredModule(module) {
      localStorage.setItem(MODULE_KEY, module)
    },

    defaultRoute() {
      const module = this.preferredModule()
      if (module === 'sales') return '/sales'
      if (module === 'admin') return '/admin/users'
      return '/rental'
    },

    async fetchUser() {
      try {
        const { data } = await api.get('/api/v1/auth/me')
        this.user = data.data
      } catch {
        this.user = null
      } finally {
        this.checked = true
      }
    },

    async login(username, password, remember = false) {
      resetCsrfCookie()
      await ensureCsrfCookie()

      try {
        const { data } = await api.post('/api/v1/auth/login', {
          username,
          password,
          remember,
        })
        this.user = data.data
        this.checked = true
        return this.user
      } catch (error) {
        if (error.response?.status === 419) {
          resetCsrfCookie()
          await ensureCsrfCookie()
          const { data } = await api.post('/api/v1/auth/login', {
            username,
            password,
            remember,
          })
          this.user = data.data
          this.checked = true
          return this.user
        }

        throw error
      }
    },

    async logout() {
      await api.post('/api/v1/auth/logout')
      this.user = null
    },
  },
})
