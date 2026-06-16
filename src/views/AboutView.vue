<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useHead } from '@unhead/vue'

useHead({
  title: 'О студии Akita Studio — Тюнинг-студия в Хабаровске',
  link: [{ rel: 'canonical', href: 'https://akita-studio.ru/about' }],
  meta: [
    { name: 'description', content: 'Akita Studio — команда профессионалов с 2015 года. Используем премиальные материалы и современное оборудование. Качество, проверенное временем.' },
    { property: 'og:title', content: 'О студии Akita Studio — Тюнинг-студия в Хабаровске' },
    { property: 'og:description', content: 'Akita Studio — команда профессионалов с 2015 года. Используем премиальные материалы и современное оборудование.' },
    { property: 'og:url', content: 'https://akita-studio.ru/about' },
    { property: 'og:image', content: 'https://akita-studio.ru/og-image.webp' },
    { property: 'og:image:width', content: '1080' },
    { property: 'og:image:height', content: '600' },
  ],
})

// Импортируем видео
import washMotorVideo from "@/assets/Video/Wash_motor.mp4"
import portfolio2Video from "@/assets/Video/portfolio2.mp4"
import portfolio3Video from "@/assets/Video/portfolio3.mp4"
import portfolio4Video from "@/assets/Video/portfolio4.mp4"
import portfolio5Video from "@/assets/Video/portfolio5.mp4"

// Импорт фото основателя
import igorPhoto from "@/assets/Images/Igor.png"

const scrollProgress = ref(0)

const principles = [
  {
    icon: "🎯",
    title: "Индивидуальный подход",
    description: "Каждый автомобиль уникален. Мы разрабатываем персональную программу восстановления под ваши задачи и бюджет.",
    color: "from-[#fc9303]/20 to-transparent"
  },
  {
    icon: "✨",
    title: "Премиальные материалы",
    description: "Используем только лучшие бренды: Koch Chemie, Chemical Guys, Shine Systems, Letech.",
    color: "from-[#ff6b00]/20 to-transparent"
  },
  {
    icon: "🔧",
    title: "Гарантия качества",
    description: "Даём гарантию на все виды работ. Ваша уверенность — наша репутация.",
    color: "from-[#fc9303]/20 to-transparent"
  }
]

const handleScroll = () => {
  const scrollY = window.scrollY
  const maxScroll = document.documentElement.scrollHeight - window.innerHeight
  scrollProgress.value = scrollY / maxScroll
}

onMounted(() => {
  window.addEventListener('scroll', handleScroll)
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
})
</script>

<template>
  <div class="about-page bg-black text-white">
    
    <!-- ПЕРВЫЙ ЭКРАН: Герой -->
    <section class="relative h-screen flex items-center justify-center overflow-hidden">
      <video autoplay muted loop playsinline class="absolute inset-0 w-full h-full object-cover opacity-40">
        <source :src="portfolio5Video" type="video/mp4">
      </video>
      <div class="absolute inset-0 bg-gradient-to-b from-black via-transparent to-black"></div>
      <div class="relative z-10 text-center px-4 container">
        <h1 class="text-7xl md:text-9xl font-bold mb-6">
          <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">AKITA</span>
          <span class="text-white"> STUDIO</span>
        </h1>
        <p class="text-xl md:text-2xl text-gray-400 max-w-2xl mx-auto">Детейлинг, который чувствуется</p>
      </div>
    </section>

    <!-- ВТОРОЙ ЭКРАН: Основатель -->
    <section class="py-32 relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-b from-black to-[#4d4d4d]/20"></div>
      <div class="container mx-auto px-4 relative z-10">
        <div class="text-center mb-20">
          <span class="text-[#fc9303] text-sm tracking-[0.2em] uppercase">Лицо бренда</span>
          <h2 class="text-5xl md:text-6xl font-bold mt-4">
            <span class="bg-gradient-to-r from-white to-gray-400 bg-clip-text text-transparent">Создатель студии</span>
          </h2>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
          <!-- Фото с уменьшенной высотой и скруглёнными углами -->
          <div class="relative group flex justify-center">
            <div class="relative w-full max-w-md mx-auto">
              <div class="absolute -inset-1 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] rounded-2xl blur opacity-30 group-hover:opacity-70 transition duration-500"></div>
              <div class="relative rounded-2xl overflow-hidden">
                <img :src="igorPhoto" alt="Игорь Шетогубов" class="w-full h-auto object-contain rounded-2xl">
              </div>
            </div>
          </div>
          
          <div class="flex flex-col space-y-6">
            <div>
              <h3 class="text-3xl md:text-4xl font-bold text-white">Игорь Шетогубов</h3>
              <p class="text-[#fc9303] text-lg mt-2">Основатель и руководитель</p>
            </div>
            <div class="h-px w-16 bg-gradient-to-r from-[#fc9303] to-transparent"></div>
            <p class="text-gray-300 text-lg leading-relaxed text-justify">Меня зовут Игорь Шетогубов, я создатель и руководитель одной из первых в регионе профессиональной тюнинг-студии «Akita Studio».</p>
            <!-- Текст цитаты без кавычек и с обычным стилем -->
            <p class="text-gray-300 text-lg leading-relaxed text-justify">«Akita Studio» — это не просто тюнинг-студия с 10-летним опытом, это команда экспертов, любящих своё дело. Мы используем только самые современные технологии и профессиональные средства для достижения безупречного результата. Мы умеем превосходить ожидания и создавать для вас автомобиль мечты.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- НОВЫЙ БЛОК: НАШИ ПРИНЦИПЫ (карточки одинаковой высоты) -->
    <section class="py-32 relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-b from-[#4d4d4d]/10 to-black"></div>
      <div class="container mx-auto px-4 relative z-10">
        <div class="text-center mb-20">
          <span class="text-[#fc9303] text-sm tracking-[0.2em] uppercase">Философия работы</span>
          <h2 class="text-5xl md:text-6xl font-bold mt-4">
            <span class="bg-gradient-to-r from-white to-gray-400 bg-clip-text text-transparent">Наши принципы</span>
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div v-for="(item, idx) in principles" :key="idx"
               class="group relative bg-gray-900/40 backdrop-blur-sm rounded-2xl p-8 border border-gray-800 hover:border-[#fc9303] transition-all duration-500 hover:-translate-y-2 flex flex-col">
            <div class="absolute inset-0 rounded-2xl bg-gradient-to-br opacity-0 group-hover:opacity-100 transition-opacity duration-500" :class="item.color"></div>
            <div class="relative z-10 flex flex-col h-full">
              <div class="text-6xl mb-6 group-hover:scale-110 transition-transform duration-300 text-center">{{ item.icon }}</div>
              <h3 class="text-2xl font-bold text-white mb-4 text-center min-h-[4rem] flex items-center justify-center">{{ item.title }}</h3>
              <p class="text-gray-400 leading-relaxed text-justify flex-grow">{{ item.description }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ПЯТЫЙ ЭКРАН: Манифест (без значков поверх видео) -->
    <section class="py-32 bg-gradient-to-b from-[#4d4d4d]/20 to-black">
      <div class="container mx-auto px-4">
        <h2 class="text-5xl md:text-6xl font-bold text-center mb-20">
          <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">Наша философия</span>
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
          <div class="text-center group">
            <div class="relative w-48 h-48 mx-auto mb-8">
              <div class="absolute inset-0 rounded-full bg-gradient-to-r from-[#fc9303]/10 to-transparent animate-ping-slow"></div>
              <div class="absolute inset-4 rounded-full bg-gradient-to-br from-[#fc9303]/20 to-transparent backdrop-blur-sm flex items-center justify-center overflow-hidden">
                <video :src="washMotorVideo" class="w-full h-full object-cover opacity-30 group-hover:opacity-50 transition-opacity duration-500" muted loop playsinline autoplay></video>
              </div>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">Мы не моем.</h3>
            <p class="text-xl text-[#fc9303]">Мы возвращаем жизнь.</p>
          </div>
          <div class="text-center group">
            <div class="relative w-48 h-48 mx-auto mb-8">
              <div class="absolute inset-0 rounded-full bg-gradient-to-r from-[#fc9303]/10 to-transparent animate-ping-slow animation-delay-300"></div>
              <div class="absolute inset-4 rounded-full bg-gradient-to-br from-[#fc9303]/20 to-transparent backdrop-blur-sm flex items-center justify-center overflow-hidden">
                <video :src="portfolio3Video" class="w-full h-full object-cover opacity-30 group-hover:opacity-50 transition-opacity duration-500" muted loop playsinline autoplay></video>
              </div>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">Каждая деталь</h3>
            <p class="text-xl text-[#fc9303]">имеет значение.</p>
          </div>
          <div class="text-center group">
            <div class="relative w-48 h-48 mx-auto mb-8">
              <div class="absolute inset-0 rounded-full bg-gradient-to-r from-[#fc9303]/10 to-transparent animate-ping-slow animation-delay-600"></div>
              <div class="absolute inset-4 rounded-full bg-gradient-to-br from-[#fc9303]/20 to-transparent backdrop-blur-sm flex items-center justify-center overflow-hidden">
                <video :src="portfolio5Video" class="w-full h-full object-cover opacity-30 group-hover:opacity-50 transition-opacity duration-500" muted loop playsinline autoplay></video>
              </div>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">Ваша машина</h3>
            <p class="text-xl text-[#fc9303]">заслуживает лучшего.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ФИНАЛЬНЫЙ ЭКРАН: CTA -->
    <section class="relative h-screen flex items-center justify-center overflow-hidden">
      <video autoplay muted loop playsinline class="absolute inset-0 w-full h-full object-cover opacity-30">
        <source :src="portfolio2Video" type="video/mp4">
      </video>
      <div class="absolute inset-0 bg-gradient-radial from-transparent via-black to-black"></div>
      <div class="relative z-10 text-center px-4 container">
        <h2 class="text-5xl md:text-7xl font-bold mb-12 max-w-4xl mx-auto">
          <span class="bg-gradient-to-r from-white to-[#fc9303] bg-clip-text text-transparent">Готовы почувствовать разницу?</span>
        </h2>
        <div class="flex flex-col sm:flex-row gap-6 justify-center">
          <router-link to="/booking" class="group relative px-12 py-5 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-white font-bold text-xl rounded-full overflow-hidden">
            <span class="relative z-10">Записаться</span>
            <span class="absolute inset-0 bg-white transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left opacity-20"></span>
          </router-link>
          <router-link to="/portfolio" class="group relative px-12 py-5 border-2 border-[#fc9303] text-white font-bold text-xl rounded-full overflow-hidden">
            <span class="relative z-10">Смотреть портфолио</span>
            <span class="absolute inset-0 bg-[#fc9303] transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left opacity-20"></span>
          </router-link>
        </div>
      </div>
      <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-[#fc9303] rounded-full filter blur-[100px] opacity-10 animate-pulse-slow"></div>
      <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-[#ff6b00] rounded-full filter blur-[150px] opacity-10 animate-pulse-slow animation-delay-1000"></div>
    </section>
  </div>
</template>

<style scoped>
@keyframes spin-slow {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
@keyframes spin-slow-reverse {
  from { transform: rotate(360deg); }
  to { transform: rotate(0deg); }
}
@keyframes ping-slow {
  75%, 100% {
    transform: scale(1.5);
    opacity: 0;
  }
}
.animate-spin-slow {
  animation: spin-slow 8s linear infinite;
}
.animate-spin-slow-reverse {
  animation: spin-slow-reverse 10s linear infinite;
}
.animate-ping-slow {
  animation: ping-slow 3s cubic-bezier(0, 0, 0.2, 1) infinite;
}
.animation-delay-300 {
  animation-delay: 300ms;
}
.animation-delay-600 {
  animation-delay: 600ms;
}
.animation-delay-1000 {
  animation-delay: 1000ms;
}
.bg-gradient-radial {
  background: radial-gradient(circle at center, transparent 0%, black 100%);
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
@media (max-width: 768px) {
  h1 {
    font-size: 4rem;
  }
  h2 {
    font-size: 2.5rem;
  }
}
</style>