<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()
const login = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

const handleLogin = async () => {
  if (!login.value || !password.value) {
    error.value = 'Заполните все поля'
    return
  }
  loading.value = true
  error.value = ''
  const result = await authStore.login(login.value, password.value)
  if (result.success) {
    if (authStore.user?.role === 'admin') {
      router.push('/admin/profile')
    } else {
      router.push('/profile')
    }
  } else {
    error.value = result.error || 'Ошибка входа'
  }
  loading.value = false
}
</script>

<template>
  <div class="login-page min-h-screen bg-black flex items-center justify-center">
    <div class="bg-gradient-to-br from-gray-900 to-black rounded-2xl border border-gray-800 p-8 w-full max-w-md">
      <h1 class="text-3xl font-bold text-center mb-8">
        <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">Вход в систему</span>
      </h1>
      
      <form @submit.prevent="handleLogin" class="space-y-6">
        <div>
          <input 
            v-model="login" 
            type="text" 
            placeholder="Логин" 
            class="w-full px-5 py-4 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303]" 
          />
        </div>
        <div>
          <input 
            v-model="password" 
            type="password" 
            placeholder="Пароль" 
            class="w-full px-5 py-4 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303]" 
          />
        </div>
        
        <p v-if="error" class="text-red-500 text-sm text-center">{{ error }}</p>
        
        <button 
          type="submit" 
          :disabled="loading" 
          class="w-full bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-white font-semibold py-4 rounded-xl transition-all duration-300 hover:scale-[1.02] disabled:opacity-50"
        >
          {{ loading ? 'Вход...' : 'Войти' }}
        </button>
      </form>
      
      <!-- Ссылка на регистрацию -->
      <p class="text-center text-gray-500 text-sm mt-6">
        Нет аккаунта? 
        <router-link to="/register" class="text-[#fc9303] hover:underline transition">
          Зарегистрироваться
        </router-link>
      </p>
      
      <!-- Подсказка для тестового доступа -->
      <div class="mt-8 pt-6 border-t border-gray-800">
        <p class="text-center text-gray-600 text-xs">
          Тестовый доступ:<br>
          Админ: admin / admin123<br>
          Клиент: зарегистрируйтесь
        </p>
      </div>
    </div>
  </div>
</template>