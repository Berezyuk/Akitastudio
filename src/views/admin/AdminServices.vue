<script setup>
import { ref, onMounted, watch } from 'vue'
import { API_BASE } from '@/config/api.js'
import ConfirmModal from '@/components/admin/ConfirmModal.vue'

// ── Услуги ──────────────────────────────────────────────────────────────────
const services = ref([])
const categories = ref([])
const loading = ref(false)
const showModal = ref(false)
const editingService = ref(null)

const confirmModal = ref({ show: false, title: '', message: '' })
let confirmResolve = null
const askConfirm = (title, message = '') => new Promise(resolve => {
  confirmModal.value = { show: true, title, message }
  confirmResolve = resolve
})
const onConfirmOk = () => { confirmModal.value.show = false; confirmResolve?.(true) }
const onConfirmCancel = () => { confirmModal.value.show = false; confirmResolve?.(false) }

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

const minutesToHours = (minutes) => minutes / 60
const hoursToMinutes = (hours) => Math.round(hours * 60)

watch(durationHours, (v) => { form.value.duration_minutes = hoursToMinutes(v) })
watch(() => form.value.duration_minutes, (v) => { if (v) durationHours.value = minutesToHours(v) })

const fetchServices = async () => {
  loading.value = true
  try {
    const res = await fetch(`${API_BASE}/admin/services`, { credentials: 'include' })
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
    const res = await fetch(`${API_BASE}/admin/service-categories`, { credentials: 'include' })
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
    ? `${API_BASE}/admin/services/${editingService.value.service_id}`
    : `${API_BASE}/admin/services`
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
  if (!await askConfirm('Удалить услугу?', `«${name}» будет удалена без возможности восстановления.`)) return
  const res = await fetch(`${API_BASE}/admin/services/${id}`, {
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

// ── Категории ────────────────────────────────────────────────────────────────
const showCatModal = ref(false)
const showCatLimitModal = ref(false)
const editingCat = ref(null)
const catForm = ref({ name: '', sort_order: 0, icon: '', show_on_home: false })
const catMediaUploading = ref(false)
const catMediaPreview = ref(null)
const catError = ref('')

const openAddCatModal = () => {
  editingCat.value = null
  catForm.value = { name: '', sort_order: categories.value.length + 1, icon: '', show_on_home: false }
  catMediaPreview.value = null
  catError.value = ''
  showCatModal.value = true
}

const openEditCatModal = (cat) => {
  editingCat.value = cat
  catForm.value = { name: cat.name, sort_order: cat.sort_order, icon: cat.icon || '', show_on_home: !!cat.show_on_home }
  catMediaPreview.value = cat.home_media_url || null
  catError.value = ''
  showCatModal.value = true
}

const saveCat = async () => {
  catError.value = ''
  if (!catForm.value.name.trim()) {
    catError.value = 'Введите название категории'
    return
  }
  const url = editingCat.value
    ? `${API_BASE}/admin/service-categories/${editingCat.value.category_id}`
    : `${API_BASE}/admin/service-categories`
  const method = editingCat.value ? 'PUT' : 'POST'

  const res = await fetch(url, {
    method,
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify(catForm.value)
  })
  const data = await res.json()
  if (data.success || data.category_id) {
    await fetchCategories()
    showCatModal.value = false
  } else if (data.home_limit_exceeded) {
    showCatLimitModal.value = true
  } else {
    catError.value = data.error || 'Не удалось сохранить'
  }
}

const uploadCatMedia = async (event) => {
  const file = event.target.files[0]
  if (!file || !editingCat.value) return
  catMediaUploading.value = true
  const fd = new FormData()
  fd.append('media', file)
  try {
    const res = await fetch(`${API_BASE}/admin/service-categories/${editingCat.value.category_id}/media`, {
      method: 'POST',
      credentials: 'include',
      body: fd
    })
    const data = await res.json()
    if (data.success) {
      catMediaPreview.value = data.url
      await fetchCategories()
    } else {
      catError.value = data.error || 'Ошибка загрузки'
    }
  } catch {
    catError.value = 'Ошибка соединения'
  } finally {
    catMediaUploading.value = false
    event.target.value = ''
  }
}

const removeCatMedia = async () => {
  if (!editingCat.value) return
  const res = await fetch(`${API_BASE}/admin/service-categories/${editingCat.value.category_id}/media`, {
    method: 'DELETE',
    credentials: 'include'
  })
  const data = await res.json()
  if (data.success) {
    catMediaPreview.value = null
    await fetchCategories()
  }
}

const deleteCat = async (cat) => {
  const count = services.value.filter(s => s.category_id === cat.category_id).length
  const message = count > 0
    ? `В категории ${count} усл. — они тоже будут удалены. Это действие нельзя отменить.`
    : `«${cat.name}» будет удалена без возможности восстановления.`
  if (!await askConfirm(`Удалить категорию «${cat.name}»?`, message)) return
  const res = await fetch(`${API_BASE}/admin/service-categories/${cat.category_id}`, {
    method: 'DELETE',
    credentials: 'include'
  })
  const data = await res.json()
  if (data.success) {
    await fetchCategories()
    await fetchServices()
  } else {
    alert('Ошибка удаления: ' + (data.error || ''))
  }
}

onMounted(() => {
  fetchCategories()
  fetchServices()
})
</script>

<template>
  <div>
    <!-- Заголовок страницы -->
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold">Управление услугами</h2>
      <button @click="openAddModal"
              class="px-4 py-2 bg-[#fc9303] rounded-lg text-black font-semibold hover:scale-105 transition">
        + Добавить услугу
      </button>
    </div>

    <!-- Блок управления категориями -->
    <div class="bg-gray-900 border border-gray-800 rounded-2xl mb-8">
      <div class="flex justify-between items-center px-6 py-4 border-b border-gray-800">
        <h3 class="text-lg font-bold">Категории услуг</h3>
        <button @click="openAddCatModal"
                class="flex items-center gap-2 px-3 py-1.5 border border-[#fc9303] text-[#fc9303] rounded-lg text-sm font-semibold hover:bg-[#fc9303] hover:text-black transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Добавить категорию
        </button>
      </div>

      <div v-if="categories.length === 0" class="px-6 py-8 text-center text-gray-500">
        Категорий пока нет. Создайте первую.
      </div>

      <div v-else class="divide-y divide-gray-800">
        <div v-for="cat in categories" :key="cat.category_id"
             class="flex items-center justify-between px-6 py-3 hover:bg-gray-800/50 transition">
          <div class="flex items-center gap-3 flex-wrap">
            <span class="w-6 h-6 flex items-center justify-center text-xs text-gray-500 bg-gray-800 rounded-full flex-shrink-0">
              {{ cat.sort_order }}
            </span>
            <span class="text-lg flex-shrink-0">{{ cat.icon || '📁' }}</span>
            <span class="font-medium">{{ cat.name }}</span>
            <span class="text-xs text-gray-500">
              {{ services.filter(s => s.category_id === cat.category_id).length }} усл.
            </span>
            <span v-if="cat.show_on_home"
                  class="text-xs px-2 py-0.5 rounded-full bg-[#fc9303]/20 text-[#fc9303] border border-[#fc9303]/30">
              На главной
            </span>
          </div>
          <div class="flex gap-2">
            <button @click="openEditCatModal(cat)"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition"
                    title="Редактировать">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
              </svg>
            </button>
            <button @click="deleteCat(cat)"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-gray-700 transition"
                    title="Удалить">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Список услуг по категориям -->
    <div v-if="loading" class="text-center py-12">
      <div class="w-10 h-10 border-4 border-[#fc9303] border-t-transparent rounded-full animate-spin mx-auto"></div>
    </div>

    <div v-else class="space-y-6">
      <div v-for="cat in categories" :key="cat.category_id">
        <h3 class="text-lg font-bold mb-2">{{ cat.name }}</h3>
        <div class="bg-gray-800 rounded-lg overflow-hidden mb-4">
          <div v-if="services.filter(s => s.category_id === cat.category_id).length === 0"
               class="p-4 text-sm text-gray-500 italic">
            Нет услуг в этой категории
          </div>
          <div v-for="service in services.filter(s => s.category_id === cat.category_id)"
               :key="service.service_id"
               class="p-4 border-b border-gray-700 flex justify-between items-center hover:bg-gray-700">
            <div>
              <div class="font-semibold">{{ service.name }}</div>
              <div class="text-sm text-gray-400">{{ service.description || '—' }}</div>
              <div class="text-sm text-[#fc9303]">
                {{ service.base_price }} ₽ |
                {{ Math.floor(service.duration_minutes / 60) }} ч {{ service.duration_minutes % 60 }} мин
              </div>
              <div class="text-xs text-gray-500">Статус: {{ service.is_active ? 'Активна' : 'Неактивна' }}</div>
            </div>
            <div class="flex gap-2">
              <button @click="openEditModal(service)" class="text-gray-400 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
              </button>
              <button @click="deleteService(service.service_id, service.name)" class="text-gray-400 hover:text-red-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Модал: добавить/редактировать категорию -->
    <Transition name="modal">
      <div v-if="showCatModal"
           class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4"
           @click.self="showCatModal = false">
        <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-md">
          <div class="px-6 py-4 border-b border-gray-800 flex justify-between items-center">
            <h3 class="text-xl font-bold">
              {{ editingCat ? 'Редактировать категорию' : 'Новая категория' }}
            </h3>
            <button @click="showCatModal = false" class="text-gray-400 hover:text-white">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="p-6 space-y-4">
            <!-- Название -->
            <div>
              <label class="block text-sm text-gray-400 mb-1">Название <span class="text-red-500">*</span></label>
              <input v-model="catForm.name"
                     type="text"
                     placeholder="Например: Полировка"
                     class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white placeholder-gray-600 focus:border-[#fc9303] focus:outline-none transition"
                     @keyup.enter="saveCat">
            </div>

            <!-- Иконка (эмодзи) -->
            <div>
              <label class="block text-sm text-gray-400 mb-1">Иконка категории (эмодзи)</label>
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gray-800 border border-gray-700 flex items-center justify-center text-2xl flex-shrink-0">
                  {{ catForm.icon || '📁' }}
                </div>
                <input v-model="catForm.icon"
                       type="text"
                       placeholder="🔧 Вставьте эмодзи"
                       maxlength="10"
                       class="flex-1 px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white placeholder-gray-600 focus:border-[#fc9303] focus:outline-none transition">
              </div>
              <p class="text-xs text-gray-500 mt-1">Отображается на странице «Услуги»</p>
            </div>

            <!-- Порядок сортировки -->
            <div>
              <label class="block text-sm text-gray-400 mb-1">Порядок сортировки</label>
              <input v-model.number="catForm.sort_order"
                     type="number" min="1"
                     class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:border-[#fc9303] focus:outline-none transition">
            </div>

            <!-- Показывать на главной -->
            <div class="flex items-center justify-between py-2">
              <div>
                <p class="text-sm font-medium">Показывать на главной странице</p>
                <p class="text-xs text-gray-500">Карточка категории появится в секции «Наши услуги» <span class="text-gray-600">(макс. 5)</span></p>
              </div>
              <button @click="catForm.show_on_home = !catForm.show_on_home"
                      class="relative w-12 h-6 rounded-full transition-colors flex-shrink-0"
                      :class="catForm.show_on_home ? 'bg-[#fc9303]' : 'bg-gray-700'">
                <span class="absolute top-1 w-4 h-4 bg-white rounded-full transition-all"
                      :class="catForm.show_on_home ? 'left-7' : 'left-1'"></span>
              </button>
            </div>

            <!-- Медиа для главной (только при редактировании существующей категории с show_on_home) -->
            <div v-if="catForm.show_on_home && editingCat" class="border border-gray-700 rounded-xl p-4 space-y-3">
              <p class="text-sm font-medium text-gray-300">Медиа для карточки на главной</p>
              <p class="text-xs text-gray-500">Фото или видео (JPG, PNG, WEBP, MP4, WEBM). До 100 МБ.</p>

              <!-- Превью -->
              <div v-if="catMediaPreview" class="relative rounded-xl overflow-hidden h-40 bg-gray-900">
                <video v-if="/\.(mp4|webm|ogg)/i.test(catMediaPreview)"
                       :src="catMediaPreview" class="w-full h-full object-cover"
                       autoplay muted loop playsinline></video>
                <img v-else :src="catMediaPreview" class="w-full h-full object-cover" alt="превью">
                <button @click="removeCatMedia"
                        class="absolute top-2 right-2 w-7 h-7 rounded-full bg-black/70 hover:bg-red-600 flex items-center justify-center transition"
                        title="Удалить медиа">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>

              <!-- Загрузка -->
              <label class="flex items-center gap-3 cursor-pointer px-4 py-3 bg-gray-800 border border-dashed border-gray-600 rounded-xl hover:border-[#fc9303] transition">
                <svg class="w-5 h-5 text-[#fc9303] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                <span class="text-sm text-gray-400">
                  <template v-if="catMediaUploading">Загрузка…</template>
                  <template v-else>{{ catMediaPreview ? 'Заменить медиа' : 'Загрузить медиа' }}</template>
                </span>
                <input type="file" class="hidden"
                       accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm"
                       :disabled="catMediaUploading"
                       @change="uploadCatMedia">
              </label>
            </div>

            <!-- Подсказка: медиа доступно только для существующих категорий -->
            <div v-if="catForm.show_on_home && !editingCat" class="text-xs text-gray-500 bg-gray-800/50 rounded-xl px-4 py-3">
              Сохраните категорию — затем откройте её снова, чтобы загрузить медиа для главной страницы.
            </div>

            <p v-if="catError" class="text-sm text-red-400">{{ catError }}</p>
          </div>
          <div class="px-6 py-4 border-t border-gray-800 flex gap-3">
            <button @click="showCatModal = false"
                    class="flex-1 px-4 py-3 border border-gray-700 rounded-xl text-gray-400 hover:text-white transition">
              Отмена
            </button>
            <button @click="saveCat"
                    class="flex-1 px-4 py-3 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] rounded-xl text-white font-semibold hover:opacity-90 transition">
              Сохранить
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Модал: добавить/редактировать услугу -->
    <Transition name="modal">
      <div v-if="showModal"
           class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4"
           @click.self="showModal = false">
        <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="sticky top-0 bg-gray-900 px-6 py-4 border-b border-gray-800 flex justify-between items-center">
            <h3 class="text-xl font-bold">{{ editingService ? 'Редактировать' : 'Добавить' }} услугу</h3>
            <button @click="showModal = false" class="text-gray-400 hover:text-white">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm text-gray-400 mb-1">Категория</label>
                <select v-model="form.category_id"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
                  <option v-for="cat in categories" :key="cat.category_id" :value="cat.category_id">{{ cat.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Название</label>
                <input v-model="form.name" type="text"
                       class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm text-gray-400 mb-1">Описание</label>
                <textarea v-model="form.description" rows="3"
                          class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white"></textarea>
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Цена (₽)</label>
                <input v-model.number="form.base_price" type="number"
                       class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Длительность (часы)</label>
                <input v-model.number="durationHours" type="number" step="0.5"
                       placeholder="Например: 2.5"
                       class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">URL иконки</label>
                <input v-model="form.icon_url" type="text"
                       class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Порядок сортировки</label>
                <input v-model.number="form.sort_order" type="number"
                       class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
              </div>
              <div class="flex items-center justify-between md:col-span-2">
                <label class="text-sm text-gray-400">Активна</label>
                <button @click="form.is_active = !form.is_active"
                        class="relative w-12 h-6 rounded-full transition-colors"
                        :class="form.is_active ? 'bg-[#fc9303]' : 'bg-gray-700'">
                  <span class="absolute top-1 w-4 h-4 bg-white rounded-full transition-all"
                        :class="form.is_active ? 'left-7' : 'left-1'"></span>
                </button>
              </div>
            </div>
          </div>
          <div class="sticky bottom-0 bg-gray-900 px-6 py-4 border-t border-gray-800 flex gap-3">
            <button @click="showModal = false"
                    class="flex-1 px-4 py-3 border border-gray-700 rounded-xl text-gray-400 hover:text-white transition">
              Отмена
            </button>
            <button @click="saveService"
                    class="flex-1 px-4 py-3 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] rounded-xl text-white font-semibold">
              Сохранить
            </button>
          </div>
        </div>
      </div>
    </Transition>
    <!-- Модалка: превышен лимит категорий на главной -->
    <Transition name="modal">
      <div v-if="showCatLimitModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showCatLimitModal = false">
        <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-sm p-6 text-center">
          <div class="w-14 h-14 bg-[#fc9303]/20 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-[#fc9303]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
          </div>
          <h3 class="text-lg font-bold mb-2">Лимит превышен</h3>
          <p class="text-gray-400 text-sm mb-6">На главной странице можно разместить не более <span class="text-white font-semibold">5 категорий услуг</span>. Снимите отметку «На главной» у одной из существующих категорий и попробуйте снова.</p>
          <button @click="showCatLimitModal = false" class="w-full px-4 py-3 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] rounded-xl text-white font-semibold transition hover:brightness-110">
            Понятно
          </button>
        </div>
      </div>
    </Transition>

    <ConfirmModal
      :show="confirmModal.show"
      :title="confirmModal.title"
      :message="confirmModal.message"
      @confirm="onConfirmOk"
      @cancel="onConfirmCancel"
    />
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
