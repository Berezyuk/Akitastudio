<template>
  <div class="portfolio-page">
    <!-- Hero секция -->
    <section class="relative pt-24 pb-16 overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-b from-black via-black to-[#4d4d4d]/20"></div>
      <div class="absolute inset-0 opacity-30">
        <div class="absolute top-20 left-10 w-64 h-64 bg-[#fc9303] rounded-full filter blur-[100px]"></div>
        <div class="absolute bottom-20 right-10 w-96 h-96 bg-[#ff6b00] rounded-full filter blur-[150px]"></div>
      </div>
      <div class="container mx-auto px-4 relative z-10 text-center">
        <h1 class="text-5xl md:text-6xl lg:text-7xl font-bold mb-6">
          <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">Портфолио</span>
        </h1>
        <p class="text-xl text-gray-400 max-w-2xl mx-auto">
          Наши работы — ваше вдохновение. Выберите категорию, чтобы увидеть больше.
        </p>
      </div>
    </section>

    <!-- Сетка портфолио -->
    <section class="py-8 pb-16">
      <div class="container mx-auto px-4">
        <!-- Фильтры -->
        <div class="flex flex-wrap justify-center gap-3 mb-6">
          <!-- Кнопка "Все" -->
          <button
            @click="activeFilter = 'all'"
            class="filter-btn px-6 py-2.5 rounded-full text-sm font-medium transition-all duration-300"
            :class="activeFilter === 'all' ? 'active' : 'inactive'"
          >
            <span class="relative z-10">Все работы</span>
            <span v-if="activeFilter === 'all'" class="absolute inset-0 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] rounded-full -z-0"></span>
          </button>

          <!-- Категории из портфолио -->
          <button
            v-for="cat in uniqueCategories"
            :key="cat.id"
            @click="activeFilter = cat.name"
            class="filter-btn px-6 py-2.5 rounded-full text-sm font-medium transition-all duration-300"
            :class="activeFilter === cat.name ? 'active' : 'inactive'"
          >
            <span class="relative z-10">{{ cat.name }}</span>
            <span v-if="activeFilter === cat.name" class="absolute inset-0 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] rounded-full -z-0"></span>
          </button>
        </div>

        <!-- Счетчик результатов -->
        <div class="text-center mb-8">
          <p class="text-gray-400 text-sm">
            Найдено <span class="text-[#fc9303] font-semibold">{{ filteredItems.length }}</span> работ
          </p>
        </div>
        <div v-if="loading" role="status" aria-label="Загрузка портфолио" class="flex justify-center py-20">
          <div class="w-10 h-10 border-4 border-[#fc9303] border-t-transparent rounded-full animate-spin"></div>
        </div>

        <div v-else-if="fetchError" class="text-center py-20 text-gray-400">
          <p class="text-lg mb-4">Не удалось загрузить портфолио</p>
          <button @click="fetchPortfolio" class="px-6 py-2 rounded-lg border border-[#fc9303] text-[#fc9303] hover:bg-[#fc9303] hover:text-black transition cursor-pointer">
            Попробовать снова
          </button>
        </div>

        <!-- Анимированная сетка -->
        <TransitionGroup v-if="!fetchError"
          name="portfolio-grid" 
          tag="div" 
          class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6"
        >
          <div
            v-for="(item, index) in filteredItems"
            :key="item.id"
            @mouseenter="handleMouseEnter(item, getOriginalIndex(item.id))"
            @mouseleave="handleMouseLeave(getOriginalIndex(item.id))"
            class="group relative aspect-[3/4] rounded-2xl overflow-hidden cursor-default portfolio-item"
          >
            <video
              v-if="isVideo(item.video_url)"
              :ref="(el) => setVideoRef(el, getOriginalIndex(item.id))"
              :src="item.video_url"
              class="absolute inset-0 w-full h-full object-cover transition-all duration-700"
              :class="{
                'scale-110 brightness-110': hoveredItem === item.id,
                'scale-100': hoveredItem !== item.id
              }"
              loop
              muted
              playsinline
              preload="none"
            ></video>
            <img
              v-else
              :src="getMediaSrc(item.video_url)"
              :alt="item.category_name"
              loading="lazy"
              class="absolute inset-0 w-full h-full object-cover transition-all duration-700"
              :class="{
                'scale-110 brightness-110': hoveredItem === item.id,
                'scale-100': hoveredItem !== item.id
              }"
            />

            <div 
              class="absolute inset-0 transition-opacity duration-500"
              :class="[
                hoveredItem === item.id 
                  ? 'bg-gradient-to-t from-black/80 via-black/30 to-transparent opacity-90' 
                  : 'bg-gradient-to-t from-black via-black/50 to-transparent opacity-60'
              ]"
            ></div>

            <div 
              v-if="hoveredItem === item.id"
              class="absolute -inset-2 bg-gradient-to-r from-[#fc9303]/20 to-[#ff6b00]/20 blur-xl rounded-3xl -z-10"
            ></div>

            <div class="absolute inset-0 p-6 flex flex-col justify-end">
              <span
                v-if="item.service_name"
                class="text-xs font-medium text-[#fc9303] mb-2 transition-all duration-500"
                :class="[
                  hoveredItem === item.id
                    ? 'opacity-100 translate-y-0'
                    : 'opacity-0 translate-y-4'
                ]"
              >
                {{ item.service_name }}
              </span>

              <h3
                class="text-2xl font-bold text-white mb-2 transition-all duration-500"
                :class="[
                  hoveredItem === item.id
                    ? 'translate-y-0'
                    : 'translate-y-4'
                ]"
              >
                {{ item.category_name }}
              </h3>

              <!-- Кнопка звука -->
              <button
                v-if="hoveredItem === item.id"
                @click.stop="toggleSound(item, getOriginalIndex(item.id), $event)"
                :aria-label="soundEnabled[item.id] ? 'Выключить звук' : 'Включить звук'"
              class="absolute top-4 left-4 flex items-center gap-2 bg-black/70 backdrop-blur-sm px-3 py-1.5 rounded-full border border-white/10 hover:border-[#fc9303] transition-all duration-300 cursor-pointer group/btn"
              >
                <svg 
                  v-if="soundEnabled[item.id]"
                  class="w-4 h-4 text-[#fc9303]" 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>
                </svg>
                <svg 
                  v-else
                  class="w-4 h-4 text-gray-400" 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"></path>
                </svg>
                <span class="text-xs text-white group-hover/btn:text-[#fc9303] transition-colors">
                  {{ soundEnabled[item.id] ? 'Звук вкл' : 'Звук выкл' }}
                </span>
              </button>

              <!-- Индикатор звука -->
              <div 
                v-if="soundEnabled[item.id] && hoveredItem !== item.id"
                class="absolute top-4 left-4 w-6 h-6 bg-[#fc9303] rounded-full flex items-center justify-center"
              >
                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>
                </svg>
              </div>
            </div>

            <!-- Индикатор "живого" видео -->
            <div 
              class="absolute top-4 right-4 w-3 h-3 transition-opacity duration-300"
              :class="{ 'opacity-0': hoveredItem === item.id }"
            >
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#fc9303] opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-[#fc9303]"></span>
            </div>
          </div>
        </TransitionGroup>

        <!-- Пустое состояние -->
        <div v-if="!loading && !fetchError && filteredItems.length === 0" class="text-center py-20">
          <div class="text-6xl mb-4">🎬</div>
          <h3 class="text-xl font-semibold text-white mb-2">Нет работ в этой категории</h3>
          <p class="text-gray-400">Попробуйте выбрать другую категорию</p>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useHead } from '@unhead/vue'
import { API_BASE } from '@/config/api.js'

useHead({
  title: 'Примеры работ — Портфолио Akita Studio',
  link: [{ rel: 'canonical', href: 'https://akita-studio.ru/portfolio' }],
  meta: [
    { name: 'description', content: 'Портфолио Akita Studio. Смотрите фото и видео результатов до и после: полировка, оклейка пленкой, химчистка, керамика. Реальные работы наших мастеров.' },
    { property: 'og:type', content: 'website' },
    { property: 'og:title', content: 'Портфолио Akita Studio — Примеры работ' },
    { property: 'og:description', content: 'Фото и видео результатов: полировка, оклейка плёнкой, химчистка, керамика. Реальные работы мастеров Akita Studio.' },
    { property: 'og:url', content: 'https://akita-studio.ru/portfolio' },
    { property: 'og:image', content: 'https://akita-studio.ru/og-image.webp' },
    { property: 'og:image:width', content: '1080' },
    { property: 'og:image:height', content: '600' },
  ],
})

const portfolioItems = ref([])
const loading = ref(true)
const fetchError = ref(false)
const hoveredItem = ref(null)
const videoRefs = ref([])
const soundEnabled = ref({})
const activeFilter = ref('all')

// Получаем уникальные категории из портфолио
const uniqueCategories = computed(() => {
  const categoryMap = new Map()
  portfolioItems.value.forEach(item => {
    if (item.category_id && item.category_name) {
      categoryMap.set(item.category_id, {
        id: item.category_id,
        name: item.category_name
      })
    }
  })
  return Array.from(categoryMap.values())
})

// Фильтрованные элементы
const filteredItems = computed(() => {
  if (activeFilter.value === 'all') {
    return portfolioItems.value
  }
  return portfolioItems.value.filter(item => item.category_name === activeFilter.value)
})

// Найти оригинальный индекс элемента по ID
const getOriginalIndex = (itemId) => {
  return portfolioItems.value.findIndex(item => item.id === itemId)
}

const fetchPortfolio = async () => {
  loading.value = true
  fetchError.value = false
  try {
    const res = await fetch(`${API_BASE}/portfolio`)
    const data = await res.json()
    if (data.success) portfolioItems.value = data.portfolio
  } catch {
    fetchError.value = true
  } finally {
    loading.value = false
  }
}

const isVideo = (url) => /\.(mp4|webm|ogg)(\?|$)/i.test(url || '')

const getYouTubeId = (url) => {
  if (!url) return null
  const m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s?]+)/)
  return m ? m[1] : null
}

const getMediaSrc = (url) => {
  const ytId = getYouTubeId(url)
  if (ytId) return `https://img.youtube.com/vi/${ytId}/hqdefault.jpg`
  return url
}

const handleMouseEnter = (item, index) => {
  hoveredItem.value = item.id
  if (!isVideo(item.video_url)) return
  const video = videoRefs.value[index]
  if (video) {
    video.volume = soundEnabled.value[item.id] ? 0.3 : 0
    video.play().catch(() => {})
  }
}

const handleMouseLeave = (index) => {
  hoveredItem.value = null
  const video = videoRefs.value[index]
  if (video) {
    video.pause()
    video.currentTime = 0
  }
}

const toggleSound = (item, index, event) => {
  event.stopPropagation()
  const video = videoRefs.value[index]
  if (video) {
    const newState = !soundEnabled.value[item.id]
    soundEnabled.value = { ...soundEnabled.value, [item.id]: newState }
    video.volume = newState ? 0.3 : 0
  }
}

const setVideoRef = (el, index) => {
  if (el && index !== -1) {
    videoRefs.value[index] = el
  }
}

onMounted(() => {
  fetchPortfolio()
})

onUnmounted(() => {
  videoRefs.value.forEach(video => {
    if (video) {
      video.pause()
      video.src = ''
    }
  })
})
</script>

<style scoped>
@keyframes ping {
  75%, 100% {
    transform: scale(2);
    opacity: 0;
  }
}

.animate-ping {
  animation: ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
}

.aspect-\[3\/4\] {
  aspect-ratio: 3/4;
}

/* Стили фильтров */
.filter-btn {
  position: relative;
  isolation: isolate;
  background: rgba(30, 30, 40, 0.6);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #ccc;
  overflow: hidden;
  cursor: pointer;
}

.filter-btn.active {
  color: white;
  border-color: transparent;
}

.filter-btn.inactive {
  color: #999;
}

.filter-btn.inactive:hover {
  color: #fff;
  background: rgba(50, 50, 60, 0.8);
  border-color: rgba(252, 147, 3, 0.5);
}

/* Анимация сетки портфолио */
.portfolio-grid-move,
.portfolio-grid-enter-active,
.portfolio-grid-leave-active {
  transition: all 0.5s ease;
}

.portfolio-grid-enter-from {
  opacity: 0;
  transform: scale(0.8);
}

.portfolio-grid-leave-to {
  opacity: 0;
  transform: scale(0.8);
}

.portfolio-grid-leave-active {
  position: absolute;
}

/* Анимация для карточек при фильтрации */
.portfolio-item {
  transition: all 0.3s ease;
}

@media (max-width: 768px) {
  .grid {
    gap: 1rem;
  }
  .aspect-\[3\/4\] {
    aspect-ratio: 2/3;
  }
  
  .filter-btn {
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
  }
}
</style>