<script setup>
import { ref, onMounted } from 'vue'
import { API_BASE } from '@/config/api.js'

// ── Видео «О студии» ──────────────────────────────────────────────────────────
const currentVideoUrl = ref('')
const uploading = ref(false)
const uploadError = ref('')
const uploadSuccess = ref(false)
const fileInput = ref(null)

// ── Политика конфиденциальности (PDF) ────────────────────────────────────────
const privacyPdfUrl = ref('')
const pdfUploading = ref(false)
const pdfError = ref('')
const pdfSuccess = ref('')
const pdfInput = ref(null)

const fetchSettings = async () => {
  try {
    const res = await fetch(`${API_BASE}/admin/settings`, { credentials: 'include' })
    const data = await res.json()
    if (data.success) {
      currentVideoUrl.value = data.settings.about_video_url || ''
      privacyPdfUrl.value = data.settings.privacy_pdf_url || ''
    }
  } catch {}
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

const onPdfSelect = async (e) => {
  const file = e.target.files?.[0]
  if (!file) return

  pdfError.value = ''
  pdfSuccess.value = ''
  pdfUploading.value = true

  const formData = new FormData()
  formData.append('pdf', file)

  try {
    const res = await fetch(`${API_BASE}/admin/settings/privacy-pdf/upload`, {
      method: 'POST',
      credentials: 'include',
      body: formData
    })
    const data = await res.json()
    if (data.success) {
      privacyPdfUrl.value = data.url
      pdfSuccess.value = 'PDF успешно загружен'
    } else {
      pdfError.value = data.error || 'Ошибка загрузки'
    }
  } catch {
    pdfError.value = 'Ошибка соединения'
  } finally {
    pdfUploading.value = false
    e.target.value = ''
  }
}

const deletePdf = async () => {
  pdfError.value = ''
  pdfSuccess.value = ''
  pdfUploading.value = true
  try {
    const res = await fetch(`${API_BASE}/admin/settings/privacy-pdf`, {
      method: 'DELETE',
      credentials: 'include'
    })
    const data = await res.json()
    if (data.success) {
      privacyPdfUrl.value = ''
      pdfSuccess.value = 'PDF удалён'
    } else {
      pdfError.value = data.error || 'Ошибка удаления'
    }
  } catch {
    pdfError.value = 'Ошибка соединения'
  } finally {
    pdfUploading.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <div>
    <h2 class="text-2xl font-bold mb-8">Общее</h2>

    <div class="flex flex-col gap-6 max-w-2xl">

      <!-- Видео «О студии» -->
      <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
        <h3 class="text-lg font-bold mb-1">Видео в блоке «О студии»</h3>
        <p class="text-sm text-gray-500 mb-5">Отображается на главной странице в секции «Akita Studio — это:»</p>

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

      <!-- Политика обработки персональных данных -->
      <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
        <h3 class="text-lg font-bold mb-1">Политика обработки персональных данных</h3>
        <p class="text-sm text-gray-500 mb-5">PDF-файл будет доступен по ссылке в форме обратной связи на главной странице.</p>

        <!-- Текущий файл -->
        <div class="mb-5">
          <p class="text-xs text-gray-500 mb-2">Текущий файл:</p>
          <div v-if="privacyPdfUrl" class="flex items-center gap-3 bg-gray-800 rounded-xl px-4 py-3">
            <svg class="w-7 h-7 text-red-400 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM6 20V4h5v7h7v9H6z"/>
              <path d="M9.5 13.5c0-.8-.6-1.5-1.5-1.5H7v4h1v-1h.5c.9 0 1.5-.7 1.5-1.5zm-1.5.5H8v-1h.5c.3 0 .5.2.5.5s-.2.5-.5.5zM12 12h-1.5v4H12c1.1 0 2-.9 2-2v-.8c0-1-.9-1.2-2-1.2zm.5 2.8c0 .6-.4.7-.5.7h-.5v-3h.5c.1 0 .5.1.5.7v1.6zM15 13.5h1v1h-1v1.5h-1V12h2.5v1H15z"/>
            </svg>
            <a
              :href="privacyPdfUrl"
              target="_blank"
              rel="noopener"
              class="flex-1 text-sm text-[#fc9303] hover:underline truncate"
            >Открыть PDF</a>
            <button
              @click="deletePdf"
              :disabled="pdfUploading"
              class="flex-shrink-0 text-xs text-red-400 hover:text-red-300 disabled:opacity-50 transition-colors"
            >Удалить</button>
          </div>
          <div v-else class="flex items-center justify-center h-16 bg-gray-800 rounded-xl text-gray-600 text-sm">
            Файл не загружен
          </div>
        </div>

        <input ref="pdfInput" type="file" class="hidden" accept=".pdf,application/pdf" @change="onPdfSelect" />

        <button
          @click="pdfInput.click()"
          :disabled="pdfUploading"
          class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-white font-semibold rounded-xl hover:brightness-110 transition disabled:opacity-50"
        >
          <svg v-if="!pdfUploading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
          </svg>
          <div v-else class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
          {{ pdfUploading ? 'Загрузка...' : (privacyPdfUrl ? 'Заменить PDF' : 'Загрузить PDF') }}
        </button>
        <p class="text-xs text-gray-600 mt-2">PDF · до 20 МБ</p>

        <p v-if="pdfError" class="mt-3 text-sm text-red-400">{{ pdfError }}</p>
        <p v-if="pdfSuccess" class="mt-3 text-sm text-green-400">{{ pdfSuccess }}</p>
      </div>

    </div>
  </div>
</template>

<style scoped>
button { cursor: pointer; }
</style>
