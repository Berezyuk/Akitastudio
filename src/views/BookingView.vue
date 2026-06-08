<template>
  <div class="booking-page bg-black text-white min-h-screen">
    <!-- Hero секция -->
    <section class="relative pt-24 pb-16 overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-b from-black via-black to-[#4d4d4d]/20"></div>
      <div class="absolute inset-0 opacity-30">
        <div class="absolute top-20 left-10 w-64 h-64 bg-[#fc9303] rounded-full filter blur-[100px]"></div>
        <div class="absolute bottom-20 right-10 w-96 h-96 bg-[#ff6b00] rounded-full filter blur-[150px]"></div>
      </div>
      <div class="container mx-auto px-4 relative z-10 text-center">
        <h1 class="text-5xl md:text-6xl lg:text-7xl font-bold mb-6">
          <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">Записаться</span>
        </h1>
        <p class="text-xl text-gray-400 max-w-2xl mx-auto">Выберите услуги и оставьте заявку</p>
      </div>
    </section>

    <section class="py-16">
      <div class="container mx-auto px-4 max-w-6xl">
        <div class="bg-gray-900/50 backdrop-blur-sm rounded-2xl border border-gray-800 p-8">
          <form @submit.prevent="handleSubmit" class="space-y-8">
            <!-- Выбор услуг по категориям -->
            <div class="flex flex-col lg:flex-row gap-8">
              <div class="lg:w-1/3">
                <h3 class="text-xl font-semibold mb-4 border-l-2 border-[#fc9303] pl-3">Категории</h3>
                <div class="space-y-3">
                  <button
                    v-for="cat in categories"
                    :key="cat.category_id"
                    @click="selectedCategoryId = cat.category_id"
                    type="button"
                    class="w-full text-left px-5 py-3 rounded-xl transition-all duration-300"
                    :class="selectedCategoryId === cat.category_id 
                      ? 'bg-[#fc9303]/20 text-[#fc9303] border-l-2 border-[#fc9303]' 
                      : 'bg-gray-800 text-gray-400 hover:bg-gray-700'"
                  >
                    {{ cat.name }}
                  </button>
                </div>
              </div>

              <div class="lg:w-2/3">
                <h3 class="text-xl font-semibold mb-4 border-l-2 border-[#fc9303] pl-3">
                  {{ currentCategoryName || 'Выберите категорию' }}
                </h3>
                <div v-if="!selectedCategoryId" class="text-center py-12 text-gray-500">
                  Выберите категорию, чтобы увидеть услуги
                </div>
                <div v-else-if="currentServices.length === 0" class="text-center py-12 text-gray-500">
                  В этой категории пока нет услуг
                </div>
                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <label
                    v-for="service in currentServices"
                    :key="service.service_id"
                    class="flex items-center gap-3 p-3 bg-gray-800 rounded-xl cursor-pointer hover:bg-gray-700 transition group"
                  >
                    <input
                      type="checkbox"
                      :value="service.service_id"
                      v-model="selectedServices"
                      class="w-5 h-5 accent-[#fc9303] rounded flex-shrink-0"
                    />
                    <span class="text-white group-hover:text-[#fc9303] transition">{{ service.name }}</span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Контактные данные -->
            <div class="space-y-4">
              <h3 class="text-xl font-semibold border-l-2 border-[#fc9303] pl-3">Контактные данные</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm text-gray-400 mb-2">Имя *</label>
                  <input v-model="form.name" type="text" class="w-full px-5 py-4 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303]" />
                </div>
                <div>
                  <label class="block text-sm text-gray-400 mb-2">Телефон *</label>
                  <input 
                    v-model="form.phone" 
                    type="tel" 
                    class="w-full px-5 py-4 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303]" 
                    placeholder="+7 (___) ___-__-__"
                  />
                </div>
              </div>
            </div>

            <!-- Автомобиль -->
            <div class="space-y-4">
              <h3 class="text-xl font-semibold border-l-2 border-[#fc9303] pl-3">Автомобиль</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="relative">
                  <label class="block text-sm text-gray-400 mb-2">Марка *</label>
                  <input 
                    v-model="carBrandQuery"
                    @input="fetchBrandSuggestions"
                    @focus="fetchBrandSuggestions"
                    type="text"
                    class="w-full px-5 py-4 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303]"
                    placeholder="Начните вводить или выберите из списка"
                  />
                  <ul v-if="brandSuggestions.length" class="absolute z-20 w-full bg-gray-800 border border-gray-700 rounded-xl mt-1 max-h-60 overflow-y-auto custom-scroll">
                    <li 
                      v-for="brand in brandSuggestions" 
                      :key="brand.data.id"
                      @click="selectBrand(brand)"
                      class="px-5 py-3 hover:bg-gray-700 cursor-pointer text-white transition-colors"
                    >
                      {{ brand.value }}
                    </li>
                  </ul>
                </div>
                <div class="relative">
                  <label class="block text-sm text-gray-400 mb-2">Модель *</label>
                  <input
                    v-model="form.carModel"
                    type="text"
                    @input="onModelInput"
                    @focus="onModelFocus"
                    @blur="onModelBlur"
                    class="w-full px-5 py-4 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303]"
                    :placeholder="allModelsForBrand.length ? 'Начните вводить или выберите из списка' : 'Например: Camry'"
                  />
                  <ul v-if="modelSuggestions.length" class="absolute z-20 w-full bg-gray-800 border border-gray-700 rounded-xl mt-1 max-h-60 overflow-y-auto custom-scroll">
                    <li
                      v-for="model in modelSuggestions"
                      :key="model.model_id"
                      @mousedown.prevent="selectModel(model)"
                      class="px-5 py-3 hover:bg-gray-700 cursor-pointer text-white transition-colors"
                    >
                      {{ model.name }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Пожелания -->
            <div class="space-y-4">
              <h3 class="text-xl font-semibold border-l-2 border-[#fc9303] pl-3">Пожелания по дате и времени</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm text-gray-400 mb-2">Желаемая дата</label>
                  <input 
                    v-model="form.desiredDate" 
                    type="date" 
                    :min="minDate"
                    class="w-full px-5 py-4 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303]" 
                  />
                </div>
                <div>
                  <label class="block text-sm text-gray-400 mb-2">Желаемое время</label>
                  <input 
                    v-model="form.desiredTime" 
                    type="time" 
                    min="10:00"
                    max="20:00"
                    class="w-full px-5 py-4 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303]" 
                  />
                </div>
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-2">Комментарий</label>
                <textarea v-model="form.comment" rows="3" class="w-full px-5 py-4 bg-gray-800 border border-gray-700 rounded-xl text-white resize-none focus:outline-none focus:border-[#fc9303]"></textarea>
              </div>
            </div>

            <!-- Согласие на обработку данных -->
            <div class="flex items-center gap-2">
              <input type="checkbox" v-model="agreed" id="agreement" class="w-5 h-5 accent-[#fc9303] rounded flex-shrink-0">
              <label for="agreement" class="text-sm text-gray-400">
                Нажимая кнопку, Вы даете согласие на обработку персональных данных и соглашаетесь с 
                <a href="#" class="text-[#fc9303] hover:underline">Политикой конфиденциальности</a> *
              </label>
            </div>

            <button type="submit" :disabled="loading" class="w-full bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-white font-semibold py-5 rounded-xl transition-all duration-300 hover:scale-[1.02] disabled:opacity-50">
              {{ loading ? 'Отправка...' : 'Отправить заявку' }}
            </button>
          </form>
        </div>
      </div>
    </section>

    <!-- Модальное окно успеха -->
    <Transition name="modal">
      <div v-if="successMessage" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="successMessage = null">
        <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-md p-6 text-center">
          <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-500/20 flex items-center justify-center">
            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
          </div>
          <h3 class="text-xl font-bold mb-2 text-white">Заявка отправлена!</h3>
          <p class="text-gray-400 mb-6">{{ successMessage }}</p>
          <button @click="successMessage = null" class="w-full bg-[#fc9303] rounded-lg text-black font-semibold py-2 hover:bg-[#ff6b00] transition">Отлично</button>
        </div>
      </div>
    </Transition>

    <!-- Модальное окно для подтверждения исправления модели -->
    <Transition name="modal">
      <div v-if="showModelConfirm" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="closeModelConfirm">
        <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-md p-6">
          <h3 class="text-xl font-bold mb-4">Уточните модель</h3>
          <p class="text-gray-300 mb-4">Возможно, вы имели в виду:</p>
          <p class="text-lg font-semibold text-[#fc9303] mb-6">{{ suggestedModel }}</p>
          <div class="flex gap-3">
            <button @click="acceptModelSuggestion" class="flex-1 px-4 py-2 bg-[#fc9303] rounded-lg text-black font-semibold hover:bg-[#ff6b00]">Использовать</button>
            <button @click="rejectModelSuggestion" class="flex-1 px-4 py-2 border border-gray-700 rounded-lg text-gray-400 hover:text-white">Оставить своё</button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Модальное окно ошибок -->
    <Transition name="modal">
      <div v-if="errorMessage" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="errorMessage = null">
        <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-md p-6">
          <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-red-500/20 flex items-center justify-center">
            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </div>
          <h3 class="text-xl font-bold mb-4 text-center text-red-500">Ошибка</h3>
          <p class="text-gray-300 mb-6 text-center">{{ errorMessage }}</p>
          <button @click="errorMessage = null" class="w-full bg-[#fc9303] rounded-lg text-black font-semibold py-2 hover:bg-[#ff6b00]">Закрыть</button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { API_BASE } from '@/config/api.js'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

// Данные
const services = ref([])
const categories = ref([])
const selectedCategoryId = ref(null)
const selectedServices = ref([])
const loading = ref(false)
const successMessage = ref(null)
const errorMessage = ref(null)

// Модалка для модели
const showModelConfirm = ref(false)
const suggestedModel = ref('')
let pendingModelValidation = null

// Форма
const form = ref({
  name: '',
  phone: '',
  carModel: '',
  desiredDate: '',
  desiredTime: '',
  comment: ''
})

const agreed = ref(false)

// DaData для марок (запросы идут через наш backend-прокси, токен в JS не хранится)
const carBrandQuery = ref('')
const brandSuggestions = ref([])
const selectedBrandId = ref(null)
const selectedBrandName = ref('')

// Autocomplete для моделей (из нашей БД, пополняется через DaData cleaner)
const allModelsForBrand = ref([])
const modelSuggestions = ref([])

// Минимальная дата
const minDate = new Date().toISOString().split('T')[0]

// API вызовы
const fetchCategories = async () => {
  try {
    const res = await fetch(`${API_BASE}/categories`)
    const data = await res.json()
    if (data.success) categories.value = data.categories
  } catch (err) { console.error(err) }
}

const fetchServices = async () => {
  try {
    const res = await fetch(`${API_BASE}/services`)
    const data = await res.json()
    if (data.success) services.value = data.services
  } catch (err) { console.error(err) }
}

// Computed
const currentServices = computed(() => {
  if (!selectedCategoryId.value) return []
  return services.value.filter(s => s.category_id === selectedCategoryId.value)
})

const currentCategoryName = computed(() => {
  const cat = categories.value.find(c => c.category_id === selectedCategoryId.value)
  return cat ? cat.name : ''
})

// Подсказки марок — через backend-прокси (токен DaData скрыт на сервере)
const fetchBrandSuggestions = async () => {
  const query = carBrandQuery.value.trim()
  if (!query) {
    brandSuggestions.value = []
    return
  }
  try {
    const res = await fetch(`${API_BASE}/car-brand-suggest`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ query, count: 20 })
    })
    const data = await res.json()
    brandSuggestions.value = data.suggestions || []
  } catch (err) {
    console.error('Brand suggest error:', err)
  }
}

const selectBrand = async (brand) => {
  selectedBrandId.value = brand.data.id
  selectedBrandName.value = brand.value
  carBrandQuery.value = brand.value
  brandSuggestions.value = []
  // Сбрасываем модель и грузим варианты для выбранной марки
  form.value.carModel = ''
  allModelsForBrand.value = []
  modelSuggestions.value = []
  try {
    const res = await fetch(`${API_BASE}/car-models?brand_name=${encodeURIComponent(brand.value)}`)
    const data = await res.json()
    if (data.success) allModelsForBrand.value = data.models
  } catch (err) {
    console.error('Model load error:', err)
  }
}

const onModelInput = () => {
  const q = form.value.carModel.trim().toLowerCase()
  modelSuggestions.value = q
    ? allModelsForBrand.value.filter(m => m.name.toLowerCase().includes(q))
    : allModelsForBrand.value.slice(0, 20)
}

const onModelFocus = () => {
  const q = form.value.carModel.trim().toLowerCase()
  modelSuggestions.value = q
    ? allModelsForBrand.value.filter(m => m.name.toLowerCase().includes(q))
    : allModelsForBrand.value.slice(0, 20)
}

const selectModel = (model) => {
  form.value.carModel = model.name
  modelSuggestions.value = []
}

const onModelBlur = () => {
  // Даём time для mousedown на элементе списка
  setTimeout(() => {
    modelSuggestions.value = []
    validateModel()
  }, 200)
}

// ========== Валидация модели через DaData ==========
const validateModel = async () => {
  if (!form.value.carModel.trim() || !carBrandQuery.value.trim()) return

  try {
    const res = await fetch(`${API_BASE}/validate-car`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ brand: carBrandQuery.value, model: form.value.carModel })
    })
    const data = await res.json()

    if (data.success && data.model && data.model !== form.value.carModel) {
      suggestedModel.value = data.model
      pendingModelValidation = { original: form.value.carModel, corrected: data.model }
      showModelConfirm.value = true
    } else if (!data.success && data.error) {
      errorMessage.value = data.error
      setTimeout(() => errorMessage.value = null, 3000)
    }
  } catch (err) {
    console.error('Validation error:', err)
  }
}

const acceptModelSuggestion = () => {
  if (pendingModelValidation) {
    form.value.carModel = pendingModelValidation.corrected
  }
  showModelConfirm.value = false
  pendingModelValidation = null
}

const rejectModelSuggestion = () => {
  showModelConfirm.value = false
  pendingModelValidation = null
}

const closeModelConfirm = () => {
  showModelConfirm.value = false
  pendingModelValidation = null
}

// Отправка формы
const handleSubmit = async () => {
  // Очищаем предыдущие сообщения
  errorMessage.value = null
  successMessage.value = null

  // 1. Услуги
  if (selectedServices.value.length === 0) {
    errorMessage.value = 'Выберите хотя бы одну услугу'
    return
  }

  // 2. Контакты
  if (!form.value.name.trim()) {
    errorMessage.value = 'Введите имя'
    return
  }
  const phoneDigits = form.value.phone.replace(/\D/g, '')
  if (phoneDigits.length < 11) {
    errorMessage.value = 'Введите корректный номер телефона (11 цифр)'
    return
  }

  // 3. Автомобиль
  if (!carBrandQuery.value.trim()) {
    errorMessage.value = 'Введите марку автомобиля'
    return
  }
  if (!form.value.carModel.trim()) {
    errorMessage.value = 'Введите модель автомобиля'
    return
  }

  // 4. Согласие
  if (!agreed.value) {
    errorMessage.value = 'Необходимо согласие на обработку персональных данных'
    return
  }

  // 5. Дата и время
  if (form.value.desiredDate && form.value.desiredDate < minDate) {
    errorMessage.value = 'Нельзя выбрать прошедшую дату'
    return
  }
  if (form.value.desiredTime) {
    const hour = parseInt(form.value.desiredTime.split(':')[0])
    if (hour < 10 || hour >= 20) {
      errorMessage.value = 'Студия работает с 10:00 до 20:00. Выберите время в этом диапазоне.'
      return
    }
  }

  // Отправка
  loading.value = true
  try {
    const payload = {
      service_ids: selectedServices.value,
      client_name: form.value.name,
      client_phone: phoneDigits,
      car_brand: carBrandQuery.value,
      car_model: form.value.carModel,
      desired_date: form.value.desiredDate,
      desired_time: form.value.desiredTime,
      comment: form.value.comment
    }

    if (authStore.isAuthenticated && authStore.user?.client_id) {
      payload.client_id = authStore.user.client_id
    }

    const res = await fetch(`${API_BASE}/order/create`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    })
    const data = await res.json()

    if (data.success) {
      successMessage.value = 'Ваша заявка принята! Мы свяжемся с вами в ближайшее время для подтверждения.'
      // Очистка формы
      selectedServices.value = []
      selectedCategoryId.value = null
      form.value = {
        name: '',
        phone: '',
        carModel: '',
        desiredDate: '',
        desiredTime: '',
        comment: ''
      }
      carBrandQuery.value = ''
      selectedBrandId.value = null
      selectedBrandName.value = ''
      brandSuggestions.value = []
      agreed.value = false
    } else {
      errorMessage.value = data.error || 'Ошибка отправки'
    }
  } catch (err) {
    errorMessage.value = 'Ошибка соединения с сервером'
    console.error(err)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchCategories()
  fetchServices()
})
</script>

<style scoped>
.custom-scroll::-webkit-scrollbar {
  width: 4px;
}
.custom-scroll::-webkit-scrollbar-track {
  background: #1f2937;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: #fc9303;
  border-radius: 4px;
}
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