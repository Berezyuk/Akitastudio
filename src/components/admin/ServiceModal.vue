<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false
    },
    service: {
        type: Object,
        default: null
    },
    categories: {
        type: Array,
        required: true
    }
})

const emit = defineEmits(['update:modelValue', 'save'])

const isOpen = ref(props.modelValue)
const formData = ref({
    name: '',
    category: '',
    description: '',
    price_from: '',
    price_to: '',
    duration_hours: '',
    is_active: true,
    sort_order: 0
})

const isEdit = ref(false)

watch(() => props.modelValue, (val) => {
    isOpen.value = val
    if (val && props.service) {
        isEdit.value = true
        formData.value = { ...props.service }
    } else if (val && !props.service) {
        isEdit.value = false
        formData.value = {
            name: '',
            category: props.categories[0] || '',
            description: '',
            price_from: '',
            price_to: '',
            duration_hours: '',
            is_active: true,
            sort_order: 0
        }
    }
})

watch(isOpen, (val) => {
    if (!val) {
        emit('update:modelValue', false)
    }
})

const closeModal = () => {
    isOpen.value = false
}

const handleSubmit = () => {
    emit('save', { ...formData.value })
    closeModal()
}

const formatPriceInput = (value) => {
    return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
}
</script>

<template>
    <Transition name="modal">
        <div v-if="isOpen" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="closeModal">
            <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-gray-900 px-6 py-4 border-b border-gray-800 flex justify-between items-center">
                    <h3 class="text-xl font-bold text-white">
                        {{ isEdit ? 'Редактировать услугу' : 'Добавить услугу' }}
                    </h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-white transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="p-6 space-y-5">
                    <!-- Название -->
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Название услуги *</label>
                        <input 
                            v-model="formData.name"
                            type="text" 
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] transition-colors"
                            placeholder="Например: Химчистка салона"
                        >
                    </div>
                    
                    <!-- Категория -->
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Категория *</label>
                        <select 
                            v-model="formData.category"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] transition-colors"
                        >
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>
                    
                    <!-- Описание -->
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Описание</label>
                        <textarea 
                            v-model="formData.description"
                            rows="3"
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] transition-colors resize-none"
                            placeholder="Краткое описание услуги"
                        ></textarea>
                    </div>
                    
                    <!-- Цена -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Цена от (₽)</label>
                            <input 
                                v-model="formData.price_from"
                                type="number" 
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] transition-colors"
                                placeholder="Например: 5000"
                            >
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Цена до (₽)</label>
                            <input 
                                v-model="formData.price_to"
                                type="number" 
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] transition-colors"
                                placeholder="Например: 8000"
                            >
                        </div>
                    </div>
                    
                    <!-- Длительность -->
                    <div>
                        <label class="block text-sm text-gray-400 mb-2">Длительность (часов)</label>
                        <input 
                            v-model="formData.duration_hours"
                            type="number" 
                            class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] transition-colors"
                            placeholder="Например: 4"
                        >
                    </div>
                    
                    <!-- Активность -->
                    <div class="flex items-center justify-between">
                        <label class="text-sm text-gray-400">Активна на сайте</label>
                        <button 
                            @click="formData.is_active = !formData.is_active"
                            class="relative w-12 h-6 rounded-full transition-colors duration-300"
                            :class="formData.is_active ? 'bg-[#fc9303]' : 'bg-gray-700'"
                        >
                            <span 
                                class="absolute top-1 w-4 h-4 bg-white rounded-full transition-all duration-300"
                                :class="formData.is_active ? 'left-7' : 'left-1'"
                            ></span>
                        </button>
                    </div>
                </div>
                
                <div class="px-6 py-4 border-t border-gray-800 flex gap-3">
                    <button 
                        @click="closeModal"
                        class="flex-1 px-4 py-3 border border-gray-700 rounded-xl text-gray-400 hover:text-white transition-colors"
                    >
                        Отмена
                    </button>
                    <button 
                        @click="handleSubmit"
                        class="flex-1 px-4 py-3 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] rounded-xl text-white font-semibold hover:scale-[1.02] transition-transform"
                        :disabled="!formData.name || !formData.category"
                    >
                        {{ isEdit ? 'Сохранить' : 'Добавить' }}
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
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