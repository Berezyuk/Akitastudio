// stores/profile.js
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useAuthStore } from './auth'

export const useProfileStore = defineStore('profile', () => {
    const authStore = useAuthStore()
    const API_URL = 'http://localhost:8000/api'
    
    const profile = ref(null)
    const cars = ref([])
    const orders = ref([])
    const activeOrder = ref(null)
    const notifications = ref([])
    const loyalty = ref(null)
    const loading = ref(false)

    async function fetchProfile() {
        loading.value = true
        try {
            const response = await fetch(`${API_URL}/user/profile`, {
                credentials: 'include',
                headers: {
                    'Authorization': localStorage.getItem('token') ? `Bearer ${localStorage.getItem('token')}` : ''
                }
            })
            const data = await response.json()
            if (data.success) {
                profile.value = data.profile
            }
        } catch (err) {
            console.error('Error fetching profile:', err)
        } finally {
            loading.value = false
        }
    }

    async function fetchCars() {
        try {
            const response = await fetch(`${API_URL}/user/cars`, {
                credentials: 'include',
                headers: {
                    'Authorization': localStorage.getItem('token') ? `Bearer ${localStorage.getItem('token')}` : ''
                }
            })
            const data = await response.json()
            if (data.success) {
                cars.value = data.cars
            }
        } catch (err) {
            console.error('Error fetching cars:', err)
        }
    }

    async function addCar(carData) {
        try {
            const response = await fetch(`${API_URL}/user/cars`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': localStorage.getItem('token') ? `Bearer ${localStorage.getItem('token')}` : ''
                },
                credentials: 'include',
                body: JSON.stringify(carData)
            })
            const data = await response.json()
            if (data.success) {
                await fetchCars()
            }
            return data
        } catch (err) {
            console.error('Error adding car:', err)
            return { error: err.message }
        }
    }

    async function deleteCar(carId) {
        try {
            const response = await fetch(`${API_URL}/user/cars/${carId}`, {
                method: 'DELETE',
                credentials: 'include',
                headers: {
                    'Authorization': localStorage.getItem('token') ? `Bearer ${localStorage.getItem('token')}` : ''
                }
            })
            const data = await response.json()
            if (data.success) {
                await fetchCars()
            }
            return data
        } catch (err) {
            console.error('Error deleting car:', err)
            return { error: err.message }
        }
    }

    async function fetchOrders() {
        try {
            const response = await fetch(`${API_URL}/user/orders`, {
                credentials: 'include',
                headers: {
                    'Authorization': localStorage.getItem('token') ? `Bearer ${localStorage.getItem('token')}` : ''
                }
            })
            const data = await response.json()
            if (data.success) {
                orders.value = data.orders
            }
        } catch (err) {
            console.error('Error fetching orders:', err)
        }
    }

    async function fetchActiveOrder() {
        try {
            const response = await fetch(`${API_URL}/user/orders/active`, {
                credentials: 'include',
                headers: {
                    'Authorization': localStorage.getItem('token') ? `Bearer ${localStorage.getItem('token')}` : ''
                }
            })
            const data = await response.json()
            if (data.success) {
                activeOrder.value = data.order
            }
        } catch (err) {
            console.error('Error fetching active order:', err)
        }
    }

    async function fetchNotifications() {
        try {
            const response = await fetch(`${API_URL}/user/notifications`, {
                credentials: 'include',
                headers: {
                    'Authorization': localStorage.getItem('token') ? `Bearer ${localStorage.getItem('token')}` : ''
                }
            })
            const data = await response.json()
            if (data.success) {
                notifications.value = data.notifications
            }
        } catch (err) {
            console.error('Error fetching notifications:', err)
        }
    }

    async function markNotificationRead(notificationId) {
        try {
            const response = await fetch(`${API_URL}/user/notifications/${notificationId}/read`, {
                method: 'PUT',
                credentials: 'include',
                headers: {
                    'Authorization': localStorage.getItem('token') ? `Bearer ${localStorage.getItem('token')}` : ''
                }
            })
            return await response.json()
        } catch (err) {
            console.error('Error marking notification read:', err)
            return { error: err.message }
        }
    }

    async function fetchLoyalty() {
        try {
            const response = await fetch(`${API_URL}/user/loyalty`, {
                credentials: 'include',
                headers: {
                    'Authorization': localStorage.getItem('token') ? `Bearer ${localStorage.getItem('token')}` : ''
                }
            })
            const data = await response.json()
            if (data.success) {
                loyalty.value = data.loyalty
            }
        } catch (err) {
            console.error('Error fetching loyalty:', err)
        }
    }

    return {
        profile,
        cars,
        orders,
        activeOrder,
        notifications,
        loyalty,
        loading,
        fetchProfile,
        fetchCars,
        addCar,
        deleteCar,
        fetchOrders,
        fetchActiveOrder,
        fetchNotifications,
        markNotificationRead,
        fetchLoyalty
    }
})