<template>
  <div class="admin-settings">
    <h2 class="text-2xl font-bold mb-8">Настройки</h2>

    <!-- Смена пароля -->
    <div class="max-w-md mb-10">
      <h3 class="text-xl font-semibold mb-4">Смена пароля</h3>

      <div v-if="successMessage" class="mb-4 p-3 bg-green-500/20 border border-green-500 rounded-lg text-green-400">
        {{ successMessage }}
      </div>
      <div v-if="errorMessage" class="mb-4 p-3 bg-red-500/20 border border-red-500 rounded-lg text-red-400">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="changePassword" class="space-y-4">
        <div>
          <label class="block text-sm text-gray-400 mb-1">Текущий пароль</label>
          <input
            v-model="form.old_password"
            type="password"
            class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-[#fc9303]"
          />
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-1">Новый пароль</label>
          <input
            v-model="form.new_password"
            type="password"
            class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-[#fc9303]"
          />
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-1">Подтверждение нового пароля</label>
          <input
            v-model="form.confirm_password"
            type="password"
            class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-[#fc9303]"
          />
        </div>
        <button
          type="submit"
          :disabled="loading"
          class="px-6 py-2 bg-[#fc9303] rounded-lg text-black font-semibold hover:bg-[#ff6b00] transition disabled:opacity-50"
        >
          {{ loading ? 'Сохранение...' : 'Сменить пароль' }}
        </button>
      </form>
    </div>

    <div class="border-t border-gray-700 mb-10"></div>

    <!-- Политика обработки персональных данных -->
    <div class="max-w-md">
      <h3 class="text-xl font-semibold mb-4">Политика обработки персональных данных</h3>
      <p class="text-sm text-gray-400 mb-4">
        PDF-файл будет доступен по ссылке в форме обратной связи на главной странице.
      </p>

      <!-- Текущий файл -->
      <div class="bg-gray-800 border border-gray-700 rounded-lg p-4 mb-4">
        <p class="text-xs text-gray-500 uppercase tracking-wider mb-3">Текущий файл</p>
        <div v-if="privacyPdfUrl" class="flex items-center gap-3">
          <svg class="w-8 h-8 text-red-400 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM6 20V4h5v7h7v9H6z"/>
            <path d="M9.5 13.5c0-.8-.6-1.5-1.5-1.5H7v4h1v-1h.5c.9 0 1.5-.7 1.5-1.5zm-1.5.5H8v-1h.5c.3 0 .5.2.5.5s-.2.5-.5.5zM12 12h-1.5v4H12c1.1 0 2-.9 2-2v-.8c0-1-.9-1.2-2-1.2zm.5 2.8c0 .6-.4.7-.5.7h-.5v-3h.5c.1 0 .5.1.5.7v1.6zM15 13.5h1v1h-1v1.5h-1V12h2.5v1H15z"/>
          </svg>
          <div class="flex-1 min-w-0">
            <a
              :href="privacyPdfUrl"
              target="_blank"
              rel="noopener"
              class="text-[#fc9303] hover:underline text-sm break-all"
            >Открыть PDF</a>
          </div>
          <button
            @click="deletePdf"
            :disabled="pdfLoading"
            class="flex-shrink-0 text-red-400 hover:text-red-300 text-sm disabled:opacity-50 transition-colors"
          >
            Удалить
          </button>
        </div>
        <p v-else class="text-gray-500 text-sm">Файл не загружен</p>
      </div>

      <div v-if="pdfSuccess" class="mb-4 p-3 bg-green-500/20 border border-green-500 rounded-lg text-green-400 text-sm">
        {{ pdfSuccess }}
      </div>
      <div v-if="pdfError" class="mb-4 p-3 bg-red-500/20 border border-red-500 rounded-lg text-red-400 text-sm">
        {{ pdfError }}
      </div>

      <input
        ref="pdfInput"
        type="file"
        accept=".pdf,application/pdf"
        class="hidden"
        @change="uploadPdf"
      />
      <button
        @click="pdfInput.click()"
        :disabled="pdfLoading"
        class="px-6 py-2 bg-[#fc9303] rounded-lg text-black font-semibold hover:bg-[#ff6b00] transition disabled:opacity-50"
      >
        {{ pdfLoading ? 'Загрузка...' : (privacyPdfUrl ? 'Заменить PDF' : 'Загрузить PDF') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { API_BASE } from '@/config/api.js'

// ── Смена пароля ──────────────────────────────────────────────────────────────
const form = ref({ old_password: '', new_password: '', confirm_password: '' })
const loading = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const changePassword = async () => {
  loading.value = true
  successMessage.value = ''
  errorMessage.value = ''
  try {
    const res = await fetch(`${API_BASE}/admin/change-password`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(form.value)
    })
    const data = await res.json()
    if (data.success) {
      successMessage.value = data.message
      form.value = { old_password: '', new_password: '', confirm_password: '' }
    } else {
      errorMessage.value = data.error
    }
  } catch {
    errorMessage.value = 'Ошибка соединения'
  } finally {
    loading.value = false
  }
}

// ── Политика конфиденциальности (PDF) ────────────────────────────────────────
const privacyPdfUrl = ref('')
const pdfLoading = ref(false)
const pdfSuccess = ref('')
const pdfError = ref('')
const pdfInput = ref(null)

const loadSettings = async () => {
  try {
    const res = await fetch(`${API_BASE}/admin/settings`, { credentials: 'include' })
    const data = await res.json()
    if (data.success && data.settings.privacy_pdf_url) {
      privacyPdfUrl.value = data.settings.privacy_pdf_url
    }
  } catch {}
}

const uploadPdf = async (e) => {
  const file = e.target.files[0]
  if (!file) return
  pdfSuccess.value = ''
  pdfError.value = ''
  pdfLoading.value = true

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
    pdfLoading.value = false
    pdfInput.value.value = ''
  }
}

const deletePdf = async () => {
  pdfSuccess.value = ''
  pdfError.value = ''
  pdfLoading.value = true
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
    pdfLoading.value = false
  }
}

onMounted(loadSettings)
</script>
