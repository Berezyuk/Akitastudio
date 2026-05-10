<script setup>
import { ref, onMounted, watch } from 'vue'

const portfolio = ref([])
const categories = ref([])
const services = ref([])
const loading = ref(false)
const showModal = ref(false)
const editingItem = ref(null)

const form = ref({
    video_url: '',
    title: '',
    description: '',
    category_id: null,
    service_id: null,
    sort_order: 0
})

const fetchPortfolio = async () => {
    loading.value = true
    try {
        const res = await fetch('http://localhost:8000/api/admin/portfolio', { credentials: 'include' })
        const data = await res.json()
        if (data.success) portfolio.value = data.portfolio
    } catch (err) { console.error(err) }
    finally { loading.value = false }
}

const fetchCategories = async () => {
    try {
        const res = await fetch('http://localhost:8000/api/admin/service-categories', { credentials: 'include' })
        const data = await res.json()
        if (data.success) categories.value = data.categories
    } catch (err) { console.error(err) }
}

const fetchServicesByCategory = async (categoryId) => {
    if (!categoryId) {
        services.value = []
        return
    }
    try {
        const res = await fetch(`http://localhost:8000/api/admin/services-by-category/${categoryId}`, { credentials: 'include' })
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
    form.value = {
        video_url: '',
        title: '',
        description: '',
        category_id: categories.value[0]?.category_id || null,
        service_id: null,
        sort_order: 0
    }
    if (form.value.category_id) fetchServicesByCategory(form.value.category_id)
    showModal.value = true
}

const openEditModal = (item) => {
    editingItem.value = item
    form.value = { ...item }
    if (form.value.category_id) fetchServicesByCategory(form.value.category_id)
    showModal.value = true
}

const saveItem = async () => {
    const url = editingItem.value
        ? `http://localhost:8000/api/admin/portfolio/${editingItem.value.id}`
        : 'http://localhost:8000/api/admin/portfolio'
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
    } else {
        alert('Ошибка: ' + (data.error || 'Не удалось сохранить'))
    }
}

const deleteItem = async (id, title) => {
    if (confirm(`Удалить видео "${title}"?`)) {
        const res = await fetch(`http://localhost:8000/api/admin/portfolio/${id}`, { method: 'DELETE', credentials: 'include' })
        const data = await res.json()
        if (data.success) await fetchPortfolio()
        else alert('Ошибка удаления')
    }
}

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
                + Добавить видео
            </button>
        </div>

        <div v-if="loading" class="text-center py-12">
            <div class="w-10 h-10 border-4 border-[#fc9303] border-t-transparent rounded-full animate-spin mx-auto"></div>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <div v-for="item in portfolio" :key="item.id" class="relative group bg-gray-800 rounded-lg overflow-hidden">
                <video :src="item.video_url" class="w-full h-40 object-cover"></video>
                <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <button @click="openEditModal(item)" class="bg-blue-600 text-white px-3 py-1 rounded-lg text-sm mr-2">Редактировать</button>
                    <button @click="deleteItem(item.id, item.title)" class="bg-red-600 text-white px-3 py-1 rounded-lg text-sm">Удалить</button>
                </div>
                <div class="p-2">
                    <p class="text-sm font-semibold truncate">{{ item.title || 'Без названия' }}</p>
                    <p class="text-xs text-gray-400">{{ item.category_name }}</p>
                    <p v-if="item.service_name" class="text-xs text-gray-500">{{ item.service_name }}</p>
                </div>
            </div>
        </div>

        <!-- Модальное окно (улучшенное для десктопа) -->
        <Transition name="modal">
            <div v-if="showModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showModal = false">
                <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <div class="sticky top-0 bg-gray-900 px-6 py-4 border-b border-gray-800 flex justify-between items-center">
                        <h3 class="text-xl font-bold">{{ editingItem ? 'Редактировать' : 'Добавить' }} видео</h3>
                        <button @click="showModal = false" class="text-gray-400 hover:text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm text-gray-400 mb-1">URL видео *</label>
                                <input v-model="form.video_url" type="text" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Название</label>
                                <input v-model="form.title" type="text" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Порядок сортировки</label>
                                <input v-model.number="form.sort_order" type="number" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm text-gray-400 mb-1">Описание</label>
                                <textarea v-model="form.description" rows="3" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Категория *</label>
                                <select v-model="form.category_id" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
                                    <option v-for="cat in categories" :key="cat.category_id" :value="cat.category_id">{{ cat.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-1">Услуга (необязательно)</label>
                                <select v-model="form.service_id" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
                                    <option :value="null">— Не привязано к услуге —</option>
                                    <option v-for="srv in services" :key="srv.service_id" :value="srv.service_id">{{ srv.name }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="sticky bottom-0 bg-gray-900 px-6 py-4 border-t border-gray-800 flex gap-3">
                        <button @click="showModal = false" class="flex-1 px-4 py-3 border border-gray-700 rounded-xl text-gray-400 hover:text-white">Отмена</button>
                        <button @click="saveItem" class="flex-1 px-4 py-3 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] rounded-xl text-white font-semibold">Сохранить</button>
                    </div>
                </div>
            </div>
        </Transition>
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