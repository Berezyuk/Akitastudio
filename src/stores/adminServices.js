import { defineStore } from 'pinia'
import { ref } from 'vue'
import { API_BASE } from '@/config/api.js'

export const useAdminServicesStore = defineStore('adminServices', () => {
    const services = ref([])
    const loading = ref(false)
    const error = ref(null)

    const getHeaders = () => ({ 'Content-Type': 'application/json' })
    
    // Загрузка всех услуг
    const fetchServices = async () => {
        loading.value = true
        try {
            const response = await fetch(`${API_BASE}/admin/services`, {
                credentials: 'include',
                headers: getHeaders()
            })
            const data = await response.json()
            if (data.success) {
                services.value = data.services
            }
        } catch (err) {
            error.value = err.message
            console.error('Error fetching services:', err)
        } finally {
            loading.value = false
        }
    }
    
    // Добавление услуги
    const addService = async (serviceData) => {
        loading.value = true
        try {
            const response = await fetch(`${API_BASE}/admin/services`, {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'include',
                body: JSON.stringify(serviceData)
            })
            const data = await response.json()
            if (data.success) {
                await fetchServices()
                return { success: true }
            }
            return { success: false, error: data.error }
        } catch (err) {
            return { success: false, error: err.message }
        } finally {
            loading.value = false
        }
    }
    
    // Обновление услуги
    const updateService = async (serviceId, serviceData) => {
        loading.value = true
        try {
            const response = await fetch(`${API_BASE}/admin/services/${serviceId}`, {
                method: 'PUT',
                headers: getHeaders(),
                credentials: 'include',
                body: JSON.stringify(serviceData)
            })
            const data = await response.json()
            if (data.success) {
                await fetchServices()
                return { success: true }
            }
            return { success: false, error: data.error }
        } catch (err) {
            return { success: false, error: err.message }
        } finally {
            loading.value = false
        }
    }
    
    // Удаление услуги
    const deleteService = async (serviceId) => {
        loading.value = true
        try {
            const response = await fetch(`${API_BASE}/admin/services/${serviceId}`, {
                method: 'DELETE',
                credentials: 'include',
                headers: getHeaders()
            })
            const data = await response.json()
            if (data.success) {
                await fetchServices()
                return { success: true }
            }
            return { success: false, error: data.error }
        } catch (err) {
            return { success: false, error: err.message }
        } finally {
            loading.value = false
        }
    }
    
    // Тoggle активности услуги
    const toggleServiceActive = async (serviceId, isActive) => {
        loading.value = true
        try {
            const response = await fetch(`${API_BASE}/admin/services/${serviceId}/toggle`, {
                method: 'PUT',
                headers: getHeaders(),
                credentials: 'include',
                body: JSON.stringify({ is_active: isActive })
            })
            const data = await response.json()
            if (data.success) {
                await fetchServices()
                return { success: true }
            }
            return { success: false, error: data.error }
        } catch (err) {
            return { success: false, error: err.message }
        } finally {
            loading.value = false
        }
    }
    
    return {
        services,
        loading,
        error,
        fetchServices,
        addService,
        updateService,
        deleteService,
        toggleServiceActive
    }
})