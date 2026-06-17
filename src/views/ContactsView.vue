<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useHead } from '@unhead/vue'

useHead({
  title: 'Контакты тюнинг-студии Akita Studio в Хабаровске | Адрес и телефон',
  link: [{ rel: 'canonical', href: 'https://akita-studio.ru/contacts' }],
  meta: [
    { name: 'description', content: 'Адрес студии Akita Studio: Хабаровск, ул. Кавказская 35/5. Телефон, email и форма обратной связи. Работаем ежедневно с 10:00 до 20:00. Запишитесь на профессиональный детейлинг прямо сейчас!' },
    { property: 'og:title', content: 'Контакты Akita Studio — Хабаровск, ул. Кавказская 35/5' },
    { property: 'og:description', content: 'Адрес: Хабаровск, ул. Кавказская 35/5. Телефон и email студии детейлинга Akita Studio.' },
    { property: 'og:type', content: 'website' },
    { property: 'og:url', content: 'https://akita-studio.ru/contacts' },
    { property: 'og:image', content: 'https://akita-studio.ru/og-image.webp' },
    { property: 'og:image:width', content: '1080' },
    { property: 'og:image:height', content: '600' },
  ],
})

// Актуальные соцсети
const socialLinks = {
  instagram: 'https://instagram.com/auto.akita',
  telegram: 'https://t.me/akita_auto'
}

// Контакты для блока "Связаться с нами"
const contactMethods = [
  {
    icon: '📞',
    title: 'Позвонить',
    value: '+7 909 802-98-68',
    link: 'tel:+79098029868',
    color: 'from-green-500/20 to-transparent',
    external: false
  },
  {
    icon: '💬',
    title: 'Telegram',
    value: '@akita_auto',
    link: 'https://t.me/akita_auto',
    color: 'from-blue-500/20 to-transparent',
    external: true
  },
  {
    icon: '📱',
    title: 'WhatsApp',
    value: '+7 909 802-98-68',
    link: 'https://wa.me/79098029868',
    color: 'from-green-400/20 to-transparent',
    external: true
  },
]

// Режим работы: 10:00–20:00 по хабаровскому времени (UTC+10)
const now = ref(new Date())
let clockTimer = null

const isOpen = computed(() => {
  const hour = parseInt(
    new Intl.DateTimeFormat('ru', { hour: 'numeric', hour12: false, timeZone: 'Asia/Vladivostok' })
      .format(now.value)
  )
  return hour >= 10 && hour < 20
})

const mapTilt = ref({ x: 0, y: 0 })
let rafId = null

const handleMouseMove = (e) => {
  if (rafId) return
  rafId = requestAnimationFrame(() => {
    const x = (e.clientY / window.innerHeight - 0.5) * 0.5
    const y = (e.clientX / window.innerWidth - 0.5) * 0.5
    mapTilt.value = { x, y }
    rafId = null
  })
}

onMounted(() => {
  window.addEventListener('mousemove', handleMouseMove)
  clockTimer = setInterval(() => { now.value = new Date() }, 60000)
})

onUnmounted(() => {
  window.removeEventListener('mousemove', handleMouseMove)
  if (rafId) cancelAnimationFrame(rafId)
  clearInterval(clockTimer)
})
</script>

<template>
  <div class="contacts-page bg-black text-white">
    
    <!-- Герой -->
    <section class="relative h-[55vh] md:h-screen flex items-center justify-center overflow-hidden">
      <video autoplay muted loop playsinline preload="none" aria-hidden="true" class="absolute inset-0 w-full h-full object-cover opacity-40">
        <source src="@/assets/Video/portfolio2.mp4" type="video/mp4">
      </video>
      <div class="absolute inset-0 bg-gradient-to-b from-black via-transparent to-black"></div>
      <div class="absolute top-1/2 left-0 w-full h-px bg-gradient-to-r from-transparent via-[#fc9303] to-transparent animate-pulse-slow"></div>
      <div class="relative z-10 text-center px-4">
        <span class="text-xs md:text-sm tracking-[0.3em] text-gray-500 mb-3 md:mb-4 block">КОНТАКТЫ</span>
        <h1 class="text-3xl md:text-7xl font-bold mb-3 md:mb-6">
          <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">г. Хабаровск</span>
        </h1>
        <p class="text-lg md:text-3xl text-white mb-4 md:mb-8">ул. Кавказская, 35/5</p>
        <p class="text-sm md:text-lg text-gray-400 max-w-2xl mx-auto">Мы ждем вас и ваш автомобиль</p>
      </div>
      <div class="absolute top-1/4 right-1/4 w-64 h-64 bg-[#fc9303] rounded-full filter blur-[100px] opacity-10"></div>
      <div class="absolute bottom-1/4 left-1/4 w-96 h-96 bg-[#ff6b00] rounded-full filter blur-[150px] opacity-10"></div>
    </section>

    <!-- Карта -->
    <section class="pt-16 pb-8 relative overflow-hidden">
      <div class="site-container mx-auto px-4">
        <h2 class="text-4xl md:text-5xl font-bold text-center mb-16">
          <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">Как добраться</span>
        </h2>
        <div class="relative max-w-5xl mx-auto">
          <div class="relative rounded-2xl overflow-hidden border border-gray-800 transform-gpu transition-transform duration-200 ease-out"
               :style="{ transform: `perspective(1000px) rotateX(${mapTilt.x}deg) rotateY(${mapTilt.y}deg)` }">
            <div class="relative w-full h-[400px] md:h-[500px]">
              <iframe src="https://yandex.ru/map-widget/v1/?um=constructor%3A800b56c6d1df7d01e5c583c9b85fd3bf00d3c6c10408a95691382a2a63f77274&amp;source=constructor"
                      class="absolute top-0 left-0 w-full h-full border-0" allowfullscreen loading="lazy"
                      title="Akita Studio на карте"></iframe>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Соцсети -->
    <section class="pt-8 pb-16 bg-gradient-to-b from-black to-[#4d4d4d]/20">
      <div class="site-container mx-auto px-4">
        <h2 class="text-4xl md:text-5xl font-bold text-center mb-16">
          <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">Мы в сети</span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 max-w-3xl mx-auto">
          <!-- Instagram -->
          <a :href="socialLinks.instagram" target="_blank" rel="noopener noreferrer"
             class="group bg-gray-900/50 backdrop-blur-sm rounded-2xl p-8 border border-gray-800 hover:border-[#fc9303] transition-all duration-500 hover:-translate-y-2">
            <div class="flex items-center gap-4 mb-4">
              <div class="w-14 h-14 rounded-xl bg-gradient-to-tr from-purple-500 to-pink-500 flex items-center justify-center">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                </svg>
              </div>
              <div>
                <h3 class="text-2xl font-bold text-white">Instagram</h3>
                <p class="text-[#fc9303]">@auto.akita</p>
              </div>
            </div>
            <p class="text-gray-400">Следите за нашими работами, процессом и акциями в Instagram</p>
          </a>
          <!-- Telegram -->
          <a :href="socialLinks.telegram" target="_blank" rel="noopener noreferrer"
             class="group bg-gray-900/50 backdrop-blur-sm rounded-2xl p-8 border border-gray-800 hover:border-[#fc9303] transition-all duration-500 hover:-translate-y-2">
            <div class="flex items-center gap-4 mb-4">
              <div class="w-14 h-14 rounded-xl bg-[#2AABEE] flex items-center justify-center">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                </svg>
              </div>
              <div>
                <h3 class="text-2xl font-bold text-white">Telegram</h3>
                <p class="text-[#fc9303]">@akita_auto</p>
              </div>
            </div>
            <p class="text-gray-400">Подпишитесь на наш Telegram-канал, чтобы первыми узнавать о новостях</p>
          </a>
        </div>
      </div>
    </section>

    <!-- Режим работы и контакты -->
    <section class="py-12">
      <div class="site-container mx-auto px-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 max-w-5xl mx-auto">
          <!-- Режим работы -->
          <div class="bg-gray-900/30 backdrop-blur-sm rounded-2xl border border-gray-800 p-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-8">
              <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">Режим работы</span>
            </h2>
            <div class="flex items-center justify-center gap-3 mb-6">
              <div class="w-2 h-2 rounded-full animate-pulse" :class="isOpen ? 'bg-green-500' : 'bg-red-500'"></div>
              <span class="text-gray-400">{{ isOpen ? 'Сейчас открыто' : 'Сейчас закрыто' }}</span>
            </div>
            <div class="text-left max-w-xs mx-auto">
              <div class="flex justify-between items-center">
                <span class="text-gray-400">Ежедневно</span>
                <span class="text-white font-bold">10:00 - 20:00</span>
              </div>
            </div>
            <div class="mt-6 pt-6 border-t border-gray-800">
              <a href="tel:+79098029868" class="text-2xl md:text-3xl font-bold text-white hover:text-[#fc9303] transition-colors duration-300">
                +7 909 802-98-68
              </a>
              <p class="text-gray-400 mt-3">Звоните, мы всегда на связи</p>
            </div>
          </div>

          <!-- Связаться с нами -->
          <div class="bg-gray-900/30 backdrop-blur-sm rounded-2xl border border-gray-800 p-8">
            <h2 class="text-3xl md:text-4xl font-bold text-center mb-8">
              <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">Связаться с нами</span>
            </h2>
            <div class="space-y-4">
              <a v-for="method in contactMethods" :key="method.title"
                 :href="method.link"
                 v-bind="method.external ? { target: '_blank', rel: 'noopener noreferrer' } : {}"
                 class="flex items-center gap-5 p-5 rounded-xl border border-gray-800 hover:border-[#fc9303] transition-all duration-300 group hover:-translate-x-1">
                <div aria-hidden="true" class="w-14 h-14 rounded-full bg-gradient-to-br flex items-center justify-center text-2xl group-hover:scale-110 transition-transform" :class="method.color">
                  {{ method.icon }}
                </div>
                <div>
                  <p class="text-gray-400 text-sm">{{ method.title }}</p>
                  <p class="text-white text-xl font-semibold">{{ method.value }}</p>
                </div>
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="relative py-20 flex items-center justify-center overflow-hidden">
      <video autoplay muted loop playsinline preload="none" aria-hidden="true" class="absolute inset-0 w-full h-full object-cover opacity-20">
        <source src="@/assets/Video/portfolio3.mp4" type="video/mp4">
      </video>
      <div class="absolute inset-0 bg-gradient-radial from-transparent via-black to-black"></div>
      <div class="relative z-10 text-center px-4">
        <div class="mb-8">
          <img src="@/assets/Images/Logo.svg" alt="Akita Studio" loading="lazy" class="h-20 mx-auto opacity-50">
        </div>
        <h2 class="text-5xl md:text-7xl font-bold mb-6">
          <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">До встречи!</span>
        </h2>
        <p class="text-xl text-gray-400 max-w-2xl mx-auto">Мы уже готовим ваш кофе ☕️</p>
      </div>
      <div class="absolute inset-0 bg-black/50 pointer-events-none animate-fade-infinite"></div>
    </section>
  </div>
</template>

<style scoped>
@keyframes pulse-slow {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 1; }
}
@keyframes fade-infinite {
  0%, 100% { opacity: 0.3; }
  50% { opacity: 0.6; }
}
.animate-pulse-slow {
  animation: pulse-slow 3s ease-in-out infinite;
}
.animate-fade-infinite {
  animation: fade-infinite 4s ease-in-out infinite;
}
.bg-gradient-radial {
  background: radial-gradient(circle at center, transparent 0%, black 100%);
}
iframe {
  filter: invert(90%) hue-rotate(180deg) brightness(0.8);
}
@media (max-width: 768px) {
  h1 {
    font-size: 3rem;
  }
}
</style>