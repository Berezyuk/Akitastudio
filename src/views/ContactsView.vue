<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useHead } from '@unhead/vue'
// Официальные логотипы: SVG для них не публикуют, берём иконки приложений.
import maxLogo from '@/assets/Images/max-logo.png'
import gis2Logo from '@/assets/Images/2gis-logo.png'

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
  telegram: 'https://t.me/akita_auto',
  max: 'https://web.max.ru/76436410',
  whatsapp: 'https://wa.me/79098029868',
  yandexMaps: 'https://yandex.ru/maps/org/avto_akita/193553178512/?ll=135.062781%2C48.465607&z=15',
  gis2: 'https://2gis.ru/khabarovsk/firm/70000001028746438'
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

// Ленивая загрузка фоновых видео — как в HomeView/AboutView.
// autoplay заставляет браузер скачать файл сразу и отменяет preload="none".
let videoObserver = null

onMounted(() => {
  window.addEventListener('mousemove', handleMouseMove)
  clockTimer = setInterval(() => { now.value = new Date() }, 60000)

  const videos = document.querySelectorAll('video[data-lazy-video]')
  if (videos.length) {
    videoObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) entry.target.play().catch(() => {})
          else entry.target.pause()
        })
      },
      { threshold: 0.25 }
    )
    videos.forEach((v) => videoObserver.observe(v))
  }
})

onUnmounted(() => {
  window.removeEventListener('mousemove', handleMouseMove)
  if (rafId) cancelAnimationFrame(rafId)
  clearInterval(clockTimer)
  videoObserver?.disconnect()
})
</script>

<template>
  <div class="contacts-page bg-black text-white">
    
    <!-- Герой -->
    <section class="relative h-[55vh] md:h-screen flex items-center justify-center overflow-hidden">
      <video data-lazy-video muted loop playsinline preload="none" aria-hidden="true" class="absolute inset-0 w-full h-full object-cover opacity-40">
        <source src="@/assets/Video/portfolio2.mp4" type="video/mp4">
      </video>
      <div class="absolute inset-0 bg-gradient-to-b from-black via-transparent to-black"></div>
      <div class="absolute top-1/2 left-0 w-full h-px bg-gradient-to-r from-transparent via-[#fc9303] to-transparent animate-pulse-slow"></div>
      <div class="relative z-10 text-center px-4">
        <span class="text-xs md:text-sm tracking-[0.3em] text-gray-400 mb-3 md:mb-4 block">КОНТАКТЫ</span>
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
      <div class="container mx-auto px-4">
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
      <div class="container mx-auto px-4">
        <h2 class="text-4xl md:text-5xl font-bold text-center mb-16">
          <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">Мы в сети</span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 max-w-3xl mx-auto">
          <!-- MAX -->
          <a :href="socialLinks.max" target="_blank" rel="noopener noreferrer"
             class="group bg-gray-900/50 backdrop-blur-sm rounded-2xl p-5 md:p-8 border border-gray-800 hover:border-[#fc9303] transition-all duration-500 hover:-translate-y-2">
            <div class="flex items-center gap-4 mb-4">
              <img :src="maxLogo" alt="" aria-hidden="true" class="w-14 h-14 rounded-xl object-cover" />
              <div>
                <h3 class="text-2xl font-bold text-white">MAX</h3>
                <p class="text-[#fc9303]">Напишите нам</p>
              </div>
            </div>
            <p class="text-gray-400">Свяжитесь с нами в мессенджере MAX — ответим в рабочее время</p>
          </a>
          <!-- Telegram -->
          <a :href="socialLinks.telegram" target="_blank" rel="noopener noreferrer"
             class="group bg-gray-900/50 backdrop-blur-sm rounded-2xl p-5 md:p-8 border border-gray-800 hover:border-[#fc9303] transition-all duration-500 hover:-translate-y-2">
            <div class="flex items-center gap-4 mb-4">
              <div class="w-14 h-14 rounded-xl bg-[#2AABEE] flex items-center justify-center">
                <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"></path>
                </svg>
              </div>
              <div>
                <h3 class="text-2xl font-bold text-white">Telegram<span class="text-[#fc9303] ml-0.5">*</span></h3>
                <p class="text-[#fc9303]">@akita_auto</p>
              </div>
            </div>
            <p class="text-gray-400">Подпишитесь на наш Telegram-канал, чтобы первыми узнавать о новостях</p>
          </a>
          <!-- Instagram -->
          <a :href="socialLinks.instagram" target="_blank" rel="noopener noreferrer"
             class="group bg-gray-900/50 backdrop-blur-sm rounded-2xl p-5 md:p-8 border border-gray-800 hover:border-[#fc9303] transition-all duration-500 hover:-translate-y-2">
            <div class="flex items-center gap-4 mb-4">
              <div class="w-14 h-14 rounded-xl bg-gradient-to-tr from-[#833AB4] via-[#FD1D1D] to-[#FCB045] flex items-center justify-center">
                <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M7.0301.084c-1.2768.0602-2.1487.264-2.911.5634-.7888.3075-1.4575.72-2.1228 1.3877-.6652.6677-1.075 1.3368-1.3802 2.127-.2954.7638-.4956 1.6365-.552 2.914-.0564 1.2775-.0689 1.6882-.0626 4.947.0062 3.2586.0206 3.6671.0825 4.9473.061 1.2765.264 2.1482.5635 2.9107.308.7889.72 1.4573 1.388 2.1228.6679.6655 1.3365 1.0743 2.1285 1.38.7632.295 1.6361.4961 2.9134.552 1.2773.056 1.6884.069 4.9462.0627 3.2578-.0062 3.668-.0207 4.9478-.0814 1.28-.0607 2.147-.2652 2.9098-.5633.7889-.3086 1.4578-.72 2.1228-1.3881.665-.6682 1.0745-1.3378 1.3795-2.1284.2957-.7632.4966-1.636.552-2.9124.056-1.2809.0692-1.6898.063-4.948-.0063-3.2583-.021-3.6668-.0817-4.9465-.0607-1.2797-.264-2.1487-.5633-2.9117-.3084-.7889-.72-1.4568-1.3876-2.1228C21.2982 1.33 20.628.9208 19.8378.6165 19.074.321 18.2017.1197 16.9244.0645 15.6471.0093 15.236-.005 11.977.0014 8.718.0076 8.31.0215 7.0301.0839m.1402 21.6932c-1.17-.0509-1.8053-.2453-2.2287-.408-.5606-.216-.96-.4771-1.3819-.895-.422-.4178-.6811-.8186-.9-1.378-.1644-.4234-.3624-1.058-.4171-2.228-.0595-1.2645-.072-1.6442-.079-4.848-.007-3.2037.0053-3.583.0607-4.848.05-1.169.2456-1.805.408-2.2282.216-.5613.4762-.96.895-1.3816.4188-.4217.8184-.6814 1.3783-.9003.423-.1651 1.0575-.3614 2.227-.4171 1.2655-.06 1.6447-.072 4.848-.079 3.2033-.007 3.5835.005 4.8495.0608 1.169.0508 1.8053.2445 2.228.408.5608.216.96.4754 1.3816.895.4217.4194.6816.8176.9005 1.3787.1653.4217.3617 1.056.4169 2.2263.0602 1.2655.0739 1.645.0796 4.848.0058 3.203-.0055 3.5834-.061 4.848-.051 1.17-.245 1.8055-.408 2.2294-.216.5604-.4763.96-.8954 1.3814-.419.4215-.8181.6811-1.3783.9-.4224.1649-1.0577.3617-2.2262.4174-1.2656.0595-1.6448.072-4.8493.079-3.2045.007-3.5825-.006-4.848-.0608M16.953 5.5864A1.44 1.44 0 1 0 18.39 4.144a1.44 1.44 0 0 0-1.437 1.4424M5.8385 12.012c.0067 3.4032 2.7706 6.1557 6.173 6.1493 3.4026-.0065 6.157-2.7701 6.1506-6.1733-.0065-3.4032-2.771-6.1565-6.174-6.1498-3.403.0067-6.156 2.771-6.1496 6.1738M8 12.0077a4 4 0 1 1 4.008 3.9921A3.9996 3.9996 0 0 1 8 12.0077"></path>
                </svg>
              </div>
              <div>
                <h3 class="text-2xl font-bold text-white">Instagram<span class="text-[#fc9303] ml-0.5">*</span></h3>
                <p class="text-[#fc9303]">@auto.akita</p>
              </div>
            </div>
            <p class="text-gray-400">Следите за нашими работами, процессом и акциями в Instagram</p>
          </a>
          <!-- WhatsApp -->
          <a :href="socialLinks.whatsapp" target="_blank" rel="noopener noreferrer"
             class="group bg-gray-900/50 backdrop-blur-sm rounded-2xl p-5 md:p-8 border border-gray-800 hover:border-[#fc9303] transition-all duration-500 hover:-translate-y-2">
            <div class="flex items-center gap-4 mb-4">
              <div class="w-14 h-14 rounded-xl bg-[#25D366] flex items-center justify-center">
                <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"></path>
                </svg>
              </div>
              <div>
                <h3 class="text-2xl font-bold text-white">WhatsApp<span class="text-[#fc9303] ml-0.5">*</span></h3>
                <p class="text-[#fc9303]">+7 909 802-98-68</p>
              </div>
            </div>
            <p class="text-gray-400">Напишите нам в WhatsApp — обсудим услуги и запишем на удобное время</p>
          </a>
          <!-- Яндекс Карты -->
          <a :href="socialLinks.yandexMaps" target="_blank" rel="noopener noreferrer"
             class="group bg-gray-900/50 backdrop-blur-sm rounded-2xl p-5 md:p-8 border border-gray-800 hover:border-[#fc9303] transition-all duration-500 hover:-translate-y-2">
            <div class="flex items-center gap-4 mb-4">
              <div class="w-14 h-14 rounded-xl bg-white flex items-center justify-center">
                <svg class="w-8 h-8" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path d="M8 1C4.6862 1 2 3.6862 2 7C2 8.6563 2.6711 10.156 3.7565 11.2417C4.8422 12.328 7.4 13.9 7.55 15.55C7.57249 15.7974 7.7516 16 8 16C8.2484 16 8.42751 15.7974 8.45 15.55C8.6 13.9 11.1578 12.328 12.2435 11.2417C13.3289 10.156 14 8.6563 14 7C14 3.6862 11.3138 1 8 1Z" fill="#FF4433"/>
                  <path d="M8.00002 9.10015C9.15982 9.10015 10.1 8.15994 10.1 7.00015C10.1 5.84035 9.15982 4.90015 8.00002 4.90015C6.84023 4.90015 5.90002 5.84035 5.90002 7.00015C5.90002 8.15994 6.84023 9.10015 8.00002 9.10015Z" fill="white"/>
                </svg>
              </div>
              <div>
                <h3 class="text-2xl font-bold text-white">Яндекс Карты</h3>
                <p class="text-[#fc9303]">Отзывы и маршрут</p>
              </div>
            </div>
            <p class="text-gray-400">Посмотрите отзывы и постройте маршрут до студии в Яндекс Картах</p>
          </a>
          <!-- 2GIS -->
          <a :href="socialLinks.gis2" target="_blank" rel="noopener noreferrer"
             class="group bg-gray-900/50 backdrop-blur-sm rounded-2xl p-5 md:p-8 border border-gray-800 hover:border-[#fc9303] transition-all duration-500 hover:-translate-y-2">
            <div class="flex items-center gap-4 mb-4">
              <img :src="gis2Logo" alt="" aria-hidden="true" class="w-14 h-14 rounded-xl object-cover" />
              <div>
                <h3 class="text-2xl font-bold text-white">2GIS</h3>
                <p class="text-[#fc9303]">Мы на карте города</p>
              </div>
            </div>
            <p class="text-gray-400">Найдите нас в 2GIS — режим работы, отзывы и как проехать</p>
          </a>
        </div>
      </div>
    </section>

    <!-- Режим работы и контакты -->
    <section class="py-12">
      <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 max-w-5xl mx-auto">
          <!-- Режим работы -->
          <div class="bg-gray-900/30 backdrop-blur-sm rounded-2xl border border-gray-800 p-5 md:p-8 text-center">
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
          <div class="bg-gray-900/30 backdrop-blur-sm rounded-2xl border border-gray-800 p-5 md:p-8">
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
                  <p class="text-gray-400 text-sm">{{ method.title }}<span v-if="['Telegram','WhatsApp'].includes(method.title)" class="text-[#fc9303] ml-0.5">*</span></p>
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
      <video data-lazy-video muted loop playsinline preload="none" aria-hidden="true" class="absolute inset-0 w-full h-full object-cover opacity-20">
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
</style>