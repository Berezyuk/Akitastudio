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

  // Локальный разлогин не должен зависеть от доступности сервера: apiFetch бросает
  // на любой не-2xx, и без перехвата упавший бэкенд оставлял пользователя
  // залогиненным, а редирект у вызывающих (TheHeader, ProfileView) не срабатывал.
  async function logout() {
    try {
      await apiFetch('/auth/logout', { method: 'POST' })
    } catch (err) {
      console.error('logout request failed', err)
    } finally {
      user.value = null
    }
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
