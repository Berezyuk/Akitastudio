<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { API_BASE } from '@/config/api.js'
import ConfirmModal from '@/components/admin/ConfirmModal.vue'

const portfolio = ref([])
const categories = ref([])
const services = ref([])
const loading = ref(false)
const showModal = ref(false)
const showLimitModal = ref(false)
const editingItem = ref(null)

const confirmModal = ref({ show: false, title: '', message: '' })
let confirmResolve = null
const askConfirm = (title, message = '') => new Promise(resolve => {
  confirmModal.value = { show: true, title, message }
  confirmResolve = resolve
})
const onConfirmOk = () => { confirmModal.value.show = false; confirmResolve?.(true) }
const onConfirmCancel = () => { confirmModal.value.show = false; confirmResolve?.(false) }

const isFormValid = computed(() =>
  !!form.value.video_url?.trim() && !!form.value.category_id
)

// Загрузка медиа
const mediaFile = ref(null)
const mediaFileInput = ref(null)
const uploading = ref(false)
const uploadError = ref('')
const saving = ref(false)
const saveError = ref('')

const form = ref({
  video_url: '',
  title: '',
  description: '',
  category_id: null,
  service_id: null,
  sort_order: 0,
  show_on_home: false
})

const fetchPortfolio = async () => {
  loading.value = true
  try {
    const res = await fetch(`${API_BASE}/admin/portfolio`, { credentials: 'include' })
    const data = await res.json()
    if (data.success) portfolio.value = data.portfolio
  } catch (err) { console.error(err) }
  finally { loading.value = false }
}

const fetchCategories = async () => {
  try {
    const res = await fetch(`${API_BASE}/admin/service-categories`, { credentials: 'include' })
    const data = await res.json()
    if (data.success) categories.value = data.categories
  } catch (err) { console.error(err) }
}

const fetchServicesByCategory = async (categoryId) => {
  if (!categoryId) { services.value = []; return }
  try {
    const res = await fetch(`${API_BASE}/admin/services-by-category/${categoryId}`, { credentials: 'include' })
    const data = await res.json()
    if (data.success) services.value = data.services
  } catch (err) { console.error(err) }
}

watch(() => form.value.category_id, (newVal) => {
  form.value.service_id = null
  fetchServicesByCategory(newVal)
})

const openAddModal = () => {
  editingItem.value = null
  mediaFile.value = null
  uploadError.value = ''
  saveError.value = ''
  form.value = {
    video_url: '',
    title: '',
    description: '',
    category_id: categories.value[0]?.category_id || null,
    service_id: null,
    sort_order: 0,
    show_on_home: false
  }
  if (form.value.category_id) fetchServicesByCategory(form.value.category_id)
  showModal.value = true
}

const openEditModal = (item) => {
  editingItem.value = item
  mediaFile.value = null
  uploadError.value = ''
  saveError.value = ''
  form.value = { ...item, video_url: item.video_url || '' }
  if (form.value.category_id) fetchServicesByCategory(form.value.category_id)
  showModal.value = true
}

// Обработка выбора файла: сразу загружаем в MinIO, получаем URL
const onMediaFileSelect = async (e) => {
  const file = e.target.files?.[0]
  if (!file) return
  mediaFile.value = file
  uploadError.value = ''

  const formData = new FormData()
  formData.append('media', file)

  uploading.value = true
  try {
    const res = await fetch(`${API_BASE}/admin/portfolio/upload`, {
      method: 'POST',
      credentials: 'include',
      body: formData
    })
    const data = await res.json()
    if (data.success) {
      form.value.video_url = data.url
    } else {
      uploadError.value = data.error || 'Ошибка загрузки'
      mediaFile.value = null
    }
  } catch (err) {
    uploadError.value = 'Ошибка соединения'
    mediaFile.value = null
  } finally {
    uploading.value = false
  }
}

const saveItem = async () => {
  saveError.value = ''
  saving.value = true
  try {
    const url = editingItem.value
      ? `${API_BASE}/admin/portfolio/${editingItem.value.id}`
      : `${API_BASE}/admin/portfolio`
    const method = editingItem.value ? 'PUT' : 'POST'

    const res = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(form.value)
    })
    const data = await res.json()
    if (data.success) {
      await fetchPortfolio()
      showModal.value = false
    } else if (data.home_limit_exceeded) {
      showLimitModal.value = true
    } else {
      saveError.value = data.error || 'Не удалось сохранить'
    }
  } catch {
    saveError.value = 'Ошибка соединения'
  } finally {
    saving.value = false
  }
}

const deleteItem = async (id, title) => {
  if (!await askConfirm('Удалить из портфолио?', `«${title || 'элемент'}» будет удалён без возможности восстановления.`)) return
  const res = await fetch(`${API_BASE}/admin/portfolio/${id}`, {
    method: 'DELETE',
    credentials: 'include'
  })
  const data = await res.json()
  if (data.success) await fetchPortfolio()
  else alert('Ошибка удаления')
}

// Определяем тип медиа по URL для предпросмотра
const isVideo = (url) => /\.(mp4|webm|ogg)(\?|$)/i.test(url)

onMounted(() => {
  fetchCategories()
  fetchPortfolio()
})
</script>

<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold">Управление портфолио</h2>
      <button @click="openAddModal" class="px-4 py-2 bg-[#fc9303] rounded-lg text-black font-semibold hover:scale-105 transition">
        + Добавить
      </button>
    </div>

    <div v-if="loading" class="text-center py-12">
      <div class="w-10 h-10 border-4 border-[#fc9303] border-t-transparent rounded-full animate-spin mx-auto"></div>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
      <div v-for="item in portfolio" :key="item.id" class="relative group bg-gray-800 rounded-lg overflow-hidden">
        <!-- Превью: видео или изображение -->
        <video v-if="isVideo(item.video_url)" :src="item.video_url" class="w-full h-40 object-cover" muted></video>
        <img v-else :src="item.video_url" class="w-full h-40 object-cover" :alt="item.title" @error="$event.target.style.display='none'" />

        <!-- Бейдж «На главной» -->
        <div v-if="item.show_on_home" class="absolute top-2 left-2 flex items-center gap-1 bg-[#fc9303] text-black text-xs font-bold px-2 py-0.5 rounded-full shadow">
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7A1 1 0 003 11h1v6a1 1 0 001 1h4v-4h2v4h4a1 1 0 001-1v-6h1a1 1 0 00.707-1.707l-7-7z"/>
          </svg>
          На главной
        </div>

        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
          <button @click="openEditModal(item)" class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-black font-semibold px-3 py-1 rounded-lg text-sm hover:brightness-110 transition">Изменить</button>
          <button @click="deleteItem(item.id, item.title)" class="bg-black/70 border border-[#fc9303]/60 text-[#fc9303] font-semibold px-3 py-1 rounded-lg text-sm hover:bg-[#fc9303]/20 transition">Удалить</button>
        </div>

        <div class="p-2">
          <p class="text-sm font-semibold truncate">{{ item.title || 'Без названия' }}</p>
          <p class="text-xs text-gray-400">{{ item.category_name }}</p>
          <p v-if="item.service_name" class="text-xs text-gray-500">{{ item.service_name }}</p>
        </div>
      </div>
    </div>

    <!-- Модальное окно ─────────────────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showModal = false">
        <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-2xl max-h-[90vh] overflow-y-auto">

          <!-- Шапка -->
          <div class="sticky top-0 bg-gray-900 px-6 py-4 border-b border-gray-800 flex justify-between items-center">
            <h3 class="text-xl font-bold">{{ editingItem ? 'Редактировать' : 'Добавить' }} элемент портфолио</h3>
            <button @click="showModal = false" class="text-gray-400 hover:text-white">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="p-6 space-y-5">

            <!-- Загрузка медиафайла ──────────────────────────────────────── -->
            <div>
              <label class="block text-sm text-gray-400 mb-2">Медиафайл *</label>
              <input
                ref="mediaFileInput"
                type="file"
                class="hidden"
                accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/ogg"
                @change="onMediaFileSelect"
              />
              <div
                @click="mediaFileInput.click()"
                class="w-full px-4 py-8 bg-gray-800 border-2 border-dashed border-gray-600 rounded-xl text-center cursor-pointer hover:border-[#fc9303] transition"
                :class="{ 'border-[#fc9303]': mediaFile }"
              >
                <div v-if="uploading" class="flex flex-col items-center gap-2">
                  <div class="w-8 h-8 border-4 border-[#fc9303] border-t-transparent rounded-full animate-spin"></div>
                  <p class="text-sm text-gray-400">Загружаю в MinIO...</p>
                </div>
                <div v-else-if="mediaFile && form.video_url" class="text-sm">
                  <p class="text-green-400 font-medium">✓ Файл загружен</p>
                  <p class="text-gray-500 mt-1 break-all text-xs">{{ mediaFile.name }}</p>
                </div>
                <div v-else-if="form.video_url" class="text-sm">
                  <p class="text-blue-400 font-medium">Текущий файл сохранён</p>
                  <p class="text-gray-500 mt-1 text-xs">Нажмите, чтобы заменить</p>
                </div>
                <div v-else class="text-gray-400">
                  <p class="text-lg mb-1">📁</p>
                  <p class="text-sm">Нажмите для выбора файла</p>
                  <p class="text-xs mt-1">JPG, PNG, WEBP, GIF, MP4, WEBM · до 100 МБ</p>
                </div>
              </div>
              <p v-if="uploadError" class="text-red-400 text-sm mt-2">{{ uploadError }}</p>

              <!-- Превью текущего файла -->
              <div v-if="form.video_url && !uploading" class="mt-3">
                <p class="text-xs text-gray-500 mb-1">Предпросмотр:</p>
                <video v-if="isVideo(form.video_url)" :src="form.video_url" class="w-full max-h-40 rounded-lg object-contain bg-black" controls muted></video>
                <img v-else :src="form.video_url" class="w-full max-h-40 rounded-lg object-contain bg-gray-800" alt="preview" @error="$event.target.style.display='none'" />
              </div>
            </div>

            <!-- Остальные поля ──────────────────────────────────────────── -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm text-gray-400 mb-1">Название</label>
                <input v-model="form.title" type="text" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:border-[#fc9303] focus:outline-none" />
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Порядок сортировки</label>
                <input v-model.number="form.sort_order" type="number" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:border-[#fc9303] focus:outline-none" />
              </div>
              <div class="md:col-span-2 flex items-center gap-3 px-1">
                <input id="show_on_home" v-model="form.show_on_home" type="checkbox" class="w-5 h-5 accent-[#fc9303] rounded flex-shrink-0 cursor-pointer" />
                <label for="show_on_home" class="text-sm text-gray-300 cursor-pointer select-none">
                  Показывать на главной странице <span class="text-gray-500">(макс. 5)</span>
                </label>
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm text-gray-400 mb-1">Описание</label>
                <textarea v-model="form.description" rows="3" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white resize-none focus:border-[#fc9303] focus:outline-none"></textarea>
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Категория *</label>
                <select v-model="form.category_id" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:border-[#fc9303] focus:outline-none">
                  <option v-for="cat in categories" :key="cat.category_id" :value="cat.category_id">{{ cat.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Услуга (необязательно)</label>
                <select v-model="form.service_id" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:border-[#fc9303] focus:outline-none">
                  <option :value="null">— Без привязки —</option>
                  <option v-for="srv in services" :key="srv.service_id" :value="srv.service_id">{{ srv.name }}</option>
                </select>
              </div>
            </div>

          </div>

          <!-- Футер -->
          <div class="sticky bottom-0 bg-gray-900 px-6 py-4 border-t border-gray-800">
            <p v-if="saveError" class="text-red-400 text-sm mb-3 text-center">{{ saveError }}</p>
            <div class="flex gap-3">
              <button @click="showModal = false" class="flex-1 px-4 py-3 border border-gray-700 rounded-xl text-gray-400 hover:text-white transition">Отмена</button>
              <button
                @click="saveItem"
                :disabled="uploading || saving || !isFormValid"
                class="flex-1 px-4 py-3 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] rounded-xl text-white font-semibold disabled:opacity-50 transition flex items-center justify-center gap-2"
              >
                <div v-if="saving" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                {{ saving ? 'Сохранение...' : uploading ? 'Загрузка...' : 'Сохранить' }}
              </button>
            </div>
          </div>

        </div>
      </div>
    </Transition>

    <!-- Модалка: превышен лимит на главной ────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showLimitModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showLimitModal = false">
        <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-sm p-6 text-center">
          <div class="w-14 h-14 bg-[#fc9303]/20 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-[#fc9303]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
          </div>
          <h3 class="text-lg font-bold mb-2">Лимит превышен</h3>
          <p class="text-gray-400 text-sm mb-6">На главной странице можно разместить не более <span class="text-white font-semibold">5 работ портфолио</span>. Снимите отметку «На главной» у одной из существующих работ и попробуйте снова.</p>
          <button @click="showLimitModal = false" class="w-full px-4 py-3 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] rounded-xl text-white font-semibold transition hover:brightness-110">
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
