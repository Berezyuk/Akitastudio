<script setup>
defineProps({
  show:    { type: Boolean, required: true },
  title:   { type: String, default: 'Ошибка' },
  message: { type: String, default: '' },
  type:    { type: String, default: 'error' }, // 'error' | 'warning' | 'info'
  okLabel: { type: String, default: 'Закрыть' },
})
const emit = defineEmits(['close'])
</script>

<template>
  <Transition name="modal">
    <div
      v-if="show"
      class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      @click.self="emit('close')"
    >
      <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-sm p-6">
        <div class="flex justify-center mb-4">
          <div
            class="w-12 h-12 rounded-full flex items-center justify-center"
            :class="{
              'bg-red-500/10':    type === 'error',
              'bg-yellow-500/10': type === 'warning',
              'bg-blue-500/10':   type === 'info',
            }"
          >
            <!-- Ошибка -->
            <svg v-if="type === 'error'" class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <!-- Предупреждение -->
            <svg v-else-if="type === 'warning'" class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <!-- Информация -->
            <svg v-else class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
        </div>

        <h3 class="text-lg font-bold text-center mb-2">{{ title }}</h3>
        <p v-if="message" class="text-sm text-gray-400 text-center mb-6">{{ message }}</p>
        <div v-else class="mb-6"></div>

        <button
          @click="emit('close')"
          class="w-full px-4 py-2.5 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-white font-semibold rounded-xl hover:brightness-110 transition"
        >
          {{ okLabel }}
        </button>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(0.95); }
</style>
