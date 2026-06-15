<template>
  <div class="admin-settings">
    <h2 class="text-2xl font-bold mb-8">Настройки</h2>

    <!-- Смена пароля -->
    <div class="max-w-md mb-10">
      <h3 class="text-xl font-semibold mb-4">Смена пароля</h3>

      <div v-if="successMessage" class="mb-4 p-3 bg-green-500/20 border border-green-500 rounded-lg text-green-400">
        {{ successMessage }}
      </div>
      <div v-if="errorMessage" class="mb-4 p-3 bg-red-500/20 border border-red-500 rounded-lg text-red-400">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="changePassword" class="space-y-4">
        <div>
          <label class="block text-sm text-gray-400 mb-1">Текущий пароль</label>
          <input
            v-model="form.old_password"
            type="password"
            class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-[#fc9303]"
          />
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-1">Новый пароль</label>
          <input
            v-model="form.new_password"
            type="password"
            class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-[#fc9303]"
          />
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-1">Подтверждение нового пароля</label>
          <input
            v-model="form.confirm_password"
            type="password"
            class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-[#fc9303]"
          />
        </div>
        <button
          type="submit"
          :disabled="loading"
          class="px-6 py-2 bg-[#fc9303] rounded-lg text-black font-semibold hover:bg-[#ff6b00] transition disabled:opacity-50"
        >
          {{ loading ? 'Сохранение...' : 'Сменить пароль' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { API_BASE } from '@/config/api.js'

const form = ref({ old_password: '', new_password: '', confirm_password: '' })
const loading = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const changePassword = async () => {
  loading.value = true
  successMessage.value = ''
  errorMessage.value = ''
  try {
    const res = await fetch(`${API_BASE}/admin/change-password`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(form.value)
    })
    const data = await res.json()
    if (data.success) {
      successMessage.value = data.message
      form.value = { old_password: '', new_password: '', confirm_password: '' }
    } else {
      errorMessage.value = data.error
    }
  } catch {
    errorMessage.value = 'Ошибка соединения'
  } finally {
    loading.value = false
  }
}
</script>
