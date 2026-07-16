<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import Chart from 'chart.js/auto'
import { API_BASE } from '@/config/api'

const stats = ref({ today: { visits: 0, uniques: 0 }, sources: [], devices: [], top_hosts: [] })
const days = ref(7)
const loading = ref(false)
const error = ref('')
const chartCanvas = ref(null)
let chartInstance = null

const SOURCE_NAMES = {
  search: 'Поисковики',
  social: 'Соцсети',
  direct: 'Прямые заходы',
  other: 'Другие сайты',
}
const DEVICE_NAMES = { mobile: 'Телефон', desktop: 'Компьютер' }

const total = (rows) => rows.reduce((sum, r) => sum + Number(r.count), 0)
const percent = (rows, count) => {
  const all = total(rows)
  return all === 0 ? 0 : Math.round((Number(count) / all) * 100)
}

const loadStats = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await fetch(`${API_BASE}/admin/analytics?days=${days.value}`, {
      credentials: 'include',
    })
    const data = await res.json()
    if (!data.success) throw new Error(data.error || 'Не удалось загрузить статистику')
    stats.value = data
    renderChart(data.chart_data)
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

const renderChart = (chartData) => {
  if (!chartCanvas.value) return
  if (chartInstance) chartInstance.destroy()
  chartInstance = new Chart(chartCanvas.value.getContext('2d'), {
    type: 'line',
    data: {
      labels: chartData.labels,
      datasets: [
        {
          label: 'Визиты',
          data: chartData.visits,
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          fill: true,
          tension: 0.3,
        },
        {
          label: 'Уники',
          data: chartData.uniques,
          borderColor: '#10b981',
          backgroundColor: 'rgba(16, 185, 129, 0.1)',
          fill: true,
          tension: 0.3,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  })
}

const setDays = (value) => {
  days.value = value
  loadStats()
}

onMounted(loadStats)
onUnmounted(() => {
  if (chartInstance) chartInstance.destroy()
})
</script>

<template>
  <div>
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
      <div>
        <h2 class="text-2xl font-bold text-white">Аналитика</h2>
        <p class="text-sm text-gray-400 mt-1">Посещаемость сайта. Считаются только браузеры с JS.</p>
      </div>
      <div class="flex gap-2">
        <button
          v-for="value in [7, 30]"
          :key="value"
          @click="setDays(value)"
          class="px-4 py-2 rounded-lg text-sm transition-colors"
          :class="days === value ? 'bg-blue-600 text-white' : 'bg-gray-800 text-gray-400 hover:text-white'"
        >
          {{ value }} дней
        </button>
      </div>
    </div>

    <p v-if="error" class="mb-4 rounded-lg bg-red-900/40 p-4 text-sm text-red-300">{{ error }}</p>
    <p v-if="loading" class="text-sm text-gray-500">Загрузка…</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
      <div class="rounded-xl bg-gray-900 p-5">
        <p class="text-sm text-gray-400">Визитов сегодня</p>
        <p class="text-3xl font-bold text-white mt-1">{{ stats.today.visits }}</p>
      </div>
      <div class="rounded-xl bg-gray-900 p-5">
        <p class="text-sm text-gray-400">Уникальных посетителей сегодня</p>
        <p class="text-3xl font-bold text-white mt-1">{{ stats.today.uniques }}</p>
      </div>
    </div>

    <div class="rounded-xl bg-gray-900 p-5 mb-6">
      <h3 class="text-white font-semibold mb-4">Динамика</h3>
      <div class="h-64"><canvas ref="chartCanvas"></canvas></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="rounded-xl bg-gray-900 p-5">
        <h3 class="text-white font-semibold mb-4">Откуда приходят</h3>
        <p v-if="!stats.sources.length" class="text-sm text-gray-500">Пока нет данных</p>
        <div v-for="row in stats.sources" :key="row.source" class="flex justify-between py-2 text-sm">
          <span class="text-gray-300">{{ SOURCE_NAMES[row.source] || row.source }}</span>
          <span class="text-gray-500">{{ row.count }} · {{ percent(stats.sources, row.count) }}%</span>
        </div>
      </div>
      <div class="rounded-xl bg-gray-900 p-5">
        <h3 class="text-white font-semibold mb-4">Устройства</h3>
        <p v-if="!stats.devices.length" class="text-sm text-gray-500">Пока нет данных</p>
        <div v-for="row in stats.devices" :key="row.device" class="flex justify-between py-2 text-sm">
          <span class="text-gray-300">{{ DEVICE_NAMES[row.device] || row.device }}</span>
          <span class="text-gray-500">{{ row.count }} · {{ percent(stats.devices, row.count) }}%</span>
        </div>
      </div>

      <div class="rounded-xl bg-gray-900 p-5 md:col-span-2">
        <h3 class="text-white font-semibold mb-1">Топ сайтов</h3>
        <p class="text-xs text-gray-500 mb-4">Откуда именно переходили. Прямых заходов тут нет — у них источника нет.</p>
        <p v-if="!stats.top_hosts.length" class="text-sm text-gray-500">Пока нет данных</p>
        <div v-for="row in stats.top_hosts" :key="row.referer_host" class="flex justify-between py-2 text-sm">
          <span class="text-gray-300 truncate">{{ row.referer_host }}</span>
          <span class="text-gray-500 shrink-0 ml-4">{{ row.count }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
