<template>
  <div class="admin-orders">
    <h2 class="text-2xl font-bold mb-6">Управление заказами</h2>

    <!-- Подсказка -->
    <div class="mb-4 flex items-center gap-2 text-sm text-gray-400 bg-gray-800/30 rounded-lg p-3 border border-gray-700">
      <svg class="w-5 h-5 text-[#fc9303]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      <span>💡 <strong class="text-white">Подсказка:</strong> Нажмите на любую строку заказа, чтобы открыть окно управления прогрессом выполнения услуг</span>
    </div>

    <!-- Фильтры -->
    <div class="bg-gray-900/50 rounded-xl border border-gray-800 p-5 mb-6">
      <div class="flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm text-gray-400 mb-1">Клиент</label>
          <input
            v-model="filters.search"
            type="text"
            placeholder="Имя, фамилия или телефон..."
            class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:border-[#fc9303] focus:outline-none transition"
          />
        </div>

        <div class="flex-1 min-w-[200px]">
          <label class="block text-sm text-gray-400 mb-1">Услуга</label>
          <select
            v-model="filters.service"
            class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:border-[#fc9303] focus:outline-none transition"
          >
            <option value="all">Все услуги</option>
            <option v-for="service in uniqueServices" :key="service" :value="service">{{ service }}</option>
          </select>
        </div>

        <div class="flex-1 min-w-[150px]">
          <label class="block text-sm text-gray-400 mb-1">Статус</label>
          <select
            v-model="filters.status"
            class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:border-[#fc9303] focus:outline-none transition"
          >
            <option value="all">Все статусы</option>
            <option v-for="s in statuses" :key="s.status_id" :value="s.status_id">{{ s.name }}</option>
          </select>
        </div>

        <div class="flex-1 min-w-[160px]">
          <label class="block text-sm text-gray-400 mb-1">Дата заявки от</label>
          <input
            v-model="filters.dateFrom"
            type="date"
            class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:border-[#fc9303] focus:outline-none transition"
          />
        </div>

        <div class="flex-1 min-w-[160px]">
          <label class="block text-sm text-gray-400 mb-1">Дата заявки до</label>
          <input
            v-model="filters.dateTo"
            type="date"
            class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:border-[#fc9303] focus:outline-none transition"
          />
        </div>

        <div class="flex gap-3">
          <button
            @click="resetFilters"
            class="px-5 py-2 bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700 transition"
          >
            Сбросить
          </button>
          <button
            @click="exportFilteredCSV"
            class="px-5 py-2 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-black font-semibold rounded-lg hover:opacity-90 transition"
          >
            📥 Экспорт CSV
          </button>
        </div>
      </div>

      <div v-if="hasActiveFilters" class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-800">
        <span class="text-sm text-gray-400 mr-2">Активные фильтры:</span>
        <span v-if="filters.search" class="inline-flex items-center gap-1 px-2 py-1 bg-gray-800 rounded-lg text-sm text-[#fc9303]">
          Поиск: {{ filters.search }}
          <button @click="filters.search = ''" class="hover:text-white">×</button>
        </span>
        <span v-if="filters.service !== 'all'" class="inline-flex items-center gap-1 px-2 py-1 bg-gray-800 rounded-lg text-sm text-[#fc9303]">
          Услуга: {{ filters.service }}
          <button @click="filters.service = 'all'" class="hover:text-white">×</button>
        </span>
        <span v-if="filters.status !== 'all'" class="inline-flex items-center gap-1 px-2 py-1 bg-gray-800 rounded-lg text-sm text-[#fc9303]">
          Статус: {{ statuses.find(s => s.status_id == filters.status)?.name || filters.status }}
          <button @click="filters.status = 'all'" class="hover:text-white">×</button>
        </span>
        <span v-if="filters.dateFrom" class="inline-flex items-center gap-1 px-2 py-1 bg-gray-800 rounded-lg text-sm text-[#fc9303]">
          С {{ formatDateSimple(filters.dateFrom) }}
          <button @click="filters.dateFrom = ''" class="hover:text-white">×</button>
        </span>
        <span v-if="filters.dateTo" class="inline-flex items-center gap-1 px-2 py-1 bg-gray-800 rounded-lg text-sm text-[#fc9303]">
          По {{ formatDateSimple(filters.dateTo) }}
          <button @click="filters.dateTo = ''" class="hover:text-white">×</button>
        </span>
      </div>
    </div>

    <!-- Статистика -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
      <div class="bg-gray-900/50 rounded-xl border border-gray-800 p-4 text-center">
        <p class="text-2xl font-bold text-[#fc9303]">{{ filteredOrders.length }}</p>
        <p class="text-xs text-gray-400">Всего заказов</p>
      </div>
      <div class="bg-gray-900/50 rounded-xl border border-gray-800 p-4 text-center">
        <p class="text-2xl font-bold text-yellow-400">{{ statusCounts[1] || 0 }}</p>
        <p class="text-xs text-gray-400">Новых</p>
      </div>
      <div class="bg-gray-900/50 rounded-xl border border-gray-800 p-4 text-center">
        <p class="text-2xl font-bold text-orange-400">{{ statusCounts[2] || 0 }}</p>
        <p class="text-xs text-gray-400">В работе</p>
      </div>
      <div class="bg-gray-900/50 rounded-xl border border-gray-800 p-4 text-center">
        <p class="text-2xl font-bold text-green-400">{{ (statusCounts[3] || 0) + (statusCounts[4] || 0) }}</p>
        <p class="text-xs text-gray-400">Завершены</p>
      </div>
      <div class="bg-gray-900/50 rounded-xl border border-gray-800 p-4 text-center">
        <p class="text-xl font-bold text-[#fc9303] truncate" :title="totalAmount.toLocaleString() + ' ₽'">
          {{ totalAmount.toLocaleString() }} ₽
        </p>
        <p class="text-xs text-gray-400">Общая сумма</p>
      </div>
    </div>

    <!-- Таблица заказов -->
    <div v-if="loading" class="text-center py-12">
      <div class="w-10 h-10 border-4 border-[#fc9303] border-t-transparent rounded-full animate-spin mx-auto"></div>
    </div>
    <div v-else class="overflow-x-auto">
      <table class="w-full text-left min-w-[800px]">
        <thead class="bg-gray-800">
          <tr>
            <th class="p-4 cursor-pointer hover:text-[#fc9303] transition" @click="sortBy('client')">
              Клиент <span class="text-xs ml-1">{{ sortField === 'client' ? (sortOrder === 'asc' ? '↑' : '↓') : '' }}</span>
            </th>
            <th class="p-4">Услуги</th>
            <th class="p-4">Автомобиль</th>
            <th class="p-4 cursor-pointer hover:text-[#fc9303] transition" @click="sortBy('order_date')">
              Дата заявки <span class="text-xs ml-1">{{ sortField === 'order_date' ? (sortOrder === 'asc' ? '↑' : '↓') : '' }}</span>
            </th>
            <th class="p-4">Желаемая дата</th>
            <th class="p-4 cursor-pointer hover:text-[#fc9303] transition" @click="sortBy('total_price')">
              Сумма <span class="text-xs ml-1">{{ sortField === 'total_price' ? (sortOrder === 'asc' ? '↑' : '↓') : '' }}</span>
            </th>
            <th class="p-4">Статус</th>
            <th class="p-4">Действия</th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="order in sortedAndFilteredOrders" 
            :key="order.order_id" 
            class="border-b border-gray-800 hover:bg-gray-800/30 transition cursor-pointer group"
            @click="openOrderModal(order)"
          >
            <td class="p-4" @click.stop>
              {{ order.first_name }} {{ order.last_name }}<br>
              <span class="text-xs text-gray-500">{{ order.phone_number }}</span>
            </td>
            <td class="p-4" @click.stop>
              <div class="max-w-xs">
                <span class="text-sm">{{ order.service_names || '—' }}</span>
              </div>
            </td>
            <td class="p-4">{{ order.brand_name || '' }} {{ order.model_name || '' }}</td>
            <td class="p-4">{{ formatDate(order.order_date) }}</td>
            <td class="p-4">
              {{ formatDate(order.desired_date) }}<br>
              <span class="text-xs text-gray-500">{{ order.desired_time || '' }}</span>
            </td>
            <td class="p-4 font-semibold text-[#fc9303]">{{ formatPrice(order.total_price) }}</td>
            <td class="p-4">
              <span :class="['px-3 py-1 rounded-full text-xs font-semibold', getStatusColor(order.status_id)]">
                {{ order.status_name }}
              </span>
            </td>
            <td class="p-4" @click.stop>
              <select @change="updateStatus(order.order_id, $event.target.value)" :value="order.status_id" class="bg-gray-800 border border-gray-700 rounded-lg px-2 py-1 text-sm focus:border-[#fc9303] focus:outline-none">
                <option v-for="s in statuses" :key="s.status_id" :value="s.status_id">{{ s.name }}</option>
              </select>
            </td>
          </tr>
          <tr v-if="sortedAndFilteredOrders.length === 0">
            <td colspan="8" class="p-12 text-center text-gray-400">Заказов не найдено. Попробуйте изменить фильтры.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <ThePagination
      :page="pagination.page"
      :limit="pagination.limit"
      :total="pagination.total"
      @update:page="onPageChange"
      class="mt-2"
    />

    <!-- Модальное окно для управления прогрессом -->
    <AdminOrderModal
      :visible="modalVisible"
      :order="selectedOrder"
      @close="modalVisible = false"
      @updated="refreshOrders"
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
import AdminOrderModal from '@/components/AdminOrderModal.vue'
import { API_BASE } from '@/config/api.js'
import ThePagination from '@/components/ThePagination.vue'
import AlertModal from '@/components/admin/AlertModal.vue'

const orders = ref([])
const statuses = ref([])
const loading = ref(false)
const pagination = ref({ page: 1, limit: 50, total: 0 })
const sortField = ref('order_date')
const sortOrder = ref('desc')
const modalVisible = ref(false)
const selectedOrder = ref(null)
const alertModal = ref({ show: false, title: '', message: '' })
const showAlert = (title, message = '') => { alertModal.value = { show: true, title, message } }

const filters = ref({
  search: '',
  service: 'all',
  status: 'all',
  dateFrom: '',
  dateTo: ''
})

const openOrderModal = (order) => {
  selectedOrder.value = order
  modalVisible.value = true
}

const refreshOrders = () => {
  fetchOrders()
}

const parseServiceNames = (serviceNamesStr) => {
  if (!serviceNamesStr) return []
  return serviceNamesStr.split(',').map(s => s.trim()).filter(s => s)
}

const uniqueServices = computed(() => {
  const services = new Set()
  orders.value.forEach(order => {
    if (order.service_names) {
      const servicesList = parseServiceNames(order.service_names)
      servicesList.forEach(s => services.add(s))
    }
  })
  return Array.from(services).sort()
})

const hasActiveFilters = computed(() => {
  return filters.value.search !== '' ||
         filters.value.service !== 'all' ||
         filters.value.status !== 'all' ||
         filters.value.dateFrom !== '' ||
         filters.value.dateTo !== ''
})

const filteredOrders = computed(() => {
  let result = [...orders.value]

  if (filters.value.search) {
    const searchLower = filters.value.search.toLowerCase()
    result = result.filter(order =>
      order.first_name?.toLowerCase().includes(searchLower) ||
      order.last_name?.toLowerCase().includes(searchLower) ||
      order.phone_number?.includes(searchLower)
    )
  }

  if (filters.value.service !== 'all') {
    result = result.filter(order => {
      if (!order.service_names) return false
      const servicesList = parseServiceNames(order.service_names)
      return servicesList.includes(filters.value.service)
    })
  }

  if (filters.value.status !== 'all') {
    result = result.filter(order => String(order.status_id) === String(filters.value.status))
  }

  if (filters.value.dateFrom) {
    const fromDate = new Date(filters.value.dateFrom)
    fromDate.setHours(0, 0, 0, 0)
    result = result.filter(order => {
      const orderDate = new Date(order.order_date)
      return orderDate >= fromDate
    })
  }

  if (filters.value.dateTo) {
    const toDate = new Date(filters.value.dateTo)
    toDate.setHours(23, 59, 59, 999)
    result = result.filter(order => {
      const orderDate = new Date(order.order_date)
      return orderDate <= toDate
    })
  }

  return result
})

const sortedAndFilteredOrders = computed(() => {
  let result = [...filteredOrders.value]

  result.sort((a, b) => {
    let aVal, bVal
    switch (sortField.value) {
      case 'client':
        aVal = `${a.first_name || ''} ${a.last_name || ''}`
        bVal = `${b.first_name || ''} ${b.last_name || ''}`
        break
      case 'order_date':
        aVal = new Date(a.order_date)
        bVal = new Date(b.order_date)
        break
      case 'total_price':
        aVal = parseFloat(a.total_price) || 0
        bVal = parseFloat(b.total_price) || 0
        break
      default:
        aVal = a.order_date
        bVal = b.order_date
    }

    if (sortOrder.value === 'asc') {
      return aVal > bVal ? 1 : -1
    } else {
      return aVal < bVal ? 1 : -1
    }
  })

  return result
})

const statusCounts = computed(() => {
  const counts = {}
  filteredOrders.value.forEach(order => {
    const id = order.status_id
    counts[id] = (counts[id] || 0) + 1
  })
  return counts
})

const totalAmount = computed(() => {
  const sum = filteredOrders.value.reduce((sum, order) => {
    const price = parseFloat(order.total_price)
    return sum + (isNaN(price) ? 0 : price)
  }, 0)
  return sum
})

const formatPrice = (price) => {
  if (!price && price !== 0) return '0 ₽'
  const num = parseFloat(price)
  if (isNaN(num)) return '0 ₽'
  return num.toLocaleString('ru-RU') + ' ₽'
}

const sortBy = (field) => {
  if (sortField.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortOrder.value = 'asc'
  }
}

const resetFilters = () => {
  filters.value = {
    search: '',
    service: 'all',
    status: 'all',
    dateFrom: '',
    dateTo: ''
  }
}

const fetchStatuses = async () => {
  try {
    const res = await fetch(`${API_BASE}/admin/order-statuses`, { credentials: 'include' })
    const data = await res.json()
    if (data.success) statuses.value = data.statuses
  } catch {}
}

const fetchOrders = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page:  pagination.value.page,
      limit: pagination.value.limit,
    })
    const res  = await fetch(`${API_BASE}/admin/orders?${params}`, { credentials: 'include' })
    const data = await res.json()
    if (data.success) {
      orders.value = data.orders
      pagination.value.total = data.total ?? orders.value.length
    }
  } catch {} finally {
    loading.value = false
  }
}

const onPageChange = (p) => {
  pagination.value.page = p
  fetchOrders()
}

const updateStatus = async (orderId, newStatusId) => {
  try {
    const res = await fetch(`${API_BASE}/admin/orders/${orderId}/status`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ status_id: parseInt(newStatusId) })
    })
    const data = await res.json()
    if (data.success) {
      await fetchOrders()
    } else {
      showAlert('Ошибка', data.error || 'Не удалось обновить статус')
    }
  } catch {
    showAlert('Ошибка соединения')
  }
}

const exportFilteredCSV = () => {
  const headers = [
    'ID заказа', 'Клиент', 'Телефон', 'Услуги', 'Автомобиль',
    'Дата заявки', 'Желаемая дата', 'Желаемое время', 'Сумма', 'Статус'
  ]

  const rows = sortedAndFilteredOrders.value.map(order => [
    order.order_id,
    `${order.first_name || ''} ${order.last_name || ''}`,
    order.phone_number || '',
    order.service_names || '',
    `${order.brand_name || ''} ${order.model_name || ''}`.trim(),
    formatDateForCSV(order.order_date),
    formatDateForCSV(order.desired_date),
    order.desired_time || '',
    formatPriceForCSV(order.total_price),
    getStatusName(order.status_name)
  ])

  const csvContent = [headers, ...rows]
    .map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    .join('\n')

  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  const url = URL.createObjectURL(blob)
  link.href = url
  link.setAttribute('download', `orders_${new Date().toISOString().slice(0, 19)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

const formatPriceForCSV = (price) => {
  if (!price && price !== 0) return '0'
  const num = parseFloat(price)
  if (isNaN(num)) return '0'
  return num.toString().replace('.', ',')
}

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('ru-RU')
}

const formatDateSimple = (dateStr) => {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('ru-RU')
}

const formatDateForCSV = (dateStr) => {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('ru-RU')
}

const getStatusColor = (statusId) => {
  const colors = {
    1: 'bg-yellow-500/20 text-yellow-400',
    2: 'bg-orange-500/20 text-orange-400',
    3: 'bg-blue-500/20 text-blue-400',
    4: 'bg-green-500/20 text-green-400',
    5: 'bg-red-500/20 text-red-400'
  }
  return colors[statusId] || 'bg-gray-500/20 text-gray-400'
}

const getStatusName = (statusName) => statusName || '—'

onMounted(() => {
  fetchStatuses()
  fetchOrders()
})
</script>