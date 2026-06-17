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
        <div v-if="!loading && !fetchError" class="flex flex-wrap justify-center gap-3 mb-6">
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
        <div v-if="!loading && !fetchError" class="text-center mb-8">
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
        <TransitionGroup v-if="!loading && !fetchError"
          name="portfolio-grid"
          tag="div"
          class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 justify-items-center"
        >
          <div
            v-for="item in filteredItems"
            :key="item.id"
            class="portfolio_card"
          >
            <video
              v-if="isVideo(item.video_url)"
              :ref="el => setPortfolioVideoRef(el, item.id)"
              muted
              loop
              playsinline
              preload="none"
              class="portfolio_img"
              v-show="!portfolioVideoErrors[item.id]"
              @error="portfolioVideoErrors[item.id] = true"
            >
              <source :src="item.video_url" type="video/mp4" />
            </video>
            <img
              v-else
              :src="getMediaSrc(item.video_url)"
              :alt="item.category_name"
              loading="lazy"
              class="portfolio_img"
            />
            <h4 class="portfolio_title">{{ item.category_name }}</h4>
            <div class="portfolio_hover-content">
              <ul class="portfolio_list">
                <li v-if="item.service_name">{{ item.service_name }}</li>
              </ul>
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
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
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
const activeFilter = ref('all')

const portfolioVideoRefs = ref({})
const portfolioVideoErrors = ref({})
let portfolioObserver = null

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

const setPortfolioVideoRef = (el, id) => {
  if (el) portfolioVideoRefs.value[id] = el
}

const setupPortfolioObserver = () => {
  if (portfolioObserver) portfolioObserver.disconnect()

  const videoIdMap = new WeakMap()
  filteredItems.value.forEach((item) => {
    const video = portfolioVideoRefs.value[item.id]
    if (video) videoIdMap.set(video, item.id)
  })

  const tryPlay = (video, id, attemptsLeft) => {
    video.play().catch(() => {
      if (attemptsLeft > 0) {
        setTimeout(() => tryPlay(video, id, attemptsLeft - 1), 400)
      } else if (id != null) {
        portfolioVideoErrors.value = { ...portfolioVideoErrors.value, [id]: true }
        portfolioObserver?.unobserve(video)
      }
    })
  }

  portfolioObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        const video = entry.target
        const id = videoIdMap.get(video)
        if (entry.isIntersecting) {
          tryPlay(video, id, 2)
        } else {
          video.pause()
        }
      })
    },
    { threshold: 0.3 }
  )

  filteredItems.value.forEach((item) => {
    const video = portfolioVideoRefs.value[item.id]
    if (video) portfolioObserver.observe(video)
  })
}

watch(filteredItems, async () => {
  await nextTick()
  setupPortfolioObserver()
})

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

onMounted(() => {
  fetchPortfolio()
})

onUnmounted(() => {
  portfolioObserver?.disconnect()
})
</script>

<style scoped>
.portfolio_card {
  width: 100%;
  max-width: 320px;
  aspect-ratio: 2 / 3;
  margin: 0 auto;
  text-align: center;
  background-color: #4d4d4d;
  overflow: hidden;
  border-radius: 10px;
  position: relative;
  cursor: pointer;
  transition: all 0.4s ease;
}

.portfolio_card:hover {
  border: 1px solid #fc9303;
  transform: translateY(-5px);
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
}

.portfolio_img {
  object-fit: cover;
  width: 100%;
  height: 100%;
  display: block;
  transition: all 0.5s ease;
}

.portfolio_card:hover .portfolio_img {
  filter: brightness(0.6);
  transform: scale(1.05);
}

.portfolio_title {
  color: white;
  font-size: 18px;
  padding: 15px 0;
  margin: 0;
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
  transition: all 0.4s ease;
  z-index: 2;
  white-space: normal;
  word-break: break-word;
  hyphens: auto;
}

.portfolio_card:hover .portfolio_title {
  top: 20px;
  bottom: auto;
  background: none;
  text-decoration: underline;
  text-decoration-color: #fc9303;
  text-underline-offset: 5px;
}

.portfolio_hover-content {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  opacity: 0;
  visibility: hidden;
  transition: all 0.4s ease;
  padding: 90px 20px 20px;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  z-index: 1;
}

.portfolio_card:hover .portfolio_hover-content {
  opacity: 1;
  visibility: visible;
}

.portfolio_list {
  list-style: none;
  padding: 0;
  margin: 0;
  color: white;
  text-align: left;
}

.portfolio_list li {
  padding: 8px 0 8px 20px;
  font-size: 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  position: relative;
}

.portfolio_list li::before {
  content: "•";
  color: #fc9303;
  font-size: 20px;
  position: absolute;
  left: 0;
  top: 6px;
}

.portfolio_list li:last-child {
  border-bottom: none;
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

@media (max-width: 768px) {
  .filter-btn {
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
  }
}
</style>