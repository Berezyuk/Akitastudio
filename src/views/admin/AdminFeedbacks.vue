<template>
  <div class="admin-feedbacks">
    <h2 class="text-2xl font-bold mb-6">Обратная связь</h2>

    <!-- Фильтры -->
    <div class="bg-gray-800/30 rounded-xl p-4 mb-6 flex flex-wrap gap-4">
      <input 
        v-model="search" 
        type="text" 
        placeholder="Поиск по имени, телефону, email, сообщению..." 
        class="flex-1 px-4 py-2 bg-gray-700 rounded-lg text-white"
        @input="debouncedFetch"
      />
      <select v-model="statusFilter" class="px-4 py-2 bg-gray-700 rounded-lg text-white" @change="fetchFeedbacks">
        <option value="all">Все статусы</option>
        <option value="new">Новые</option>
        <option value="read">Прочитанные</option>
        <option value="replied">Ответили</option>
        <option value="archived">В архиве</option>
      </select>
    </div>

    <!-- Таблица заявок -->
    <div v-if="loading" class="text-center py-12">
      <div class="w-10 h-10 border-4 border-[#fc9303] border-t-transparent rounded-full animate-spin mx-auto"></div>
    </div>
    <div v-else-if="feedbacks.length === 0" class="text-center py-12 text-gray-500">
      Нет заявок
    </div>
    <div v-else class="overflow-x-auto">
      <table class="w-full text-left">
        <thead class="bg-gray-800">
          <tr>
            <th class="p-4">Имя</th>
            <th class="p-4">Телефон</th>
            <th class="p-4">Email</th>
            <th class="p-4">Сообщение</th>
            <th class="p-4">Статус</th>
            <th class="p-4">Действия</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="feedback in feedbacks" :key="feedback.feedback_id" class="border-b border-gray-800 hover:bg-gray-800/30">
            <td class="p-4">{{ feedback.name }}</td>
            <td class="p-4">{{ feedback.phone }}</td>
            <td class="p-4">{{ feedback.email || '—' }}</td>
            <td class="p-4 max-w-xs truncate">{{ feedback.message }}</td>
            <td class="p-4">
              <span :class="['px-2 py-1 rounded-full text-xs font-semibold', getStatusClass(feedback.status)]">
                {{ getStatusName(feedback.status) }}
              </span>
            </td>
            <td class="p-4">
              <button @click="openViewModal(feedback)" class="text-blue-400 hover:text-blue-300 mr-2" title="Просмотр">👁️</button>
              <button @click="deleteFeedback(feedback.feedback_id)" class="text-red-400 hover:text-red-300" title="Удалить">🗑️</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Модальное окно просмотра заявки -->
    <div v-if="viewModalVisible" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="viewModalVisible = false">
      <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-2xl max-h-[85vh] overflow-y-auto">
        <div class="sticky top-0 bg-gray-900 px-6 py-4 border-b border-gray-800 flex justify-between items-center">
          <h3 class="text-xl font-bold">Заявка от {{ selectedFeedback?.name }}</h3>
          <button @click="viewModalVisible = false" class="text-gray-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div><strong>Имя:</strong> {{ selectedFeedback?.name }}</div>
            <div><strong>Телефон:</strong> {{ selectedFeedback?.phone }}</div>
            <div><strong>Email:</strong> {{ selectedFeedback?.email || '—' }}</div>
          </div>
          <div>
            <strong>Сообщение:</strong>
            <p class="mt-2 p-3 bg-gray-800 rounded-lg whitespace-pre-wrap">{{ selectedFeedback?.message }}</p>
          </div>
          <div>
            <label class="block text-sm text-gray-400 mb-2">Статус</label>
            <select v-model="editStatus" class="w-full px-4 py-2 bg-gray-800 rounded-lg text-white">
              <option value="new">Новая</option>
              <option value="read">Прочитанная</option>
              <option value="replied">Ответили</option>
              <option value="archived">В архиве</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-400 mb-2">Заметки администратора</label>
            <textarea v-model="editAdminNotes" rows="3" maxlength="255" class="w-full px-4 py-2 bg-gray-800 rounded-lg text-white resize-none" placeholder="Внутренние заметки (до 255 символов)..."></textarea>
            <p class="text-xs text-gray-500 text-right">{{ editAdminNotes.length }}/255</p>
          </div>
        </div>
        <div class="sticky bottom-0 bg-gray-900 px-6 py-4 border-t border-gray-800 flex gap-3">
          <button @click="viewModalVisible = false" class="flex-1 px-4 py-2 border border-gray-700 rounded-lg text-gray-400 hover:text-white">Закрыть</button>
          <button @click="saveChanges" class="flex-1 px-4 py-2 bg-[#fc9303] rounded-lg text-black font-semibold hover:bg-[#ff6b00]">Сохранить</button>
        </div>
      </div>
    </div>

    <ThePagination
      :page="pagination.page"
      :limit="pagination.limit"
      :total="pagination.total"
      @update:page="onPageChange"
      class="mt-4"
    />

    <ConfirmModal
      :show="confirmModal.show"
      :title="confirmModal.title"
      :message="confirmModal.message"
      @confirm="onConfirmOk"
      @cancel="onConfirmCancel"
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
import { ref, onMounted } from 'vue'
import { API_BASE } from '@/config/api.js'
import ThePagination from '@/components/ThePagination.vue'
import ConfirmModal from '@/components/admin/ConfirmModal.vue'
import AlertModal from '@/components/admin/AlertModal.vue'

const feedbacks = ref([])
const loading = ref(false)
const search = ref('')
const statusFilter = ref('all')
const pagination = ref({ page: 1, limit: 30, total: 0 })
let debounceTimer = null

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

const viewModalVisible = ref(false)
const selectedFeedback = ref(null)
const editStatus = ref('')
const editAdminNotes = ref('')

const fetchFeedbacks = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({ page: pagination.value.page, limit: pagination.value.limit })
    if (search.value) params.append('search', search.value)
    if (statusFilter.value !== 'all') params.append('status', statusFilter.value)
    const res  = await fetch(`${API_BASE}/admin/feedbacks?${params}`, { credentials: 'include' })
    const data = await res.json()
    if (data.success) {
      feedbacks.value = data.feedbacks
      pagination.value.total = data.total ?? feedbacks.value.length
    }
  } catch {} finally {
    loading.value = false
  }
}

const onPageChange = (p) => {
  pagination.value.page = p
  fetchFeedbacks()
}

const debouncedFetch = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchFeedbacks(), 300)
}

const openViewModal = (feedback) => {
  selectedFeedback.value = feedback
  editStatus.value = feedback.status
  editAdminNotes.value = feedback.admin_notes || ''
  viewModalVisible.value = true
}

const saveChanges = async () => {
  try {
    const res = await fetch(`${API_BASE}/admin/feedbacks/${selectedFeedback.value.feedback_id}/status`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ status: editStatus.value, admin_notes: editAdminNotes.value })
    })
    const data = await res.json()
    if (data.success) {
      viewModalVisible.value = false
      fetchFeedbacks()
    } else {
      showAlert('Ошибка', data.error || 'Не удалось сохранить')
    }
  } catch {
    showAlert('Ошибка соединения')
  }
}

const deleteFeedback = async (id) => {
  if (!await askConfirm('Удалить заявку?', 'Это действие нельзя отменить.')) return
  try {
    const res = await fetch(`${API_BASE}/admin/feedbacks/${id}`, {
      method: 'DELETE',
      credentials: 'include'
    })
    const data = await res.json()
    if (data.success) {
      fetchFeedbacks()
    } else {
      showAlert('Ошибка', data.error || 'Не удалось удалить')
    }
  } catch {
    showAlert('Ошибка соединения')
  }
}

const getStatusName = (status) => {
  const names = { 
    new: 'Новая', 
    read: 'Прочитана', 
    replied: 'Ответили', 
    archived: 'В архиве' 
  }
  return names[status] || status
}

const getStatusClass = (status) => {
  const classes = {
    new: 'bg-orange-500/20 text-orange-400',
    read: 'bg-blue-500/20 text-blue-400',
    replied: 'bg-green-500/20 text-green-400',
    archived: 'bg-gray-500/20 text-gray-400'
  }
  return classes[status] || 'bg-gray-500/20 text-gray-400'
}

onMounted(() => {
  fetchFeedbacks()
})
</script>

<style scoped>
button { cursor: pointer; }
</style>