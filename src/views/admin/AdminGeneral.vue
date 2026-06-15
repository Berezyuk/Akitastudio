<script setup>
import { ref, onMounted } from 'vue'
import { API_BASE } from '@/config/api.js'

const currentVideoUrl = ref('')
const uploading = ref(false)
const uploadError = ref('')
const uploadSuccess = ref(false)
const fileInput = ref(null)

const fetchSettings = async () => {
  try {
    const res = await fetch(`${API_BASE}/admin/settings`, { credentials: 'include' })
    const data = await res.json()
    if (data.success) currentVideoUrl.value = data.settings.about_video_url || ''
  } catch (e) {
    console.error(e)
  }
}

const onFileSelect = async (e) => {
  const file = e.target.files?.[0]
  if (!file) return

  uploadError.value = ''
  uploadSuccess.value = false
  uploading.value = true

  const formData = new FormData()
  formData.append('video', file)

  try {
    const res = await fetch(`${API_BASE}/admin/settings/about-video/upload`, {
      method: 'POST',
      credentials: 'include',
      body: formData
    })
    const data = await res.json()
    if (data.success) {
      currentVideoUrl.value = data.url
      uploadSuccess.value = true
    } else {
      uploadError.value = data.error || 'Ошибка загрузки'
    }
  } catch {
    uploadError.value = 'Ошибка соединения'
  } finally {
    uploading.value = false
    e.target.value = ''
  }
}

onMounted(fetchSettings)
</script>

<template>
  <div>
    <h2 class="text-2xl font-bold mb-8">Общее</h2>

    <!-- Видео «О студии» -->
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 max-w-2xl">
      <h3 class="text-lg font-bold mb-1">Видео в блоке «О студии»</h3>
      <p class="text-sm text-gray-500 mb-5">Отображается на главной странице в секции «Akita Studio — это:»</p>

      <!-- Текущее видео -->
      <div v-if="currentVideoUrl" class="mb-5">
        <p class="text-xs text-gray-500 mb-2">Текущее видео:</p>
        <video
          :key="currentVideoUrl"
          :src="currentVideoUrl"
          class="w-full max-h-56 rounded-xl object-cover bg-black"
          controls
          muted
        ></video>
      </div>
      <div v-else class="mb-5 flex items-center justify-center h-32 bg-gray-800 rounded-xl text-gray-600 text-sm">
        Видео не загружено — используется файл по умолчанию
      </div>

      <!-- Загрузка -->
      <input ref="fileInput" type="file" class="hidden" accept="video/mp4,video/webm,video/ogg" @change="onFileSelect" />

      <button
        @click="fileInput.click()"
        :disabled="uploading"
        class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-white font-semibold rounded-xl hover:brightness-110 transition disabled:opacity-50"
      >
        <svg v-if="!uploading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
        <div v-else class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
        {{ uploading ? 'Загрузка...' : 'Загрузить новое видео' }}
      </button>
      <p class="text-xs text-gray-600 mt-2">MP4, WEBM, OGG · до 200 МБ</p>

      <p v-if="uploadError" class="mt-3 text-sm text-red-400">{{ uploadError }}</p>
      <p v-if="uploadSuccess" class="mt-3 text-sm text-green-400">Видео успешно обновлено</p>
    </div>
  </div>
</template>
