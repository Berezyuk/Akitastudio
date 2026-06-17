<script setup>
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from "vue";
import { useHead } from '@unhead/vue'
import ServiceCard from "../components/ServiceCard.vue";
import { API_BASE } from '@/config/api.js'

useHead({
  title: 'Akita Studio — Профессиональная тюнинг-студия в Хабаровске | Уход за авто премиум-класса',
  link: [{ rel: 'canonical', href: 'https://akita-studio.ru/' }],
  meta: [
    { name: 'description', content: 'Студия детейлинга Akita Studio в Хабаровске. Профессиональный уход: полировка кузова, химчистка салона, защитные керамические покрытия и оклейка плёнкой. Работаем с 2015 года. Гарантия качества! Запишитесь онлайн.' },
    { property: 'og:title', content: 'Akita Studio — Профессиональная тюнинг-студия в Хабаровске' },
    { property: 'og:description', content: 'Студия детейлинга Akita Studio в Хабаровске. Профессиональный уход: полировка кузова, химчистка салона, защитные керамические покрытия и оклейка плёнкой.' },
    { property: 'og:type', content: 'website' },
    { property: 'og:url', content: 'https://akita-studio.ru/' },
    { property: 'og:image', content: 'https://akita-studio.ru/og-image.webp' },
    { property: 'og:image:width', content: '1080' },
    { property: 'og:image:height', content: '600' },
  ],
})

import uhodImage from "../assets/Images/uhod.webp";
import welcomeImage from "../assets/Images/Welcome.webp";
import aboutVideoDefault from "../assets/Video/About us (2).mp4";


const mousePosition = ref({ x: 0, y: 0 });
const heroRef = ref(null);

const particles = Array.from({ length: 25 }, () => ({
  width: `${Math.random() * 3 + 1}px`,
  height: `${Math.random() * 3 + 1}px`,
  left: `${Math.random() * 100}%`,
  top: `${Math.random() * 100}%`,
  animation: `float ${Math.random() * 8 + 4}s linear infinite`,
  animationDelay: `${Math.random() * 5}s`,
}));

let rafId = null;
const handleMouseMove = (e) => {
  if (rafId) return;
  rafId = requestAnimationFrame(() => {
    rafId = null;
    const rect = heroRef.value?.getBoundingClientRect();
    if (rect) {
      mousePosition.value = {
        x: (e.clientX - rect.left) / rect.width - 0.5,
        y: (e.clientY - rect.top) / rect.height - 0.5,
      };
    }
  });
};

const scrollToTop = () => {
  window.scrollTo({ top: 0, behavior: "smooth" });
};

const services = ref([]);
const categories = ref([]);
const loading = ref(true);
const portfolioItems = ref([]);
const portfolioLoading = ref(true);
const aboutVideoUrl = ref('')
const privacyPdfUrl = ref('');

const servicesByCategory = computed(() => {
  const map = {};
  services.value.forEach((service) => {
    const cat = service.category_name;
    if (!map[cat]) map[cat] = [];
    map[cat].push(service.name);
  });
  return map;
});

const categoryCards = computed(() => {
  return categories.value
    .filter((cat) => cat.show_on_home)
    .map((cat) => ({
      title: cat.name,
      imageUrl: cat.home_media_url || uhodImage,
      items: servicesByCategory.value[cat.name] || [],
    }));
});

const fetchServices = async () => {
  loading.value = true;
  try {
    const response = await fetch(`${API_BASE}/services`);
    const data = await response.json();
    if (data.success) {
      services.value = data.services;
    } else {
      console.error("API error:", data.error);
    }
  } catch (error) {
    console.error("Error fetching services:", error);
  } finally {
    loading.value = false;
  }
};

const fetchCategories = async () => {
  try {
    const res = await fetch(`${API_BASE}/categories`);
    const data = await res.json();
    if (data.success) categories.value = data.categories;
  } catch (e) {
    console.error("Error fetching categories:", e);
  }
};

const fetchPortfolio = async () => {
  portfolioLoading.value = true;
  try {
    const res = await fetch(`${API_BASE}/portfolio`);
    const data = await res.json();
    if (data.success) {
      portfolioItems.value = data.portfolio.filter((item) => item.video_url && item.show_on_home);
    }
  } catch (e) {
    console.error("Error fetching portfolio:", e);
  } finally {
    portfolioLoading.value = false;
  }
};

const feedbackForm = ref({
  name: '',
  phone: '',
  email: '',
  message: '',
  agree: false
})
const feedbackLoading = ref(false)
const feedbackModal = ref({ show: false, type: '', title: '', message: '' })

const showFeedbackModal = (type, title, message) => {
  feedbackModal.value = { show: true, type, title, message }
}

const applyPhoneMask = (digits) => {
  if (!digits) return ''
  let r = '+7 (' + digits.slice(0, 3)
  if (digits.length >= 3) r += ')'
  if (digits.length > 3) r += ' ' + digits.slice(3, 6)
  if (digits.length > 6) r += '-' + digits.slice(6, 8)
  if (digits.length > 8) r += '-' + digits.slice(8, 10)
  return r
}

const formatPhone = (e) => {
  const prevFormatted = feedbackForm.value.phone

  let raw = e.target.value.replace(/\D/g, '')
  if (raw.startsWith('7') || raw.startsWith('8')) raw = raw.slice(1)
  raw = raw.slice(0, 10)

  let result = applyPhoneMask(raw)

  // Пользователь удалил разделитель — форматтер его восстановил.
  // Дополнительно удаляем предыдущую цифру, чтобы дать возможность стереть.
  if (result === prevFormatted && e.target.value.length < prevFormatted.length && raw.length > 0) {
    raw = raw.slice(0, -1)
    result = applyPhoneMask(raw)
  }

  feedbackForm.value.phone = result
  e.target.value = result
}

const isFeedbackValid = computed(() => {
  const phoneDigits = feedbackForm.value.phone.replace(/\D/g, '')
  return (
    feedbackForm.value.name.trim() &&
    phoneDigits.length >= 11 &&
    feedbackForm.value.agree
  )
})

const submitFeedback = async () => {
  feedbackLoading.value = true

  const phoneDigits = feedbackForm.value.phone.replace(/\D/g, '')
  if (phoneDigits.length < 11) {
    showFeedbackModal('error', 'Проверьте данные', 'Введите корректный номер телефона.')
    feedbackLoading.value = false
    return
  }

  const message = feedbackForm.value.message.substring(0, 255)

  try {
    const res = await fetch(`${API_BASE}/feedback`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: feedbackForm.value.name,
        phone: phoneDigits,
        email: feedbackForm.value.email,
        message
      })
    })

    const data = await res.json()
    if (data.success) {
      feedbackForm.value = { name: '', phone: '', email: '', message: '', agree: false }
      showFeedbackModal('success', 'Сообщение отправлено!', 'Мы свяжемся с вами в ближайшее время.')
    } else {
      showFeedbackModal('error', 'Ошибка', data.error || 'Не удалось отправить сообщение.')
    }
  } catch {
    showFeedbackModal('error', 'Ошибка соединения', 'Не удалось отправить сообщение. Попробуйте позже.')
  } finally {
    feedbackLoading.value = false
  }
}

const portfolioVideoRefs = ref({})
const portfolioVideoErrors = ref({})
let portfolioObserver = null

const setPortfolioVideoRef = (el, id) => {
  if (el) portfolioVideoRefs.value[id] = el
}

const setupPortfolioObserver = () => {
  if (portfolioObserver) portfolioObserver.disconnect()

  // Обратное отображение element → id для обработки ошибок внутри observer
  const videoIdMap = new WeakMap()
  portfolioItems.value.forEach((item) => {
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

  portfolioItems.value.forEach((item) => {
    const video = portfolioVideoRefs.value[item.id]
    if (video) portfolioObserver.observe(video)
  })
}

watch(portfolioItems, async () => {
  await nextTick()
  setupPortfolioObserver()
}, { once: true })

onMounted(() => {
  window.addEventListener("mousemove", handleMouseMove);
  fetchServices();
  fetchCategories();
  fetchPortfolio();
  fetch(`${API_BASE}/settings`)
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        if (d.settings.about_video_url) aboutVideoUrl.value = d.settings.about_video_url
        if (d.settings.privacy_pdf_url) privacyPdfUrl.value = d.settings.privacy_pdf_url
      }
    })
    .catch(() => {});
});

onUnmounted(() => {
  window.removeEventListener("mousemove", handleMouseMove);
  if (rafId) cancelAnimationFrame(rafId);
  portfolioObserver?.disconnect()
});
</script>

<template>
  <div class="home-page bg-black">
    <section
      ref="heroRef"
      class="hero-3d relative min-h-[85vh] md:min-h-screen overflow-hidden pb-4 md:pb-0"
      :style="{
        transform: `scale(${1 + Math.abs(mousePosition.x) * 0.02}) translate(${mousePosition.x * 20}px, ${mousePosition.y * 20}px)`,
      }"
    >
      <img
        :src="welcomeImage"
        fetchpriority="high"
        loading="eager"
        alt=""
        aria-hidden="true"
        class="absolute inset-0 w-full h-full object-cover object-center"
      />
      <div
        class="absolute inset-0"
        :style="{
          background: `radial-gradient(circle at ${50 + mousePosition.x * 20}% ${50 + mousePosition.y * 20}%, rgba(252,147,3,0.15) 0%, rgba(0,0,0,0.85) 100%)`,
        }"
      ></div>
      <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div
          v-for="(p, i) in particles"
          :key="i"
          class="absolute rounded-full bg-[#fc9303] opacity-20"
          :style="{
            width: p.width,
            height: p.height,
            left: p.left,
            top: p.top,
            animation: p.animation,
            animationDelay: p.animationDelay,
          }"
        ></div>
      </div>

      <div
        class="relative z-10 container mx-auto px-4 min-h-screen flex items-center justify-center"
      >
        <div class="text-center max-w-4xl mx-auto">
          <div class="inline-block mb-4 sm:mb-6">
            <span
              class="px-3 py-1 sm:px-4 sm:py-2 text-[10px] sm:text-xs tracking-[0.2em] text-[#fc9303] border border-[#fc9303]/30 rounded-full bg-black/30 backdrop-blur-sm"
            >
              PREMIUM DETAILING STUDIO
            </span>
          </div>
          <div class="text-center">
            <div
              class="text-white text-xl sm:text-2xl md:text-3xl lg:text-4xl xl:text-5xl leading-tight tracking-[0.02em] font-medium"
            >
              ПРОФЕССИОНАЛЬНАЯ
            </div>
            <div
              class="text-white text-xl sm:text-2xl md:text-3xl lg:text-4xl xl:text-5xl leading-tight tracking-[0.02em] font-medium mt-1 sm:mt-2 md:mt-3"
            >
              ТЮНИНГ-СТУДИЯ
            </div>
            <div
              class="text-white text-xl sm:text-2xl md:text-3xl lg:text-4xl xl:text-5xl leading-tight tracking-[0.02em] font-medium mt-1 sm:mt-2 md:mt-3"
            >
              В ХАБАРОВСКЕ
            </div>
          </div>
          <p
            class="text-white text-center tracking-[0.02em] text-sm sm:text-base md:text-lg lg:text-xl mt-3 sm:mt-4 md:mt-5"
          >
            Премиальный уход за вашим автомобилем с гарантией качества.
          </p>
          <div
            class="flex flex-col sm:flex-row gap-3 sm:gap-4 md:gap-6 justify-center mt-6 sm:mt-8 md:mt-12"
          >
            <router-link
              to="/services"
              class="group w-full sm:w-auto px-4 sm:px-6 md:px-10 py-2 sm:py-3 md:py-4 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-white font-semibold text-sm sm:text-base md:text-lg rounded-full transition-all duration-300 hover:scale-105 hover:shadow-xl hover:shadow-orange-500/30 flex items-center justify-center gap-2"
            >
              Выбрать услугу
              <svg
                class="w-4 h-4 sm:w-5 sm:h-5 group-hover:translate-x-1 transition-transform"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17 8l4 4m0 0l-4 4m4-4H3"
                ></path>
              </svg>
            </router-link>
            <router-link
              to="/portfolio"
              class="group w-full sm:w-auto px-4 sm:px-6 md:px-10 py-2 sm:py-3 md:py-4 border border-[#fc9303] text-white font-semibold text-sm sm:text-base md:text-lg rounded-full hover:bg-[#fc9303] transition-all duration-300 flex items-center justify-center gap-2"
            >
              Портфолио
              <svg
                class="w-4 h-4 sm:w-5 sm:h-5 group-hover:translate-x-1 transition-transform"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17 8l4 4m0 0l-4 4m4-4H3"
                ></path>
              </svg>
            </router-link>
          </div>
          <div
            class="absolute bottom-6 sm:bottom-10 left-1/2 transform -translate-x-1/2 animate-bounce"
          >
            <div
              class="w-5 h-8 sm:w-6 sm:h-10 border-2 border-white/30 rounded-full flex justify-center"
            >
              <div
                class="w-1 h-1.5 sm:h-2 bg-[#fc9303] rounded-full mt-1.5 sm:mt-2 animate-[scrollDot_1.5s_ease-in-out_infinite]"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="py-12 sm:py-16 md:py-24 bg-black">
      <div class="container mx-auto px-4">
        <div class="text-center mb-8 sm:mb-12 md:mb-16">
          <span
            class="text-[#fc9303] text-xs sm:text-sm tracking-[0.2em] uppercase"
            >Наши услуги</span
          >
          <h2
            class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold mt-3 sm:mt-4"
          >
            <span
              class="bg-gradient-to-r from-white to-gray-400 bg-clip-text text-transparent"
              >Услуги</span
            >
          </h2>
        </div>

        <div v-if="loading" class="flex justify-center py-20">
          <div
            class="w-10 h-10 border-4 border-[#fc9303] border-t-transparent rounded-full animate-spin"
          ></div>
        </div>

        <div
          v-else
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-6 justify-items-center"
        >
          <ServiceCard
            v-for="card in categoryCards"
            :key="card.title"
            :imageUrl="card.imageUrl"
            :title="card.title"
            :items="card.items"
          />
        </div>

        <div class="flex justify-center mt-8 sm:mt-12 md:mt-16">
          <router-link
            to="/services"
            @click="scrollToTop"
            class="group px-5 sm:px-6 md:px-8 py-2 sm:py-2.5 md:py-3 border border-[#fc9303] text-white text-sm sm:text-base rounded-full hover:bg-[#fc9303] transition-all duration-300 flex items-center gap-2"
          >
            Узнать больше
            <svg
              class="w-4 h-4 group-hover:translate-x-1 transition-transform"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 5l7 7-7 7"
              ></path>
            </svg>
          </router-link>
        </div>
      </div>
    </section>

    <div class="relative w-full">
      <div
        class="absolute left-0 right-0 h-px bg-gradient-to-r from-transparent via-[#fc9303] to-transparent max-w-4xl mx-auto"
      ></div>
      <div class="py-8 md:py-12"></div>
    </div>

    <section
      class="pb-12 sm:pb-16 md:pb-24 bg-gradient-to-b from-black to-[#4d4d4d]/20"
    >
      <div class="container mx-auto px-4">
        <div class="text-center mb-8 sm:mb-12 md:mb-16">
          <span
            class="text-[#fc9303] text-xs sm:text-sm tracking-[0.2em] uppercase"
            >О студии</span
          >
          <h2
            class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold mt-3 sm:mt-4"
          >
            <span
              class="bg-gradient-to-r from-white to-gray-400 bg-clip-text text-transparent"
              >Akita Studio — это:</span
            >
          </h2>
        </div>
        <div
          class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8 lg:gap-10 items-stretch"
        >
          <div class="relative group flex">
            <div
              class="absolute -inset-0.5 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] rounded-2xl blur opacity-30 group-hover:opacity-70 transition duration-500"
            ></div>
            <div class="relative rounded-2xl overflow-hidden shadow-2xl w-full">
              <video
                :key="aboutVideoUrl"
                autoplay
                muted
                loop
                playsinline
                preload="none"
                class="w-full h-full object-cover"
              >
                <source
                  :src="aboutVideoUrl || aboutVideoDefault"
                  type="video/mp4"
                />
              </video>
            </div>
          </div>

          <div class="flex flex-col justify-between space-y-5 md:space-y-6">
            <div
              class="flex gap-4 md:gap-5 group hover:translate-x-1 transition-transform duration-300"
            >
              <div
                class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-[#fc9303]/20 flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform"
              >
                <svg
                  class="w-5 h-5 md:w-6 md:h-6 text-[#fc9303]"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                  />
                </svg>
              </div>
              <div>
                <h4
                  class="text-base md:text-lg font-bold mb-1 group-hover:text-[#fc9303] transition-colors"
                >
                  Команда профессионалов
                </h4>
                <p
                  class="text-sm md:text-base text-gray-400 leading-relaxed md:leading-relaxed text-justify"
                >
                  Компания работает с 2015 года. Наши мастера постоянно повышают
                  квалификацию, чтобы предложить лучший сервис.
                </p>
              </div>
            </div>
            <div
              class="flex gap-4 md:gap-5 group hover:translate-x-1 transition-transform duration-300"
            >
              <div
                class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-[#fc9303]/20 flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform"
              >
                <svg
                  class="w-5 h-5 md:w-6 md:h-6 text-[#fc9303]"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"
                  />
                </svg>
              </div>
              <div>
                <h4
                  class="text-base md:text-lg font-bold mb-1 group-hover:text-[#fc9303] transition-colors"
                >
                  Премиальные материалы
                </h4>
                <p
                  class="text-sm md:text-base text-gray-400 leading-relaxed text-justify"
                >
                  Используем только лучшие бренды и новейшее оборудование для
                  безупречного результата.
                </p>
              </div>
            </div>
            <div
              class="flex gap-4 md:gap-5 group hover:translate-x-1 transition-transform duration-300"
            >
              <div
                class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-[#fc9303]/20 flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform"
              >
                <svg
                  class="w-5 h-5 md:w-6 md:h-6 text-[#fc9303]"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                  />
                </svg>
              </div>
              <div>
                <h4
                  class="text-base md:text-lg font-bold mb-1 group-hover:text-[#fc9303] transition-colors"
                >
                  Качество сервиса
                </h4>
                <p
                  class="text-sm md:text-base text-gray-400 leading-relaxed text-justify"
                >
                  Контроль на каждом этапе и гарантия на все виды работ.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="portfolio py-12 sm:py-16 md:py-24 bg-black">
      <div class="container mx-auto px-4">
        <div class="text-center mb-8 sm:mb-12 md:mb-16">
          <span
            class="text-[#fc9303] text-xs sm:text-sm tracking-[0.2em] uppercase"
            >Наши работы</span
          >
          <h2
            class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold mt-3 sm:mt-4"
          >
            <span
              class="bg-gradient-to-r from-white to-gray-400 bg-clip-text text-transparent"
              >Портфолио</span
            >
          </h2>
        </div>

        <div v-if="portfolioLoading" class="flex justify-center py-20">
          <div
            class="w-10 h-10 border-4 border-[#fc9303] border-t-transparent rounded-full animate-spin"
          ></div>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-6 justify-items-center">
          <div
            v-for="item in portfolioItems"
            :key="item.id"
            class="portfolio_card"
          >
            <video
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
            <h4 class="portfolio_title">{{ item.category_name }}</h4>
            <div class="portfolio_hover-content">
              <ul class="portfolio_list">
                <li v-if="item.service_name">{{ item.service_name }}</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="flex justify-center mt-8 sm:mt-12 md:mt-16">
          <router-link
            to="/portfolio"
            @click="scrollToTop"
            class="group px-5 sm:px-6 md:px-8 py-2 sm:py-2.5 md:py-3 border border-[#fc9303] text-white text-sm sm:text-base rounded-full hover:bg-[#fc9303] transition-all duration-300 flex items-center gap-2"
          >
            Все работы
            <svg
              class="w-4 h-4 group-hover:translate-x-1 transition-transform"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 5l7 7-7 7"
              ></path>
            </svg>
          </router-link>
        </div>
      </div>
    </section>

    <section class="feedback-section pb-12 sm:pb-16 md:pb-24 bg-black">
      <div class="container mx-auto px-4">
        <div class="max-w-3xl mx-auto">
          <div class="text-center mb-8 sm:mb-12">
            <span
              class="text-[#fc9303] text-xs sm:text-sm tracking-[0.2em] uppercase"
              >Свяжитесь с нами</span
            >
            <h2
              class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold mt-3 sm:mt-4"
            >
              <span
                class="bg-gradient-to-r from-white to-gray-400 bg-clip-text text-transparent"
              >
                Есть вопросы?
              </span>
            </h2>
            <p
              class="text-gray-400 text-sm sm:text-base mt-3 sm:mt-4 max-w-2xl mx-auto"
            >
              Оставьте свои контакты, и мы свяжемся с вами в ближайшее время
            </p>
          </div>

          <div
            class="bg-gray-900/50 backdrop-blur-sm rounded-2xl border border-gray-800 p-6 sm:p-8"
          >
            <form @submit.prevent="submitFeedback" class="space-y-5">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                  <label for="feedback-name" class="block text-sm text-gray-400 mb-2">Ваше имя *</label>
                  <input
                    id="feedback-name"
                    v-model="feedbackForm.name"
                    type="text"
                    required
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] transition-colors"
                    placeholder="Иван Иванов"
                  />
                </div>
                <div>
                  <label for="feedback-phone" class="block text-sm text-gray-400 mb-2">Номер телефона *</label>
                  <input
                    id="feedback-phone"
                    :value="feedbackForm.phone"
                    @input="formatPhone"
                    type="tel"
                    inputmode="numeric"
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] transition-colors"
                    placeholder="+7 (___) ___-__-__"
                  />
                </div>
              </div>
              <div>
                <label for="feedback-email" class="block text-sm text-gray-400 mb-2">Электронная почта</label>
                <input
                  id="feedback-email"
                  v-model="feedbackForm.email"
                  type="email"
                  class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] transition-colors"
                  placeholder="mail@example.com"
                />
              </div>
              <div>
                <label for="feedback-message" class="block text-sm text-gray-400 mb-2">Сообщение</label>
                <textarea
                  id="feedback-message"
                  v-model="feedbackForm.message"
                  rows="4"
                  class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] transition-colors resize-none"
                  placeholder="Ваш вопрос или сообщение..."
                ></textarea>
              </div>
              <div class="flex items-center gap-2">
                <input
                  type="checkbox"
                  v-model="feedbackForm.agree"
                  required
                  class="w-5 h-5 accent-[#fc9303] rounded flex-shrink-0"
                />
                <label class="text-sm text-gray-400">
                  Я даю согласие на
                  <a
                    v-if="privacyPdfUrl"
                    :href="privacyPdfUrl"
                    target="_blank"
                    rel="noopener"
                    class="text-[#fc9303] hover:underline"
                  >обработку персональных данных</a>
                  <span v-else class="text-[#fc9303]">обработку персональных данных</span>
                  *
                </label>
              </div>
              <button
                type="submit"
                :disabled="feedbackLoading || !isFeedbackValid"
                class="w-full bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-white font-semibold py-3 rounded-xl transition-all duration-300 hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ feedbackLoading ? "Отправка..." : "Отправить сообщение" }}
              </button>
            </form>
          </div>
        </div>
      </div>
    </section>

    <Transition name="modal">
      <div v-if="feedbackModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="feedbackModal.show = false"></div>
        <div
          class="relative bg-gray-900 border rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl"
          :class="feedbackModal.type === 'success' ? 'border-[#fc9303]' : 'border-red-500'"
        >
          <div
            class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
            :class="feedbackModal.type === 'success' ? 'bg-[#fc9303]/20' : 'bg-red-500/20'"
          >
            <svg v-if="feedbackModal.type === 'success'" class="w-8 h-8 text-[#fc9303]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <svg v-else class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-white mb-2">{{ feedbackModal.title }}</h3>
          <p class="text-gray-400 mb-6">{{ feedbackModal.message }}</p>
          <button
            @click="feedbackModal.show = false"
            class="px-8 py-2.5 rounded-xl font-semibold text-white transition-all duration-300"
            :class="feedbackModal.type === 'success' ? 'bg-gradient-to-r from-[#fc9303] to-[#ff6b00] hover:scale-105' : 'bg-red-600 hover:bg-red-500'"
          >
            Закрыть
          </button>
        </div>
      </div>
    </Transition>

    <section
      class="relative pb-12 sm:pb-16 md:pb-24 overflow-hidden bg-gradient-to-b from-black to-[#4d4d4d]/20"
    >
      <div class="absolute inset-0 bg-black/30"></div>
      <div class="container mx-auto px-4 text-center relative z-10">
        <h2
          class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl xl:text-6xl font-bold mb-3 sm:mb-4 md:mb-6"
        >
          <span
            class="bg-gradient-to-r from-white to-[#fc9303] bg-clip-text text-transparent"
            >Готовы к преображению?</span
          >
        </h2>
        <p
          class="text-sm sm:text-base md:text-lg lg:text-xl text-gray-400 mb-6 sm:mb-8 md:mb-10 max-w-2xl mx-auto px-4"
        >
          Оставьте заявку, и наш менеджер подберет идеальный комплекс услуг для
          вашего автомобиля
        </p>
        <div
          class="flex flex-col sm:flex-row gap-3 sm:gap-4 md:gap-6 justify-center"
        >
          <router-link
            to="/booking"
            class="px-6 sm:px-8 md:px-12 py-2 sm:py-3 md:py-4 lg:py-5 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-white font-bold text-sm sm:text-base md:text-lg rounded-full hover:scale-105 transition-all duration-300 shadow-lg shadow-orange-500/30"
          >
            Записаться
          </router-link>
          <router-link
            to="/contacts"
            class="px-6 sm:px-8 md:px-12 py-2 sm:py-3 md:py-4 lg:py-5 border-2 border-white/30 text-white font-bold text-sm sm:text-base md:text-lg rounded-full hover:border-[#fc9303] hover:bg-[#fc9303]/10 transition-all duration-300"
          >
            Связаться
          </router-link>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.portfolio_card {
  width: 100%;
  max-width: 260px;
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

@keyframes float {
  0%,
  100% {
    transform: translateY(0) translateX(0);
  }
  50% {
    transform: translateY(-20px) translateX(10px);
  }
}
@keyframes scrollDot {
  0% {
    transform: translateY(0);
    opacity: 1;
  }
  100% {
    transform: translateY(20px);
    opacity: 0;
  }
}
.hero-3d {
  transition: transform 0.3s ease-out;
}
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
