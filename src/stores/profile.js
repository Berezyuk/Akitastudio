// stores/profile.js
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { API_BASE } from '@/config/api.js'

export const useProfileStore = defineStore('profile', () => {
    const profile = ref(null)
    const cars = ref([])
    const orders = ref([])
    const loading = ref(false)

    async function fetchProfile() {
        loading.value = true
        try {
            const response = await fetch(`${API_BASE}/user/profile`, { credentials: 'include' })
            const data = await response.json()
            if (data.success) {
                profile.value = data.profile
            }
        } catch {
        } finally {
            loading.value = false
        }
    }

    async function fetchCars() {
        try {
            const response = await fetch(`${API_BASE}/user/cars`, { credentials: 'include' })
            const data = await response.json()
            if (data.success) {
                cars.value = data.cars
            }
        } catch {}
    }

    async function addCar(carData) {
        try {
            const response = await fetch(`${API_BASE}/user/cars`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(carData)
            })
            const data = await response.json()
            if (data.success) {
                await fetchCars()
            }
            return data
        } catch (err) {
            return { error: err.message }
        }
    }

    async function fetchOrders() {
        try {
            const response = await fetch(`${API_BASE}/user/orders`, { credentials: 'include' })
            const data = await response.json()
            if (data.success) {
                orders.value = data.orders
            }
        } catch {}
    }

    return {
        profile,
        cars,
        orders,
        loading,
        fetchProfile,
        fetchCars,
        addCar,
        fetchOrders,
    }
})
