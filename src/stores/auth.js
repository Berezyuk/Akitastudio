import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const loading = ref(false)

  const isAuthenticated = computed(() => !!user.value)

  async function login(login, password) {
    loading.value = true
    try {
      const res = await fetch('http://localhost:8000/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ login, password })
      })
      const data = await res.json()
      if (data.success) {
        user.value = data.user
        return { success: true }
      } else {
        return { success: false, error: data.error }
      }
    } catch (err) {
      return { success: false, error: err.message }
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    await fetch('http://localhost:8000/api/auth/logout', {
      method: 'POST',
      credentials: 'include'
    })
    user.value = null
  }

  async function checkAuth() {
    try {
      const res = await fetch('http://localhost:8000/api/auth/me', { credentials: 'include' })
      const data = await res.json()
      if (data.success) {
        user.value = data.user
      }
    } catch (err) {}
  }

  return { user, loading, isAuthenticated, login, logout, checkAuth }
})