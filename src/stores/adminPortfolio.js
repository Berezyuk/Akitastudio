import { defineStore } from 'pinia'
import { ref } from 'vue'
import { API_BASE } from '@/config/api.js'

export const useAdminPortfolioStore = defineStore('adminPortfolio', () => {
    
    const portfolio = ref([])
    const loading = ref(false)
    const error = ref(null)
    
    const getHeaders = () => ({
        'Content-Type': 'application/json'
    })
    
    const fetchPortfolio = async () => {
        loading.value = true
        try {
            const response = await fetch(`${API_BASE}/admin/portfolio`, {
                credentials: 'include',
                headers: getHeaders()
            })
            const data = await response.json()
            if (data.success) {
                portfolio.value = data.portfolio
            } else {
                error.value = data.error || 'Ошибка загрузки'
            }
        } catch (err) {
            error.value = err.message
            console.error('Error fetching portfolio:', err)
        } finally {
            loading.value = false
        }
    }
    
    const addPortfolioItem = async (item) => {
        loading.value = true
        try {
            const response = await fetch(`${API_BASE}/admin/portfolio`, {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'include',
                body: JSON.stringify(item)
            })
            const data = await response.json()
            if (data.success) {
                await fetchPortfolio()
                return { success: true }
            }
            return { success: false, error: data.error }
        } catch (err) {
            return { success: false, error: err.message }
        } finally {
            loading.value = false
        }
    }
    
    const deletePortfolioItem = async (id) => {
        loading.value = true
        try {
            const response = await fetch(`${API_BASE}/admin/portfolio/${id}`, {
                method: 'DELETE',
                credentials: 'include',
                headers: getHeaders()
            })
            const data = await response.json()
            if (data.success) {
                await fetchPortfolio()
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
        portfolio,
        loading,
        error,
        fetchPortfolio,
        addPortfolioItem,
        deletePortfolioItem
    }
})