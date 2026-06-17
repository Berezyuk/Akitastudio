<template>
  <div class="client-profile bg-black text-white min-h-screen">
    <!-- Hero секция -->
    <section class="relative pt-24 pb-12 overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-b from-black via-black to-[#4d4d4d]/20"></div>
      <div class="absolute inset-0 opacity-30">
        <div class="absolute top-20 left-10 w-64 h-64 bg-[#fc9303] rounded-full filter blur-[100px]"></div>
        <div class="absolute bottom-20 right-10 w-96 h-96 bg-[#ff6b00] rounded-full filter blur-[150px]"></div>
      </div>
      <div class="container mx-auto px-4 relative z-10 text-center">
        <h1 class="text-4xl md:text-5xl font-bold mb-4">
          <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">Личный кабинет</span>
        </h1>
        <p class="text-gray-400">Добро пожаловать, {{ profile?.first_name || 'клиент' }}!</p>
      </div>
    </section>

    <!-- Табы (только заказы и профиль) -->
    <section class="pb-16">
      <div class="container mx-auto px-4 max-w-5xl">
        <div class="flex flex-wrap gap-2 mb-8 border-b border-gray-800">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            class="px-6 py-3 rounded-t-lg transition-all duration-300 font-medium"
            :class="activeTab === tab.id 
              ? 'bg-gray-800 text-[#fc9303] border-b-2 border-[#fc9303]' 
              : 'text-gray-400 hover:text-white hover:bg-gray-800/50'"
          >
            {{ tab.name }}
          </button>
        </div>

        <!-- Мои заказы -->
        <div v-if="activeTab === 'orders'" class="space-y-4">
          <div v-if="loading.orders" class="text-center py-12">
            <div class="w-10 h-10 border-4 border-[#fc9303] border-t-transparent rounded-full animate-spin mx-auto"></div>
          </div>
          <div v-else-if="orders.length === 0" class="text-center py-12 bg-gray-900/50 rounded-xl border border-gray-800">
            <p class="text-gray-400">У вас пока нет заказов</p>
            <router-link to="/booking" class="inline-block mt-4 text-[#fc9303] hover:underline">Записаться →</router-link>
          </div>
          <div v-else v-for="order in orders" :key="order.order_id" class="bg-gray-900/50 rounded-xl border border-gray-800 p-5 hover:border-[#fc9303]/30 transition">
            <div class="flex flex-wrap justify-between items-start gap-4">
              <div class="flex-1">
                <div class="flex flex-wrap items-center gap-3 mb-2">
                  <span :class="['px-2 py-0.5 rounded-full text-xs font-semibold', getStatusColor(order.status_name)]">
                    {{ getStatusName(order.status_name) }}
                  </span>
                </div>

                <!-- Автомобиль -->
                <div class="flex items-center gap-2 mb-2">
                  <span class="text-sm text-gray-400">🚗</span>
                  <span class="text-white text-sm font-medium">
                    {{ order.car_brand || '?' }} {{ order.car_model || '' }}
                  </span>
                </div>

                <p class="text-gray-300 text-sm mb-2">{{ order.service_names }}</p>

                <!-- Прогресс выполнения -->
                <div v-if="getOrderProgress(order.order_id) > 0" class="mt-3">
                  <div class="flex items-center justify-between text-xs text-gray-400 mb-1">
                    <span>Прогресс выполнения</span>
                    <span>{{ getOrderProgress(order.order_id) }}%</span>
                  </div>
                  <div class="w-full bg-gray-700 rounded-full h-2">
                    <div 
                      class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] h-2 rounded-full transition-all duration-500"
                      :style="{ width: getOrderProgress(order.order_id) + '%' }"
                    ></div>
                  </div>
                </div>

                <!-- Фотоотчёт -->
                <div v-if="orderPhotos[order.order_id] && orderPhotos[order.order_id].length > 0" class="mt-3">
                  <div class="text-xs text-gray-400 mb-2">📸 Фотоотчёт ({{ orderPhotos[order.order_id].length }})</div>
                  <div class="flex flex-wrap gap-2">
                    <img 
                      v-for="photo in orderPhotos[order.order_id].slice(0, 4)" 
                      :key="photo.id"
                      :src="photo.photo_url"
                      class="w-16 h-16 rounded-lg object-cover cursor-pointer hover:opacity-80 transition border border-gray-700"
                      @click="openPhotoGallery(orderPhotos[order.order_id], 0)"
                    />
                    <div 
                      v-if="orderPhotos[order.order_id].length > 4"
                      class="w-16 h-16 rounded-lg bg-gray-800 flex items-center justify-center text-xs text-gray-400 cursor-pointer hover:bg-gray-700 transition border border-gray-700"
                      @click="openPhotoGallery(orderPhotos[order.order_id], 4)"
                    >
                      +{{ orderPhotos[order.order_id].length - 4 }}
                    </div>
                  </div>
                </div>

                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 mt-3">
                  <span>📅 Заявка: {{ formatDate(order.order_date) }}</span>
                  <span v-if="order.desired_date">⏱ Желаемая дата: {{ formatDate(order.desired_date) }} {{ order.desired_time }}</span>
                </div>
              </div>
              <div class="text-right">
                <p class="text-[#fc9303] font-bold text-xl">{{ formatPrice(order.total_price) }}</p>
                <div class="flex gap-2 mt-3">
                  <button
                    v-if="canCancel(order)"
                    @click="cancelOrder(order.order_id)"
                    class="px-3 py-1 text-xs border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-black transition"
                  >
                    Отменить
                  </button>
                  <button
                    v-if="canReschedule(order)"
                    @click="openRescheduleModal(order)"
                    class="px-3 py-1 text-xs border border-[#fc9303] text-[#fc9303] rounded-lg hover:bg-[#fc9303] hover:text-black transition"
                  >
                    Перенести
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Мои данные -->
        <div v-if="activeTab === 'profile'" class="bg-gray-900/50 rounded-xl border border-gray-800 p-6">
          <form @submit.prevent="updateProfile" class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div>
                <label class="block text-sm text-gray-400 mb-1">Имя</label>
                <input v-model="profileForm.first_name" type="text" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-[#fc9303] focus:outline-none" />
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Фамилия</label>
                <input v-model="profileForm.last_name" type="text" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-[#fc9303] focus:outline-none" />
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Отчество</label>
                <input v-model="profileForm.patronymic" type="text" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-[#fc9303] focus:outline-none" />
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Телефон</label>
                <input v-model="profileForm.phone_number" type="tel" disabled class="w-full px-4 py-3 bg-gray-700/50 border border-gray-700 rounded-lg text-gray-400" />
                <p class="text-xs text-gray-500 mt-1">Телефон нельзя изменить</p>
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm text-gray-400 mb-1">Email</label>
                <input v-model="profileForm.email" type="email" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-[#fc9303] focus:outline-none" />
              </div>
            </div>
            <button type="submit" :disabled="loading.profile" class="px-6 py-3 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-black font-semibold rounded-lg hover:opacity-90 transition">
              {{ loading.profile ? 'Сохранение...' : 'Сохранить изменения' }}
            </button>
          </form>
        </div>
      </div>
    </section>

    <!-- Модалка переноса записи -->
    <Transition name="modal">
      <div v-if="rescheduleModal.open" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="closeRescheduleModal">
        <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-md p-6">
          <h3 class="text-xl font-bold mb-4">Перенос записи</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm text-gray-400 mb-1">Новая дата</label>
              <input v-model="rescheduleForm.desired_date" type="date" :min="minDate" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg" />
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-1">Новое время</label>
              <input v-model="rescheduleForm.desired_time" type="time" min="10:00" max="20:00" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg" />
            </div>
          </div>
          <div class="flex gap-3 mt-6">
            <button @click="closeRescheduleModal" class="flex-1 px-4 py-2 border border-gray-700 rounded-lg text-gray-400 hover:text-white">Отмена</button>
            <button @click="submitReschedule" :disabled="loading.reschedule" class="flex-1 px-4 py-2 bg-[#fc9303] rounded-lg text-black font-semibold hover:bg-[#ff6b00]">
              {{ loading.reschedule ? 'Сохранение...' : 'Подтвердить' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Модалка просмотра фото (галерея) -->
    <Transition name="modal">
      <div v-if="galleryPhotos.length > 0 && galleryIndex !== null" class="fixed inset-0 bg-black/95 z-[60] flex items-center justify-center" @click="closePhotoGallery">
        <div class="relative max-w-[90vw] max-h-[90vh]">
          <img 
            :src="galleryPhotos[galleryIndex]?.photo_url" 
            class="max-w-full max-h-[85vh] object-contain rounded-lg"
            @click.stop
          />
          <p class="text-center text-white mt-4 text-sm">{{ galleryPhotos[galleryIndex]?.caption }}</p>
          
          <!-- Кнопки навигации -->
          <button 
            v-if="galleryPhotos.length > 1"
            @click.stop="prevPhoto"
            class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/50 hover:bg-black/70 transition flex items-center justify-center text-white"
          >
            ◀
          </button>
          <button 
            v-if="galleryPhotos.length > 1"
            @click.stop="nextPhoto"
            class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-black/50 hover:bg-black/70 transition flex items-center justify-center text-white"
          >
            ▶
          </button>
          
          <!-- Кнопка закрытия -->
          <button @click="closePhotoGallery" class="absolute top-4 right-4 w-10 h-10 rounded-full bg-black/50 hover:bg-black/70 transition text-white text-xl">✕</button>
          
          <!-- Индикатор номера фото -->
          <div class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-black/50 px-3 py-1 rounded-full text-sm text-white">
            {{ galleryIndex + 1 }} / {{ galleryPhotos.length }}
          </div>
        </div>
      </div>
    </Transition>

    <AlertModal
      :show="alertModal.show"
      :title="alertModal.title"
      :message="alertModal.message"
      @close="alertModal.show = false"
    />

    <ConfirmModal
      :show="confirmModal.show"
      :title="confirmModal.title"
      :message="confirmModal.message"
      @confirm="onConfirmOk"
      @cancel="onConfirmCancel"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { API_BASE } from '@/config/api.js'
import AlertModal from '@/components/admin/AlertModal.vue'
import ConfirmModal from '@/components/admin/ConfirmModal.vue'

const authStore = useAuthStore()
const activeTab = ref('orders')

const tabs = [
  { id: 'orders', name: '📋 Мои заказы' },
  { id: 'profile', name: '👤 Мои данные' },
]

// Данные
const orders = ref([])
const profile = ref({})
const progressData = ref({})
const orderPhotos = ref({}) // { orderId: [photos] }
const profileForm = ref({
  first_name: '',
  last_name: '',
  patronymic: '',
  phone_number: '',
  email: '',
})

// Галерея
const galleryPhotos = ref([])
const galleryIndex = ref(null)

const loading = ref({
  orders: false,
  profile: false,
  reschedule: false,
  progress: false,
  photos: false,
})

// Модалка переноса
const rescheduleModal = ref({ open: false, orderId: null })
const rescheduleForm = ref({ desired_date: '', desired_time: '' })

const alertModal = ref({ show: false, title: '', message: '' })
const showAlert = (title, message = '') => { alertModal.value = { show: true, title, message } }

const confirmModal = ref({ show: false, title: '', message: '' })
let confirmResolve = null
const askConfirm = (title, message = '') => new Promise(resolve => {
  confirmModal.value = { show: true, title, message }
  confirmResolve = resolve
})
const onConfirmOk = () => { confirmModal.value.show = false; confirmResolve?.(true) }
const onConfirmCancel = () => { confirmModal.value.show = false; confirmResolve?.(false) }

const minDate = new Date().toISOString().split('T')[0]

// API вызовы
const fetchOrders = async () => {
  loading.value.orders = true
  try {
    const res = await fetch(`${API_BASE}/user/orders`, { credentials: 'include' })
    const data = await res.json()
    if (data.success) {
      orders.value = data.orders
      // После загрузки заказов подгружаем фото для каждого
      for (const order of orders.value) {
        await fetchOrderPhotos(order.order_id)
      }
    }
  } catch {} finally {
    loading.value.orders = false
  }
}

const fetchProfile = async () => {
  loading.value.profile = true
  try {
    const res = await fetch(`${API_BASE}/user/profile`, { credentials: 'include' })
    const data = await res.json()
    if (data.success) {
      profile.value = data.profile
      profileForm.value = { ...data.profile }
    }
  } catch {} finally {
    loading.value.profile = false
  }
}

const fetchProgress = async () => {
  loading.value.progress = true
  try {
    const res = await fetch(`${API_BASE}/user/orders/progress`, { credentials: 'include' })
    const data = await res.json()
    if (data.success) {
      const progress = {}
      data.progress.forEach(item => {
        if (!progress[item.order_id]) progress[item.order_id] = {}
        progress[item.order_id][item.service_id] = item.progress_percent
      })
      progressData.value = progress
    }
  } catch {} finally {
    loading.value.progress = false
  }
}

const fetchOrderPhotos = async (orderId) => {
  try {
    const res = await fetch(`${API_BASE}/user/orders/${orderId}/photos`, { credentials: 'include' })
    const data = await res.json()
    if (data.success) {
      orderPhotos.value[orderId] = data.photos
    }
  } catch {}
}

const getOrderProgress = (orderId) => {
  const orderProgress = progressData.value[orderId]
  if (!orderProgress) return 0
  const values = Object.values(orderProgress)
  if (values.length === 0) return 0
  const sum = values.reduce((a, b) => a + b, 0)
  return Math.round(sum / values.length)
}

const updateProfile = async () => {
  loading.value.profile = true
  try {
    const res = await fetch(`${API_BASE}/user/profile`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(profileForm.value),
    })
    const data = await res.json()
    if (data.success) {
      await fetchProfile()
    } else {
      showAlert('Ошибка сохранения', data.error || '')
    }
  } catch {
    showAlert('Ошибка соединения')
  } finally {
    loading.value.profile = false
  }
}

const cancelOrder = async (orderId) => {
  if (!await askConfirm('Отменить запись?', 'Вы уверены, что хотите отменить запись?')) return

  try {
    const res = await fetch(`${API_BASE}/user/orders/${orderId}/cancel`, {
      method: 'POST',
      credentials: 'include',
    })
    const data = await res.json()
    if (data.success) {
      await fetchOrders()
    } else {
      showAlert('Ошибка', data.error || 'Не удалось отменить запись')
    }
  } catch {
    showAlert('Ошибка соединения')
  }
}

const openRescheduleModal = (order) => {
  rescheduleModal.value = { open: true, orderId: order.order_id }
  rescheduleForm.value = { desired_date: '', desired_time: '' }
}

const closeRescheduleModal = () => {
  rescheduleModal.value = { open: false, orderId: null }
  rescheduleForm.value = { desired_date: '', desired_time: '' }
}

const submitReschedule = async () => {
  if (!rescheduleForm.value.desired_date || !rescheduleForm.value.desired_time) {
    showAlert('Заполните поля', 'Укажите дату и время для переноса.')
    return
  }

  loading.value.reschedule = true
  try {
    const res = await fetch(`${API_BASE}/user/orders/${rescheduleModal.value.orderId}/reschedule`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(rescheduleForm.value),
    })
    const data = await res.json()
    if (data.success) {
      closeRescheduleModal()
      await fetchOrders()
    } else {
      showAlert('Ошибка', data.error || 'Не удалось перенести запись')
    }
  } catch {
    showAlert('Ошибка соединения')
  } finally {
    loading.value.reschedule = false
  }
}

// Галерея
const openPhotoGallery = (photos, startIndex = 0) => {
  galleryPhotos.value = photos
  galleryIndex.value = startIndex
  document.body.style.overflow = 'hidden'
}

const closePhotoGallery = () => {
  galleryPhotos.value = []
  galleryIndex.value = null
  document.body.style.overflow = ''
}

const prevPhoto = () => {
  if (galleryIndex.value > 0) {
    galleryIndex.value--
  } else {
    galleryIndex.value = galleryPhotos.value.length - 1
  }
}

const nextPhoto = () => {
  if (galleryIndex.value < galleryPhotos.value.length - 1) {
    galleryIndex.value++
  } else {
    galleryIndex.value = 0
  }
}

const canCancel = (order) => {
  if (order.status_name === 'completed' || order.status_name === 'cancelled') return false
  if (!order.desired_date) return true
  const desiredDateTime = new Date(order.desired_date + 'T' + (order.desired_time || '10:00'))
  const now = new Date()
  const hoursLeft = (desiredDateTime - now) / (1000 * 60 * 60)
  return hoursLeft > 1
}

const canReschedule = (order) => {
  return order.status_name !== 'completed' && order.status_name !== 'cancelled'
}

const formatDate = (dateStr) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('ru-RU')
}

const formatPrice = (price) => {
  if (!price) return '0 ₽'
  return Number(price).toLocaleString('ru-RU') + ' ₽'
}

const getStatusColor = (statusName) => {
  const colors = {
    pending: 'bg-yellow-500/20 text-yellow-400',
    confirmed: 'bg-blue-500/20 text-blue-400',
    in_progress: 'bg-orange-500/20 text-orange-400',
    completed: 'bg-green-500/20 text-green-400',
    cancelled: 'bg-red-500/20 text-red-400',
  }
  return colors[statusName] || 'bg-gray-500/20 text-gray-400'
}

const getStatusName = (statusName) => {
  const names = {
    pending: 'Ожидание',
    confirmed: 'Подтверждён',
    in_progress: 'В работе',
    completed: 'Выполнен',
    cancelled: 'Отменён',
  }
  return names[statusName] || statusName
}

onMounted(() => {
  fetchOrders()
  fetchProfile()
  fetchProgress()
})
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
  transform: scale(0.95);
}
</style>