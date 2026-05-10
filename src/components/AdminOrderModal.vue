<template>
  <div v-if="visible" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="close">
    <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
      <!-- Заголовок -->
      <div class="sticky top-0 bg-gray-900 border-b border-gray-800 p-5 flex justify-between items-center">
        <div>
          <h2 class="text-xl font-bold">{{ order?.first_name }} {{ order?.last_name }}</h2>
          <p class="text-sm text-gray-400">{{ order?.phone_number }}</p>
          <p class="text-sm text-gray-500">{{ order?.brand_name }} {{ order?.model_name }}</p>
        </div>
        <button @click="close" class="w-8 h-8 rounded-full hover:bg-gray-800 transition text-gray-400 hover:text-white">✕</button>
      </div>
      
      <!-- Содержимое с табами -->
      <div class="p-5">
        <div class="flex gap-2 mb-6 border-b border-gray-800">
          <button 
            @click="activeTab = 'services'" 
            class="px-4 py-2 rounded-t-lg transition"
            :class="activeTab === 'services' ? 'bg-gray-800 text-[#fc9303] border-b-2 border-[#fc9303]' : 'text-gray-400'"
          >
            📋 Услуги
          </button>
          <button 
            @click="activeTab = 'photos'" 
            class="px-4 py-2 rounded-t-lg transition"
            :class="activeTab === 'photos' ? 'bg-gray-800 text-[#fc9303] border-b-2 border-[#fc9303]' : 'text-gray-400'"
          >
            📸 Фотоотчёт
          </button>
        </div>

        <!-- Вкладка: Прогресс услуг -->
        <div v-if="activeTab === 'services'" class="space-y-4">
          <div v-for="service in services" :key="service.service_id" class="bg-gray-800/50 rounded-xl p-4">
            <div class="flex justify-between items-center mb-2">
              <span class="font-medium">{{ service.service_name }}</span>
              <span class="text-[#fc9303] text-sm font-semibold">{{ service.progress_percent }}%</span>
            </div>
            <input 
              type="range" 
              min="0" 
              max="100" 
              v-model="service.progress_percent"
              @change="updateProgress(service.service_id, service.progress_percent)"
              class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-[#fc9303]"
            />
            <div class="flex justify-between text-xs text-gray-500 mt-1">
              <span>Ожидание</span>
              <span>В работе</span>
              <span>Готово</span>
            </div>
          </div>
        </div>

        <!-- Вкладка: Фотоотчёт -->
        <div v-if="activeTab === 'photos'" class="space-y-4">
          <!-- Форма загрузки -->
          <div class="bg-gray-800/50 rounded-xl p-4">
            <h3 class="font-semibold mb-3">Загрузить новое фото</h3>
            <div class="flex flex-wrap gap-3 items-end">
              <div class="flex-1 min-w-[200px]">
                <input type="file" ref="fileInput" @change="onFileSelect" accept="image/jpeg,image/png,image/webp" class="hidden" />
                <button @click="$refs.fileInput.click()" class="w-full px-4 py-2 bg-gray-700 rounded-lg hover:bg-gray-600 transition flex items-center justify-center gap-2">
                  📁 Выбрать фото
                </button>
              </div>
              <div class="flex-1 min-w-[200px]">
                <input v-model="newPhotoCaption" type="text" placeholder="Подпись к фото (необязательно)" class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:border-[#fc9303] focus:outline-none" />
              </div>
              <button @click="uploadPhoto" :disabled="uploading" class="px-6 py-2 bg-[#fc9303] text-black rounded-lg font-semibold hover:bg-[#ff6b00] transition disabled:opacity-50">
                {{ uploading ? 'Загрузка...' : 'Загрузить' }}
              </button>
            </div>
          </div>

          <!-- Галерея фото -->
          <div v-if="photos.length === 0 && !loading.photos" class="text-center py-8 text-gray-500">
            📷 Нет загруженных фото. Добавьте первые фотографии процесса.
          </div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div v-for="photo in photos" :key="photo.id" class="bg-gray-800/50 rounded-xl overflow-hidden group relative">
              <img :src="photo.photo_url" class="w-full h-40 object-cover cursor-pointer" @click="openLightbox(photo)" />
              <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-2">
                <p class="text-xs text-gray-300 truncate">{{ photo.caption || 'Без подписи' }}</p>
               
              </div>
              <button 
                @click="deletePhoto(photo.id)" 
                class="absolute top-2 right-2 w-6 h-6 bg-red-500/80 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition hover:bg-red-600"
              >
                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Кнопка закрытия -->
        <div class="flex justify-end pt-4 border-t border-gray-800 mt-4">
          <button @click="close" class="px-6 py-2 bg-[#fc9303] text-black rounded-lg hover:bg-[#ff6b00] transition font-medium">Закрыть</button>
        </div>
      </div>
    </div>

    <!-- Лайтбокс для просмотра фото -->
    <div v-if="lightboxPhoto" class="fixed inset-0 bg-black/95 z-[60] flex items-center justify-center" @click="lightboxPhoto = null">
      <div class="max-w-[90vw] max-h-[90vh]">
        <img :src="lightboxPhoto.photo_url" class="max-w-full max-h-[90vh] object-contain" />
        <p class="text-center text-white mt-4">{{ lightboxPhoto.caption }}</p>
      </div>
      <button @click="lightboxPhoto = null" class="absolute top-4 right-4 w-10 h-10 rounded-full bg-gray-800 hover:bg-gray-700 text-white">✕</button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  visible: Boolean,
  order: Object
})

const emit = defineEmits(['close', 'updated'])

const activeTab = ref('services')
const services = ref([])
const photos = ref([])
const loading = ref({ services: false, photos: false })
const uploading = ref(false)
const fileInput = ref(null)
const newPhotoCaption = ref('')
const lightboxPhoto = ref(null)

// Загрузка услуг
const fetchServices = async () => {
  if (!props.order) return
  loading.value.services = true
  try {
    const res = await fetch(`http://localhost:8000/api/admin/orders/${props.order.order_id}/progress`, {
      credentials: 'include'
    })
    const data = await res.json()
    if (data.success) services.value = data.services
  } catch (err) {
    console.error(err)
  } finally {
    loading.value.services = false
  }
}

// Загрузка фото
const fetchPhotos = async () => {
  if (!props.order) return
  loading.value.photos = true
  try {
    const res = await fetch(`http://localhost:8000/api/admin/orders/${props.order.order_id}/photos`, {
      credentials: 'include'
    })
    const data = await res.json()
    if (data.success) photos.value = data.photos
  } catch (err) {
    console.error(err)
  } finally {
    loading.value.photos = false
  }
}

// Обновление прогресса
const updateProgress = async (serviceId, progress) => {
  try {
    await fetch(`http://localhost:8000/api/admin/orders/${props.order.order_id}/services/${serviceId}/progress`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ progress_percent: progress })
    })
    emit('updated')
  } catch (err) {
    console.error(err)
  }
}

// Загрузка фото
const onFileSelect = (e) => {
  const file = e.target.files[0]
  if (file) {
    uploadFile(file)
  }
}

const uploadFile = async (file) => {
  const formData = new FormData()
  formData.append('photo', file)
  formData.append('caption', newPhotoCaption.value)

  uploading.value = true
  try {
    const res = await fetch(`http://localhost:8000/api/admin/orders/${props.order.order_id}/photos/upload`, {
      method: 'POST',
      credentials: 'include',
      body: formData
    })
    const data = await res.json()
    if (data.success) {
      newPhotoCaption.value = ''
      if (fileInput.value) fileInput.value.value = ''
      await fetchPhotos()
      emit('updated')
    } else {
      alert(data.error || 'Ошибка загрузки')
    }
  } catch (err) {
    console.error(err)
    alert('Ошибка загрузки')
  } finally {
    uploading.value = false
  }
}

// Удаление фото
const deletePhoto = async (photoId) => {
  if (!confirm('Удалить это фото?')) return
  try {
    const res = await fetch(`http://localhost:8000/api/admin/photos/${photoId}`, {
      method: 'DELETE',
      credentials: 'include'
    })
    const data = await res.json()
    if (data.success) {
      await fetchPhotos()
      emit('updated')
    } else {
      alert(data.error || 'Ошибка удаления')
    }
  } catch (err) {
    console.error(err)
    alert('Ошибка удаления')
  }
}

const openLightbox = (photo) => {
  lightboxPhoto.value = photo
}

const close = () => {
  emit('close')
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleDateString('ru-RU') + ' ' + new Date(dateStr).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}

watch(() => props.visible, (newVal) => {
  if (newVal && props.order) {
    fetchServices()
    fetchPhotos()
  }
})
</script>