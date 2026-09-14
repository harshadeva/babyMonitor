import { defineStore } from 'pinia'
import { apiClient, ensureCsrfCookie } from '@/api/client'

export const useBabyStore = defineStore('baby', {
  state: () => ({
    babies: [],
    currentBabyId: null,
    loaded: false,
  }),
  getters: {
    currentBaby: (state) => state.babies.find((b) => b.id === state.currentBabyId) || null,
  },
  actions: {
    async load() {
      const { data } = await apiClient.get('/api/babies')
      this.babies = data.data
      if (!this.currentBabyId && this.babies.length > 0) {
        this.currentBabyId = this.babies[0].id
      }
      this.loaded = true
    },
    async updateBaby(payload) {
      await ensureCsrfCookie()
      const { data } = await apiClient.patch(`/api/babies/${this.currentBabyId}`, payload)
      const idx = this.babies.findIndex((b) => b.id === this.currentBabyId)
      if (idx !== -1) this.babies[idx] = data.data
      return data.data
    },
  },
})
