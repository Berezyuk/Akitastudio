<script setup>
import { computed } from 'vue'

const props = defineProps({
  page:  { type: Number, required: true },
  limit: { type: Number, required: true },
  total: { type: Number, required: true },
})

const emit = defineEmits(['update:page'])

const totalPages = computed(() => Math.max(1, Math.ceil(props.total / props.limit)))
const from       = computed(() => props.total === 0 ? 0 : (props.page - 1) * props.limit + 1)
const to         = computed(() => Math.min(props.page * props.limit, props.total))

// Показываем до 7 номеров страниц вокруг текущей
const pages = computed(() => {
  const total = totalPages.value
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const cur = props.page
  const set = new Set([1, total, cur - 1, cur, cur + 1].filter(p => p >= 1 && p <= total))
  return [...set].sort((a, b) => a - b)
})
</script>

<template>
  <div v-if="total > 0" class="flex items-center justify-between gap-4 mt-4 text-sm text-gray-400">
    <span>{{ from }}–{{ to }} из {{ total }}</span>

    <div class="flex items-center gap-1">
      <button
        @click="emit('update:page', page - 1)"
        :disabled="page <= 1"
        class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition"
      >←</button>

      <template v-for="(p, i) in pages" :key="p">
        <span v-if="i > 0 && pages[i - 1] !== p - 1" class="px-1 text-gray-600">…</span>
        <button
          @click="emit('update:page', p)"
          class="px-3 py-1.5 rounded-lg transition"
          :class="p === page ? 'bg-[#fc9303] text-black font-semibold' : 'bg-gray-800 hover:bg-gray-700'"
        >{{ p }}</button>
      </template>

      <button
        @click="emit('update:page', page + 1)"
        :disabled="page >= totalPages"
        class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition"
      >→</button>
    </div>
  </div>
</template>
