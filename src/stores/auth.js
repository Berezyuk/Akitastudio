import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiFetch } from '@/config/api.js'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const loading = ref(false)

  const isAuthenticated = computed(() => !!user.value)

  async function login(login, password) {
    loading.value = true
    try {
      const data = await apiFetch('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ login, password })
      })
      if (data.success) {
        user.value = data.user
        return { success: true }
      }
      return { success: false, error: data.error }
    } catch (err) {
      return { success: false, error: err.message }
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    await apiFetch('/auth/logout', { method: 'POST' })
    user.value = null
  }

  async function checkAuth() {
    try {
      const data = await apiFetch('/auth/me')
      if (data.success) {
        user.value = data.user
      }
    } catch (err) {
      console.error('checkAuth failed', err)
    }
  }

  return { user, loading, isAuthenticated, login, logout, checkAuth }
})
