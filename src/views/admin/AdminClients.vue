<template>
  <div class="admin-clients">
    <h2 class="text-2xl font-bold mb-6">Управление клиентами</h2>

    <!-- Поиск и экспорт -->
    <div class="mb-4 flex flex-wrap gap-4 justify-between items-center">
      <input 
        v-model="search" 
        type="text" 
        placeholder="Поиск по имени, телефону, email" 
        class="flex-1 px-4 py-2 bg-gray-700 rounded-lg text-white"
        @input="debouncedFetch"
      />
      <button @click="exportCSV" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
        📥 Экспорт CSV
      </button>
    </div>

    <!-- Таблица клиентов -->
    <div v-if="loading" class="text-center py-12">
      <div class="w-10 h-10 border-4 border-[#fc9303] border-t-transparent rounded-full animate-spin mx-auto"></div>
    </div>
    <div v-else class="overflow-x-auto">
      <table class="w-full text-left">
        <thead class="bg-gray-800">
          <tr>
            <th class="p-4">ID</th>
            <th class="p-4">Имя</th>
            <th class="p-4">Фамилия</th>
            <th class="p-4">Телефон</th>
            <th class="p-4">Email</th>
            <th class="p-4">Действия</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="client in clients" :key="client.client_id" class="border-b border-gray-800 hover:bg-gray-800/30">
            <td class="p-4">{{ client.client_id }}</td>
            <td class="p-4">{{ client.first_name }}</td>
            <td class="p-4">{{ client.last_name }}</td>
            <td class="p-4">{{ client.phone_number }}</td>
            <td class="p-4">{{ client.email || '—' }}</td>
            <td class="p-4">
              <button @click="openEditModal(client)" class="text-blue-400 hover:text-blue-300 mr-2">✏️ Редактировать</button>
              <button @click="viewDetails(client)" class="text-[#fc9303] hover:underline mr-2">История заказов</button>
              <button @click="openCreateOrderModal(client)" class="text-green-400 hover:text-green-300">➕ Новый заказ</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Модальное окно редактирования клиента -->
    <div v-if="editModalVisible" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="editModalVisible = false">
      <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-md">
        <div class="px-6 py-4 border-b border-gray-800 flex justify-between items-center">
          <h3 class="text-xl font-bold">Редактировать клиента</h3>
          <button @click="editModalVisible = false" class="text-gray-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div><label class="block text-sm text-gray-400 mb-1">Имя</label><input v-model="editForm.first_name" type="text" class="w-full px-4 py-2 bg-gray-800 rounded-lg text-white"></div>
          <div><label class="block text-sm text-gray-400 mb-1">Фамилия</label><input v-model="editForm.last_name" type="text" class="w-full px-4 py-2 bg-gray-800 rounded-lg text-white"></div>
          <div><label class="block text-sm text-gray-400 mb-1">Телефон</label><input v-model="editForm.phone_number" type="text" class="w-full px-4 py-2 bg-gray-800 rounded-lg text-white"></div>
          <div><label class="block text-sm text-gray-400 mb-1">Email</label><input v-model="editForm.email" type="email" class="w-full px-4 py-2 bg-gray-800 rounded-lg text-white"></div>
        </div>
        <div class="px-6 py-4 border-t border-gray-800 flex gap-3">
          <button @click="editModalVisible = false" class="flex-1 px-4 py-2 border border-gray-700 rounded-lg text-gray-400 hover:text-white">Отмена</button>
          <button @click="saveClient" class="flex-1 px-4 py-2 bg-[#fc9303] rounded-lg text-black font-semibold hover:bg-[#ff6b00]">Сохранить</button>
        </div>
      </div>
    </div>

    <!-- Модальное окно истории заказов -->
    <div v-if="showModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showModal = false">
      <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-4xl max-h-[85vh] overflow-y-auto">
        <div class="sticky top-0 bg-gray-900 px-6 py-4 border-b border-gray-800 flex justify-between items-center">
          <h3 class="text-xl font-bold">Заказы клиента</h3>
          <button @click="showModal = false" class="text-gray-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        <div class="p-6">
          <div class="mb-4 p-4 bg-gray-800 rounded-lg">
            <p><strong>Клиент:</strong> {{ selectedClient?.first_name }} {{ selectedClient?.last_name }}</p>
            <p><strong>Телефон:</strong> {{ selectedClient?.phone_number }}</p>
            <p><strong>Email:</strong> {{ selectedClient?.email || '—' }}</p>
          </div>
          <div v-if="clientOrders.length === 0" class="text-center text-gray-500 py-8">У клиента пока нет заказов</div>
          <div v-else class="space-y-4">
            <div v-for="order in clientOrders" :key="order.order_id" class="bg-gray-800/50 rounded-lg p-4">
              <div class="flex justify-between items-start">
                <div>
                  <p class="font-semibold">Заказ №{{ order.order_id }}</p>
                  <p class="text-sm text-gray-400">Дата: {{ formatDate(order.order_date) }}</p>
                  <p class="text-sm">Услуги: {{ order.services || '—' }}</p>
                </div>
                <div class="text-right">
                  <p class="text-[#fc9303] font-bold">{{ order.total_price?.toLocaleString() }} ₽</p>
                  <span :class="['px-2 py-0.5 rounded-full text-xs', getStatusClass(order.status_name)]">{{ getStatusName(order.status_name) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Модальное окно создания заказа (с DaData и валидацией даты/времени) -->
    <div v-if="createOrderModalVisible" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="createOrderModalVisible = false">
      <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-3xl max-h-[85vh] overflow-y-auto">
        <div class="sticky top-0 bg-gray-900 px-6 py-4 border-b border-gray-800 flex justify-between items-center">
          <h3 class="text-xl font-bold">Новый заказ для {{ selectedClient?.first_name }} {{ selectedClient?.last_name }}</h3>
          <button @click="createOrderModalVisible = false" class="text-gray-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        <div class="p-6">
          <!-- Выбор услуг по категориям -->
          <div class="flex flex-col lg:flex-row gap-6 mb-6">
            <div class="lg:w-1/3">
              <label class="block text-sm text-gray-400 mb-2">Категории</label>
              <div class="space-y-2 max-h-60 overflow-y-auto pr-2">
                <button
                  v-for="cat in categories"
                  :key="cat.category_id"
                  @click="selectedCategoryId = cat.category_id"
                  type="button"
                  class="w-full text-left px-3 py-2 rounded-lg transition-all duration-300 text-sm"
                  :class="selectedCategoryId === cat.category_id 
                    ? 'bg-[#fc9303]/20 text-[#fc9303] border-l-2 border-[#fc9303]' 
                    : 'bg-gray-800 text-gray-400 hover:bg-gray-700'"
                >
                  {{ cat.name }}
                </button>
              </div>
            </div>

            <div class="lg:w-2/3">
              <label class="block text-sm text-gray-400 mb-2">{{ currentCategoryName || 'Выберите категорию' }}</label>
              <div v-if="!selectedCategoryId" class="text-center py-8 text-gray-500 text-sm">
                Выберите категорию, чтобы увидеть услуги
              </div>
              <div v-else-if="currentServices.length === 0" class="text-center py-8 text-gray-500 text-sm">
                В этой категории пока нет услуг
              </div>
              <div v-else class="grid grid-cols-1 gap-2 max-h-60 overflow-y-auto pr-2">
                <label
                  v-for="service in currentServices"
                  :key="service.service_id"
                  class="flex items-center gap-3 p-2 bg-gray-800 rounded-lg cursor-pointer hover:bg-gray-700 transition group"
                >
                  <input
                    type="checkbox"
                    :value="service.service_id"
                    v-model="newOrder.service_ids"
                    class="checkbox-custom"
                  />
                  <span class="text-sm text-white group-hover:text-[#fc9303] transition">{{ service.name }}</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Марка и модель с DaData -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="relative">
              <label class="block text-sm text-gray-400 mb-2">Марка автомобиля</label>
              <input 
                v-model="carBrandQuery"
                @input="fetchBrandSuggestions"
                @focus="fetchBrandSuggestions"
                type="text"
                class="w-full px-4 py-2 bg-gray-800 rounded-lg text-white"
                placeholder="Начните вводить или выберите из списка"
              />
              <ul v-if="brandSuggestions.length" class="absolute z-20 w-full bg-gray-800 border border-gray-700 rounded-lg mt-1 max-h-40 overflow-y-auto">
                <li 
                  v-for="brand in brandSuggestions" 
                  :key="brand.data.id"
                  @click="selectBrand(brand)"
                  class="px-4 py-2 hover:bg-gray-700 cursor-pointer text-white text-sm"
                >
                  {{ brand.value }}
                </li>
              </ul>
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-2">Модель автомобиля</label>
              <input v-model="newOrder.car_model" type="text" class="w-full px-4 py-2 bg-gray-800 rounded-lg text-white" placeholder="Например: Camry">
            </div>
          </div>

          <!-- Дата и время с валидацией -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-sm text-gray-400 mb-2">Желаемая дата</label>
              <input 
                v-model="newOrder.desired_date" 
                type="date" 
                :min="minDate"
                @change="validateDateTime"
                class="w-full px-4 py-2 bg-gray-800 rounded-lg text-white"
              />
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-2">Желаемое время</label>
              <input 
                v-model="newOrder.desired_time" 
                type="time" 
                min="10:00"
                max="20:00"
                class="w-full px-4 py-2 bg-gray-800 rounded-lg text-white"
              />
              <p class="text-xs text-gray-500 mt-1">Студия работает с 10:00 до 20:00</p>
            </div>
          </div>

          <!-- Комментарий -->
          <div>
            <label class="block text-sm text-gray-400 mb-2">Комментарий</label>
            <textarea v-model="newOrder.comment" rows="3" class="w-full px-4 py-2 bg-gray-800 rounded-lg text-white resize-none"></textarea>
          </div>
        </div>
        <div class="sticky bottom-0 bg-gray-900 px-6 py-4 border-t border-gray-800 flex gap-3">
          <button @click="createOrderModalVisible = false" class="flex-1 px-4 py-2 border border-gray-700 rounded-lg text-gray-400 hover:text-white">Отмена</button>
          <button @click="submitNewOrder" :disabled="creatingOrder" class="flex-1 px-4 py-2 bg-[#fc9303] rounded-lg text-black font-semibold hover:bg-[#ff6b00]">
            {{ creatingOrder ? 'Создание...' : 'Создать заказ' }}
          </button>
        </div>
      </div>
    </div>

    <ThePagination
      :page="pagination.page"
      :limit="pagination.limit"
      :total="pagination.total"
      @update:page="onPageChange"
      class="mt-4"
    />

    <AlertModal
      :show="alertModal.show"
      :title="alertModal.title"
      :message="alertModal.message"
      @close="alertModal.show = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { API_BASE } from '@/config/api.js'
import ThePagination from '@/components/ThePagination.vue'
import AlertModal from '@/components/admin/AlertModal.vue'

const clients = ref([])
const loading = ref(false)
const search = ref('')
const pagination = ref({ page: 1, limit: 30, total: 0 })
let debounceTimer = null
const alertModal = ref({ show: false, title: '', message: '' })
const showAlert = (title, message = '') => { alertModal.value = { show: true, title, message } }

const editModalVisible = ref(false)
const editForm = ref({ client_id: null, first_name: '', last_name: '', phone_number: '', email: '' })

const showModal = ref(false)
const selectedClient = ref(null)
const clientOrders = ref([])

// Данные для выбора услуг
const services = ref([])
const categories = ref([])
const selectedCategoryId = ref(null)

// DaData для марок (через backend-прокси)
const carBrandQuery = ref('')
const brandSuggestions = ref([])
const selectedBrandId = ref(null)
const selectedBrandName = ref('')
let brandDebounceTimer = null

// Минимальная дата (сегодня)
const minDate = new Date().toISOString().split('T')[0]

const createOrderModalVisible = ref(false)
const creatingOrder = ref(false)
const newOrder = ref({
  service_ids: [],
  car_brand: '',
  car_model: '',
  desired_date: '',
  desired_time: '',
  comment: ''
})

// Computed для услуг текущей категории
const currentServices = computed(() => {
  if (!selectedCategoryId.value) return []
  return services.value.filter(s => s.category_id === selectedCategoryId.value)
})

const currentCategoryName = computed(() => {
  const cat = categories.value.find(c => c.category_id === selectedCategoryId.value)
  return cat ? cat.name : ''
})

// Валидация даты и времени
const validateDateTime = () => {
  if (newOrder.value.desired_date && newOrder.value.desired_date < minDate) {
    showAlert('Некорректная дата', 'Нельзя выбрать прошедшую дату.')
    newOrder.value.desired_date = ''
    return false
  }
  if (newOrder.value.desired_time) {
    const hour = parseInt(newOrder.value.desired_time.split(':')[0])
    if (hour < 10 || hour >= 20) {
      showAlert('Некорректное время', 'Студия работает с 10:00 до 20:00. Выберите время в этом диапазоне.')
      newOrder.value.desired_time = ''
      return false
    }
  }
  return true
}

// Подсказки марок — через backend-прокси
const fetchBrandSuggestions = () => {
  const query = carBrandQuery.value.trim()
  if (!query) {
    brandSuggestions.value = []
    return
  }
  clearTimeout(brandDebounceTimer)
  brandDebounceTimer = setTimeout(async () => {
    try {
      const res = await fetch(`${API_BASE}/car-brand-suggest`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query, count: 20 })
      })
      const data = await res.json()
      brandSuggestions.value = data.suggestions || []
    } catch {}
  }, 300)
}

const selectBrand = (brand) => {
  selectedBrandId.value = brand.data.id
  selectedBrandName.value = brand.value
  carBrandQuery.value = brand.value
  brandSuggestions.value = []
  newOrder.value.car_brand = selectedBrandName.value
}

const fetchClients = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({ page: pagination.value.page, limit: pagination.value.limit })
    if (search.value) params.append('search', search.value)
    const res  = await fetch(`${API_BASE}/admin/clients?${params}`, { credentials: 'include' })
    const data = await res.json()
    if (data.success) {
      clients.value = data.clients
      pagination.value.total = data.total ?? clients.value.length
    }
  } catch {} finally {
    loading.value = false
  }
}

const onPageChange = (p) => {
  pagination.value.page = p
  fetchClients()
}

const fetchCategories = async () => {
  try {
    const res = await fetch(`${API_BASE}/categories`)
    const data = await res.json()
    if (data.success) categories.value = data.categories
  } catch {}
}

const fetchServices = async () => {
  try {
    const res = await fetch(`${API_BASE}/services`)
    const data = await res.json()
    if (data.success) services.value = data.services
  } catch {}
}

const debouncedFetch = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchClients(), 300)
}

const openEditModal = (client) => {
  editForm.value = { ...client }
  editModalVisible.value = true
}

const saveClient = async () => {
  try {
    const res = await fetch(`${API_BASE}/admin/clients/${editForm.value.client_id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(editForm.value)
    })
    const data = await res.json()
    if (data.success) {
      editModalVisible.value = false
      fetchClients()
    } else {
      showAlert('Ошибка', data.error || 'Не удалось сохранить')
    }
  } catch {
    showAlert('Ошибка соединения')
  }
}

const viewDetails = async (client) => {
  selectedClient.value = client
  try {
    const res = await fetch(`${API_BASE}/admin/clients/${client.client_id}`, { credentials: 'include' })
    const data = await res.json()
    if (data.success) {
      clientOrders.value = data.orders
      showModal.value = true
    }
  } catch {}
}

const openCreateOrderModal = (client) => {
  selectedClient.value = client
  selectedCategoryId.value = categories.value[0]?.category_id || null
  carBrandQuery.value = ''
  selectedBrandName.value = ''
  newOrder.value = { 
    service_ids: [], 
    car_brand: '', 
    car_model: '', 
    desired_date: '', 
    desired_time: '', 
    comment: '' 
  }
  createOrderModalVisible.value = true
}

const submitNewOrder = async () => {
  if (newOrder.value.service_ids.length === 0) {
    showAlert('Услуги не выбраны', 'Выберите хотя бы одну услугу.')
    return
  }
  
  // Валидация даты и времени перед отправкой
  if (!validateDateTime()) return
  
  creatingOrder.value = true
  try {
    const payload = {
      client_id: selectedClient.value.client_id,
      service_ids: newOrder.value.service_ids,
      car_brand: newOrder.value.car_brand,
      car_model: newOrder.value.car_model,
      desired_date: newOrder.value.desired_date,
      desired_time: newOrder.value.desired_time,
      comment: newOrder.value.comment
    }
    const res = await fetch(`${API_BASE}/order/create`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })
    const data = await res.json()
    if (data.success) {
      createOrderModalVisible.value = false
      await viewDetails(selectedClient.value)
    } else {
      showAlert('Ошибка', data.error || 'Не удалось создать заказ')
    }
  } catch {
    showAlert('Ошибка соединения')
  } finally {
    creatingOrder.value = false
  }
}

const exportCSV = () => {
  const params = new URLSearchParams()
  if (search.value) params.append('search', search.value)
  window.open(`${API_BASE}/admin/clients/export?${params.toString()}`, '_blank')
}

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('ru-RU')
}

const getStatusName = (statusName) => {
  const names = { pending: 'Ожидание', confirmed: 'Подтверждён', in_progress: 'В работе', completed: 'Выполнен', cancelled: 'Отменён' }
  return names[statusName] || statusName
}

const getStatusClass = (statusName) => {
  const classes = {
    pending: 'bg-yellow-500/20 text-yellow-400',
    confirmed: 'bg-blue-500/20 text-blue-400',
    in_progress: 'bg-orange-500/20 text-orange-400',
    completed: 'bg-green-500/20 text-green-400',
    cancelled: 'bg-red-500/20 text-red-400'
  }
  return classes[statusName] || 'bg-gray-500/20 text-gray-400'
}

onMounted(() => {
  fetchClients()
  fetchCategories()
  fetchServices()
})
</script>

<style scoped>
/* Чётко задаём размеры чекбоксу */
.checkbox-custom {
  width: 1.25rem;
  height: 1.25rem;
  accent-color: #fc9303;
  border-radius: 0.25rem;
  flex-shrink: 0;
}
button {
  cursor: pointer;
}
</style>