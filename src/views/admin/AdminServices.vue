<script setup>
import { ref, onMounted, watch } from 'vue'

const services = ref([])
const categories = ref([])
const loading = ref(false)
const showModal = ref(false)
const editingService = ref(null)

// Поле для длительности в часах (для удобства пользователя)
const durationHours = ref(0)

const form = ref({
  category_id: null,
  name: '',
  description: '',
  base_price: 0,
  duration_minutes: 0,
  is_active: true,
  icon_url: '',
  sort_order: 0
})

// Конвертация минут в часы
const minutesToHours = (minutes) => {
  return minutes / 60
}

// Конвертация часов в минуты
const hoursToMinutes = (hours) => {
  return Math.round(hours * 60)
}

// Следим за изменением durationHours и обновляем form.duration_minutes
watch(durationHours, (newValue) => {
  form.value.duration_minutes = hoursToMinutes(newValue)
})

// Следим за изменением form.duration_minutes (при редактировании)
watch(() => form.value.duration_minutes, (newValue) => {
  if (newValue) {
    durationHours.value = minutesToHours(newValue)
  }
})

const fetchServices = async () => {
  loading.value = true
  try {
    const res = await fetch('http://localhost:8000/api/admin/services', { credentials: 'include' })
    const data = await res.json()
    if (data.success) services.value = data.services
  } catch (err) {
    console.error(err)
  } finally {
    loading.value = false
  }
}

const fetchCategories = async () => {
  try {
    const res = await fetch('http://localhost:8000/api/admin/service-categories', { credentials: 'include' })
    const data = await res.json()
    if (data.success) categories.value = data.categories
  } catch (err) {
    console.error(err)
  }
}

const openAddModal = () => {
  editingService.value = null
  durationHours.value = 0
  form.value = {
    category_id: categories.value[0]?.category_id || null,
    name: '',
    description: '',
    base_price: 0,
    duration_minutes: 0,
    is_active: true,
    icon_url: '',
    sort_order: 0
  }
  showModal.value = true
}

const openEditModal = (service) => {
  editingService.value = service
  form.value = { ...service }
  durationHours.value = minutesToHours(service.duration_minutes || 0)
  showModal.value = true
}

const saveService = async () => {
  const url = editingService.value
    ? `http://localhost:8000/api/admin/services/${editingService.value.service_id}`
    : 'http://localhost:8000/api/admin/services'
  const method = editingService.value ? 'PUT' : 'POST'

  const res = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify(form.value)
  })
  const data = await res.json()
  if (data.success) {
    await fetchServices()
    showModal.value = false
  } else {
    alert('Ошибка: ' + (data.error || 'Не удалось сохранить'))
  }
}

const deleteService = async (id, name) => {
  if (confirm(`Удалить услугу "${name}"?`)) {
    const res = await fetch(`http://localhost:8000/api/admin/services/${id}`, {
      method: 'DELETE',
      credentials: 'include'
    })
    const data = await res.json()
    if (data.success) {
      await fetchServices()
    } else {
      alert('Ошибка удаления')
    }
  }
}

onMounted(() => {
  fetchCategories()
  fetchServices()
})
</script>

<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold">Управление услугами</h2>
      <button @click="openAddModal" class="px-4 py-2 bg-[#fc9303] rounded-lg text-black font-semibold hover:scale-105 transition">
        + Добавить услугу
      </button>
    </div>

    <div v-if="loading" class="text-center py-12">
      <div class="w-10 h-10 border-4 border-[#fc9303] border-t-transparent rounded-full animate-spin mx-auto"></div>
    </div>

    <div v-else class="space-y-6">
      <div v-for="cat in categories" :key="cat.category_id">
        <h3 class="text-lg font-bold mb-2">{{ cat.name }}</h3>
        <div class="bg-gray-800 rounded-lg overflow-hidden mb-4">
          <div v-for="service in services.filter(s => s.category_id === cat.category_id)" :key="service.service_id"
               class="p-4 border-b border-gray-700 flex justify-between items-center hover:bg-gray-700">
            <div>
              <div class="font-semibold">{{ service.name }}</div>
              <div class="text-sm text-gray-400">{{ service.description || '—' }}</div>
              <div class="text-sm text-[#fc9303]">{{ service.base_price }} ₽ | {{ Math.floor(service.duration_minutes / 60) }} ч {{ service.duration_minutes % 60 }} мин</div>
              <div class="text-xs text-gray-500">Статус: {{ service.is_active ? 'Активна' : 'Неактивна' }}</div>
            </div>
            <div class="flex gap-2">
              <button @click="openEditModal(service)" class="text-gray-400 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
              </button>
              <button @click="deleteService(service.service_id, service.name)" class="text-gray-400 hover:text-red-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Модальное окно (улучшенное для десктопа) -->
    <Transition name="modal">
      <div v-if="showModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showModal = false">
        <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="sticky top-0 bg-gray-900 px-6 py-4 border-b border-gray-800 flex justify-between items-center">
            <h3 class="text-xl font-bold">{{ editingService ? 'Редактировать' : 'Добавить' }} услугу</h3>
            <button @click="showModal = false" class="text-gray-400 hover:text-white">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm text-gray-400 mb-1">Категория</label>
                <select v-model="form.category_id" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
                  <option v-for="cat in categories" :key="cat.category_id" :value="cat.category_id">{{ cat.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Название</label>
                <input v-model="form.name" type="text" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm text-gray-400 mb-1">Описание</label>
                <textarea v-model="form.description" rows="3" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white"></textarea>
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Цена (₽)</label>
                <input v-model.number="form.base_price" type="number" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Длительность (часы)</label>
                <input v-model.number="durationHours" type="number" step="0.5" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white" placeholder="Например: 2.5">
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">URL иконки</label>
                <input v-model="form.icon_url" type="text" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Порядок сортировки</label>
                <input v-model.number="form.sort_order" type="number" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
              </div>
              <div class="flex items-center justify-between md:col-span-2">
                <label class="text-sm text-gray-400">Активна</label>
                <button @click="form.is_active = !form.is_active" class="relative w-12 h-6 rounded-full transition-colors" :class="form.is_active ? 'bg-[#fc9303]' : 'bg-gray-700'">
                  <span class="absolute top-1 w-4 h-4 bg-white rounded-full transition-all" :class="form.is_active ? 'left-7' : 'left-1'"></span>
                </button>
              </div>
            </div>
          </div>
          <div class="sticky bottom-0 bg-gray-900 px-6 py-4 border-t border-gray-800 flex gap-3">
            <button @click="showModal = false" class="flex-1 px-4 py-3 border border-gray-700 rounded-xl text-gray-400 hover:text-white">Отмена</button>
            <button @click="saveService" class="flex-1 px-4 py-3 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] rounded-xl text-white font-semibold">Сохранить</button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active {
  transition: all 0.3s ease;
}
.modal-enter-from, .modal-leave-to {
  opacity: 0;
  transform: scale(0.95);
}
</style>