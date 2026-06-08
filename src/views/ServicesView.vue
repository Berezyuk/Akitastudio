<template>
  <div class="services-page bg-black text-white min-h-screen">
    <!-- Hero секция -->
    <section class="relative pt-24 pb-16 overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-b from-black via-black to-[#4d4d4d]/20"></div>
      <div class="absolute inset-0 opacity-30">
        <div class="absolute top-20 left-10 w-64 h-64 bg-[#fc9303] rounded-full filter blur-[100px]"></div>
        <div class="absolute bottom-20 right-10 w-96 h-96 bg-[#ff6b00] rounded-full filter blur-[150px]"></div>
      </div>
      <div class="container mx-auto px-4 relative z-10 text-center">
        <h1 class="text-5xl md:text-6xl lg:text-7xl font-bold mb-6">
          <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">Услуги</span>
        </h1>
        <p class="text-xl text-gray-400 max-w-2xl mx-auto">
          Выберите услуги для записи или просто ознакомьтесь с нашими возможностями
        </p>
      </div>
    </section>

    <!-- Аккордеон категорий -->
    <section class="py-16">
      <div class="container mx-auto px-4 max-w-4xl">
        <div v-for="cat in categories" :key="cat.category_id" class="mb-6">
          <!-- Заголовок категории -->
          <button
            @click="toggleCategory(cat.category_id)"
            class="w-full flex justify-between items-center p-5 bg-gray-900/50 rounded-xl border border-gray-800 hover:border-[#fc9303] transition-all group"
          >
            <div class="flex items-center gap-4 flex-1 min-w-0">
              <div class="w-10 h-10 rounded-full bg-[#fc9303]/20 flex items-center justify-center group-hover:scale-110 transition flex-shrink-0">
                <span class="text-xl">{{ categoryIcons[cat.name] || '📁' }}</span>
              </div>
              <div class="text-left overflow-hidden">
                <h2 class="text-xl font-bold break-words">{{ cat.name }}</h2>
                <p class="text-sm text-gray-400">{{ getServicesCount(cat.category_id) }}</p>
              </div>
            </div>
            <svg
              class="w-6 h-6 transition-transform duration-300 flex-shrink-0"
              :class="{ 'rotate-180': openCategories.includes(cat.category_id) }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>

          <!-- Список услуг (раскрывается) -->
          <Transition name="expand">
            <div v-if="openCategories.includes(cat.category_id)" class="mt-3 p-4 bg-gray-800/30 rounded-xl border border-gray-800">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Минималистичная карточка услуги -->
                <div
                  v-for="service in getServicesByCategory(cat.category_id)"
                  :key="service.service_id"
                  class="service-card p-5 bg-gray-800/50 rounded-xl border border-gray-700 hover:border-[#fc9303]/50 transition-all duration-300 group"
                >
                  <div class="flex justify-between items-start mb-4">
                    <h3 class="font-bold text-lg text-white group-hover:text-[#fc9303] transition">{{ service.name }}</h3>
                    <span class="text-[#fc9303] font-bold whitespace-nowrap ml-2">от {{ service.base_price?.toLocaleString() }} ₽</span>
                  </div>
                  
                  <button
                    @click="openServiceModal(service)"
                    class="w-full py-2 rounded-lg border border-[#fc9303] text-sm hover:bg-[#fc9303] hover:text-black transition font-medium"
                  >
                    Подробнее
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </div>
    </section>

    <!-- Модальное окно с детальной информацией -->
    <Transition name="modal">
      <div v-if="selectedService" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closeModal">
        <!-- Затемнение -->
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>
        
        <!-- Модальное окно -->
        <div class="relative bg-gray-900 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto border border-gray-700 shadow-2xl">
          <!-- Кнопка закрытия -->
          <button
            @click="closeModal"
            class="absolute top-4 right-4 w-10 h-10 rounded-full bg-gray-800 hover:bg-gray-700 transition flex items-center justify-center z-10"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
          
          <div class="p-6 md:p-8">
            <!-- Заголовок -->
            <div class="mb-6">
              <h2 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent mb-2">
                {{ selectedService.name }}
              </h2>
              <div class="flex flex-wrap gap-4 text-sm">
                <div class="flex items-center gap-2">
                  <span class="text-[#fc9303]">💰</span>
                  <span>от {{ selectedService.base_price?.toLocaleString() }} ₽</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-[#fc9303]">⏱️</span>
                  <span>{{ formatDuration(selectedService.duration_minutes) }}</span>
                </div>
              </div>
            </div>
            
            <!-- Описание с маркированным списком -->
            <div class="mb-8">
              <h3 class="text-lg font-semibold mb-3 text-gray-200">📋 Описание услуги</h3>
              <div class="text-gray-300 leading-relaxed" v-html="formatDescription(selectedService.description)"></div>
            </div>
            
            <!-- Преимущества (если есть) -->
            <div v-if="selectedService.benefits" class="mb-8">
              <h3 class="text-lg font-semibold mb-3 text-gray-200">✨ Что вы получаете</h3>
              <ul class="space-y-2">
                <li v-for="(benefit, idx) in selectedService.benefits" :key="idx" class="flex items-start gap-2 text-gray-300">
                  <span class="text-[#fc9303] mt-1">✓</span>
                  <span>{{ benefit }}</span>
                </li>
              </ul>
            </div>
            
            <!-- Кнопка записи -->
            <button
              @click="selectService(selectedService)"
              class="w-full py-3 rounded-xl bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-black font-bold text-lg hover:opacity-90 transition shadow-lg"
            >
              Записаться на услугу
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Кнопка возврата на главную -->
    <div class="text-center pb-16">
      <router-link to="/" class="inline-flex items-center gap-2 text-gray-400 hover:text-white transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Вернуться на главную
      </router-link>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { API_BASE } from '@/config/api.js'

const router = useRouter()
const categories = ref([])
const services = ref([])
const openCategories = ref([])
const selectedService = ref(null)

// Маппинг категорий на иконки
const categoryIcons = {
  'Детейлинг-уход': '🧼',
  'Оклейка плёнкой': '🛡️',
  'Полировка автомобиля': '✨',
  'Керамические покрытия': '💎',
  'Ремонт и реставрация салона': '🪑',
  'Малярные работы': '🎨',
  'Дооснащение': '🔧',
  'Фирменные комплексы Akita': '🏆'
}

// Загрузка категорий и услуг
const fetchCategories = async () => {
  try {
    const res = await fetch(`${API_BASE}/categories`)
    const data = await res.json()
    if (data.success) categories.value = data.categories
  } catch (err) {
    console.error(err)
  }
}

const fetchServices = async () => {
  try {
    const res = await fetch(`${API_BASE}/services`)
    const data = await res.json()
    if (data.success) services.value = data.services
  } catch (err) {
    console.error(err)
  }
}

// Склонение для слова "услуга"
const getServicesDeclension = (num) => {
  const lastDigit = num % 10
  const lastTwo = num % 100
  
  if (lastTwo >= 11 && lastTwo <= 19) return 'услуг'
  if (lastDigit === 1) return 'услуга'
  if (lastDigit >= 2 && lastDigit <= 4) return 'услуги'
  return 'услуг'
}

const getServicesCount = (categoryId) => {
  const count = services.value.filter(s => s.category_id === categoryId).length
  return `${count} ${getServicesDeclension(count)}`
}

const getServicesByCategory = (categoryId) => {
  return services.value.filter(s => s.category_id === categoryId)
}

const toggleCategory = (id) => {
  if (openCategories.value.includes(id)) {
    openCategories.value = openCategories.value.filter(i => i !== id)
  } else {
    openCategories.value.push(id)
  }
}

const openServiceModal = (service) => {
  selectedService.value = service
  document.body.style.overflow = 'hidden'
}

const closeModal = () => {
  selectedService.value = null
  document.body.style.overflow = ''
}

const selectService = (service) => {
  closeModal()
  router.push({ path: '/booking', query: { service_id: service.service_id } })
}

// Форматирование описания (преобразование текста в маркированный список)
const formatDescription = (description) => {
  if (!description) return '<p class="text-gray-400">Описание отсутствует</p>'
  
  // Если в описании есть маркеры *, - или •, преобразуем их в HTML список
  if (description.includes('*') || description.includes('-') || description.includes('•')) {
    let lines = description.split('\n')
    let inList = false
    let html = ''
    
    lines.forEach(line => {
      const trimmed = line.trim()
      // Проверяем, начинается ли строка с маркера списка
      if (trimmed.startsWith('*') || trimmed.startsWith('-') || trimmed.startsWith('•')) {
        if (!inList) {
          html += '<ul class="space-y-2 list-none">'
          inList = true
        }
        const content = trimmed.substring(1).trim()
        html += `<li class="flex items-start gap-2"><span class="text-[#fc9303] mt-1">•</span><span>${content}</span></li>`
      } else {
        if (inList) {
          html += '</ul>'
          inList = false
        }
        if (trimmed) {
          html += `<p class="mb-3">${trimmed}</p>`
        }
      }
    })
    
    if (inList) html += '</ul>'
    return html
  }
  
  // Если нет маркеров, просто разбиваем на абзацы
  const paragraphs = description.split('\n').filter(p => p.trim())
  return paragraphs.map(p => `<p class="mb-3">${p.trim()}</p>`).join('')
}

// Форматирование времени с правильными падежами
const formatDuration = (minutes) => {
  if (!minutes) return '—'
  
  const hours = minutes / 60
  const days = hours / 24
  
  if (days >= 1) {
    const daysInt = Math.floor(days)
    const remainderHours = Math.round((days - daysInt) * 24)
    
    if (remainderHours > 0) {
      return `${daysInt} ${getDaysDeclension(daysInt)} ${remainderHours} ${getHoursDeclension(remainderHours)}`
    }
    return `${daysInt} ${getDaysDeclension(daysInt)}`
  }
  
  if (hours >= 1) {
    const hoursInt = Math.floor(hours)
    const remainderMinutes = minutes % 60
    
    if (remainderMinutes > 0) {
      return `${hoursInt} ${getHoursDeclension(hoursInt)} ${remainderMinutes} ${getMinutesDeclension(remainderMinutes)}`
    }
    return `${hoursInt} ${getHoursDeclension(hoursInt)}`
  }
  
  return `${minutes} ${getMinutesDeclension(minutes)}`
}

const getDaysDeclension = (num) => {
  const lastDigit = num % 10
  const lastTwo = num % 100
  
  if (lastTwo >= 11 && lastTwo <= 19) return 'дней'
  if (lastDigit === 1) return 'день'
  if (lastDigit >= 2 && lastDigit <= 4) return 'дня'
  return 'дней'
}

const getHoursDeclension = (num) => {
  const lastDigit = num % 10
  const lastTwo = num % 100
  
  if (lastTwo >= 11 && lastTwo <= 19) return 'часов'
  if (lastDigit === 1) return 'час'
  if (lastDigit >= 2 && lastDigit <= 4) return 'часа'
  return 'часов'
}

const getMinutesDeclension = (num) => {
  const lastDigit = num % 10
  const lastTwo = num % 100
  
  if (lastTwo >= 11 && lastTwo <= 19) return 'минут'
  if (lastDigit === 1) return 'минута'
  if (lastDigit >= 2 && lastDigit <= 4) return 'минуты'
  return 'минут'
}

onMounted(() => {
  fetchCategories()
  fetchServices()
})
</script>

<style scoped>
/* Анимация раскрытия категории */
.expand-enter-active,
.expand-leave-active {
  transition: all 0.3s ease;
}
.expand-enter-from,
.expand-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

/* Анимация модального окна */
.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
.modal-enter-from .relative,
.modal-leave-to .relative {
  transform: scale(0.95);
}

/* Стили для карточки услуги */
.service-card {
  transition: all 0.3s ease;
}

/* Перенос длинных слов на мобильных */
@media (max-width: 640px) {
  .break-words {
    word-break: break-word;
  }
}

/* Стили для скролла модального окна */
.modal-content {
  scrollbar-width: thin;
  scrollbar-color: #fc9303 #333;
}

.modal-content::-webkit-scrollbar {
  width: 6px;
}

.modal-content::-webkit-scrollbar-track {
  background: #333;
  border-radius: 10px;
}

.modal-content::-webkit-scrollbar-thumb {
  background: #fc9303;
  border-radius: 10px;
}
</style>