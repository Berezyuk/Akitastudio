<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const form = ref({
    login: '',
    password: '',
    confirmPassword: '',
    first_name: '',
    last_name: '',
    phone: '',
    email: ''
})

const error = ref('')
const isLoading = ref(false)
const success = ref(false)

const API_URL = 'http://localhost:8000/api'

const handleRegister = async () => {
    // Валидация
    if (!form.value.login || !form.value.password || !form.value.first_name || 
        !form.value.last_name || !form.value.phone) {
        error.value = 'Заполните все обязательные поля'
        return
    }
    
    if (form.value.password !== form.value.confirmPassword) {
        error.value = 'Пароли не совпадают'
        return
    }
    
    if (form.value.password.length < 6) {
        error.value = 'Пароль должен содержать минимум 6 символов'
        return
    }
    
    // Очищаем телефон от лишних символов
    const cleanPhone = form.value.phone.replace(/\D/g, '')
    if (cleanPhone.length < 10 || cleanPhone.length > 11) {
        error.value = 'Введите корректный номер телефона'
        return
    }
    
    isLoading.value = true
    error.value = ''
    
    try {
        const response = await fetch(`${API_URL}/auth/register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                login: form.value.login,
                password: form.value.password,
                first_name: form.value.first_name,
                last_name: form.value.last_name,
                phone: cleanPhone,
                email: form.value.email || null
            })
        })
        
        const data = await response.json()
        
        if (data.success) {
            success.value = true
            // Очищаем форму
            form.value = {
                login: '',
                password: '',
                confirmPassword: '',
                first_name: '',
                last_name: '',
                phone: '',
                email: ''
            }
            setTimeout(() => {
                router.push('/login')
            }, 2000)
        } else {
            error.value = data.error || 'Ошибка регистрации'
        }
    } catch (err) {
        console.error(err)
        error.value = 'Ошибка соединения с сервером'
    } finally {
        isLoading.value = false
    }
}
</script>

<template>
    <div class="register-page min-h-screen bg-black flex items-center justify-center py-12">
        <div class="bg-gradient-to-br from-gray-900 to-black rounded-2xl border border-gray-800 p-8 w-full max-w-md">
            <h1 class="text-3xl font-bold text-center mb-8">
                <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">
                    Регистрация
                </span>
            </h1>
            
            <div v-if="success" class="bg-green-500/20 border border-green-500 rounded-xl p-4 mb-6 text-center">
                <p class="text-green-400">✅ Регистрация успешна! Перенаправляем на вход...</p>
            </div>
            
            <form @submit.prevent="handleRegister" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <input 
                        v-model="form.first_name"
                        type="text" 
                        placeholder="Имя *"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] focus:ring-1 focus:ring-[#fc9303] transition"
                    >
                    <input 
                        v-model="form.last_name"
                        type="text" 
                        placeholder="Фамилия *"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] focus:ring-1 focus:ring-[#fc9303] transition"
                    >
                </div>
                
                <input 
                    v-model="form.phone"
                    type="tel" 
                    placeholder="Телефон * (например: 79161234567)"
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] focus:ring-1 focus:ring-[#fc9303] transition"
                >
                
                <input 
                    v-model="form.email"
                    type="email" 
                    placeholder="Email (необязательно)"
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] focus:ring-1 focus:ring-[#fc9303] transition"
                >
                
                <input 
                    v-model="form.login"
                    type="text" 
                    placeholder="Логин *"
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] focus:ring-1 focus:ring-[#fc9303] transition"
                >
                
                <input 
                    v-model="form.password"
                    type="password" 
                    placeholder="Пароль * (минимум 6 символов)"
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] focus:ring-1 focus:ring-[#fc9303] transition"
                >
                
                <input 
                    v-model="form.confirmPassword"
                    type="password" 
                    placeholder="Подтвердите пароль *"
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] focus:ring-1 focus:ring-[#fc9303] transition"
                >
                
                <p v-if="error" class="text-red-500 text-sm text-center bg-red-500/10 py-2 rounded-lg">{{ error }}</p>
                
                <button 
                    type="submit"
                    :disabled="isLoading"
                    class="w-full bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-white font-semibold py-4 rounded-xl transition-all duration-300 hover:scale-[1.02] hover:shadow-lg disabled:opacity-50 disabled:hover:scale-100"
                >
                    {{ isLoading ? 'Регистрация...' : 'Зарегистрироваться' }}
                </button>
            </form>
            
            <p class="text-center text-gray-500 text-sm mt-6">
                Уже есть аккаунт? 
                <router-link to="/login" class="text-[#fc9303] hover:underline transition">Войти</router-link>
            </p>
        </div>
    </div>
</template>