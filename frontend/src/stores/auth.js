import { defineStore } from 'pinia'
import { apiClient, ensureCsrfCookie } from '@/api/client'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    checked: false,
  }),
  getters: {
    isAuthenticated: (state) => !!state.user,
  },
  actions: {
    async login(email, password) {
      await ensureCsrfCookie()
      await apiClient.post('/login', { email, password })
      await this.fetchUser()
    },
    async logout() {
      await apiClient.post('/logout')
      this.user = null
    },
    async fetchUser() {
      try {
        const { data } = await apiClient.get('/api/user')
        this.user = data.data
      } catch {
        this.user = null
      } finally {
        this.checked = true
      }
    },
  },
})
