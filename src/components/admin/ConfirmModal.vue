<script setup>
defineProps({
  show:    { type: Boolean, required: true },
  title:   { type: String, default: 'Подтвердите действие' },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: 'Удалить' },
  cancelLabel:  { type: String, default: 'Отмена' },
})
const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <Transition name="modal">
    <div
      v-if="show"
      class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      @click.self="emit('cancel')"
    >
      <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-sm p-6">
        <!-- Иконка -->
        <div class="flex justify-center mb-4">
          <div class="w-12 h-12 rounded-full bg-red-500/10 flex items-center justify-center">
            <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
          </div>
        </div>

        <h3 class="text-lg font-bold text-center mb-2">{{ title }}</h3>
        <p v-if="message" class="text-sm text-gray-400 text-center mb-6">{{ message }}</p>
        <div v-else class="mb-6"></div>

        <div class="flex gap-3">
          <button
            @click="emit('cancel')"
            class="flex-1 px-4 py-2.5 border border-gray-700 rounded-xl text-gray-400 hover:text-white hover:border-gray-500 transition"
          >
            {{ cancelLabel }}
          </button>
          <button
            @click="emit('confirm')"
            class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-500 text-white font-semibold rounded-xl transition"
          >
            {{ confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(0.95); }
</style>
