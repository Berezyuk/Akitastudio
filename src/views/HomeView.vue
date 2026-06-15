<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import ServiceCard from "../components/ServiceCard.vue";
import { API_BASE } from '@/config/api.js'

import uhodImage from "../assets/Images/uhod.webp";
import plenkaImage from "../assets/Images/plenka.webp";
import polishImage from "../assets/Images/polish.webp";
import ceramicImage from "../assets/Images/ceramic.webp";
import salonImage from "../assets/Images/salon.webp";
import okrasImage from "../assets/Images/okras.webp";
import dopImage from "../assets/Images/dop.webp";
import firmaImage from "../assets/Images/firma.webp";
import welcomeImage from "../assets/Images/Welcome.webp";
import aboutVideoDefault from "../assets/Video/About us (2).mp4";


const mousePosition = ref({ x: 0, y: 0 });
const heroRef = ref(null);

const handleMouseMove = (e) => {
  const rect = heroRef.value?.getBoundingClientRect();
  if (rect) {
    mousePosition.value = {
      x: (e.clientX - rect.left) / rect.width - 0.5,
      y: (e.clientY - rect.top) / rect.height - 0.5,
    };
  }
};

const scrollToTop = () => {
  window.scrollTo({ top: 0, behavior: "smooth" });
};

// Данные из API
const services = ref([]);
const categories = ref([]);
const loading = ref(true);
const portfolioItems = ref([]);
const portfolioLoading = ref(true);
const aboutVideoUrl = ref('');

// Группировка услуг по имени категории
const servicesByCategory = computed(() => {
  const map = {};
  services.value.forEach((service) => {
    const cat = service.category_name;
    if (!map[cat]) map[cat] = [];
    map[cat].push(service.name);
  });
  return map;
});

// Карточки для главной — только категории с show_on_home === true
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

// Форма обратной связи
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

onMounted(() => {
  window.addEventListener("mousemove", handleMouseMove);
  fetchServices();
  fetchCategories();
  fetchPortfolio();
  fetch(`${API_BASE}/settings`)
    .then(r => r.json())
    .then(d => { if (d.success && d.settings.about_video_url) aboutVideoUrl.value = d.settings.about_video_url })
    .catch(() => {});
});

onUnmounted(() => {
  window.removeEventListener("mousemove", handleMouseMove);
});
</script>

<template>
  <div class="home-page bg-black">
    <!-- HERO СЕКЦИЯ (без изменений) -->
    <section
      ref="heroRef"
      class="hero-3d relative min-h-[85vh] md:min-h-screen overflow-hidden bg-cover bg-center bg-no-repeat pb-4 md:pb-0"
      :style="{
        backgroundImage: `url(${welcomeImage})`,
        transform: `scale(${1 + Math.abs(mousePosition.x) * 0.02}) translate(${mousePosition.x * 20}px, ${mousePosition.y * 20}px)`,
      }"
    >
      <div
        class="absolute inset-0"
        :style="{
          background: `radial-gradient(circle at ${50 + mousePosition.x * 20}% ${50 + mousePosition.y * 20}%, rgba(252,147,3,0.15) 0%, rgba(0,0,0,0.85) 100%)`,
        }"
      ></div>
      <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div
          v-for="i in 25"
          :key="i"
          class="absolute rounded-full bg-[#fc9303] opacity-20"
          :style="{
            width: `${Math.random() * 3 + 1}px`,
            height: `${Math.random() * 3 + 1}px`,
            left: `${Math.random() * 100}%`,
            top: `${Math.random() * 100}%`,
            animation: `float ${Math.random() * 8 + 4}s linear infinite`,
            animationDelay: `${Math.random() * 5}s`,
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
            <router-link to="/services">
              <button
                class="group w-full sm:w-auto px-4 sm:px-6 md:px-10 py-2 sm:py-3 md:py-4 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-white font-semibold text-sm sm:text-base md:text-lg rounded-full transition-all duration-300 hover:scale-105 hover:shadow-xl hover:shadow-orange-500/30"
              >
                <span class="flex items-center justify-center gap-2">
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
                </span>
              </button>
            </router-link>
            <router-link to="/portfolio">
              <button
                class="group w-full sm:w-auto px-4 sm:px-6 md:px-10 py-2 sm:py-3 md:py-4 border border-[#fc9303] text-white font-semibold text-sm sm:text-base md:text-lg rounded-full hover:bg-[#fc9303] transition-all duration-300"
              >
                <span class="flex items-center justify-center gap-2">
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
                </span>
              </button>
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

    <!-- УСЛУГИ (динамические из БД) -->
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
          <router-link to="/services" @click="scrollToTop">
            <button
              class="group px-5 sm:px-6 md:px-8 py-2 sm:py-2.5 md:py-3 border border-[#fc9303] text-white text-sm sm:text-base rounded-full hover:bg-[#fc9303] transition-all duration-300"
            >
              <span class="flex items-center gap-2">
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
              </span>
            </button>
          </router-link>
        </div>
      </div>
    </section>

    <!-- РАЗДЕЛИТЕЛЬ МЕЖДУ СЕКЦИЯМИ (добавлен) -->
    <div class="relative w-full">
      <div
        class="absolute left-0 right-0 h-px bg-gradient-to-r from-transparent via-[#fc9303] to-transparent max-w-4xl mx-auto"
      ></div>
      <div class="py-8 md:py-12"></div>
    </div>

    <!-- О СТУДИИ (обновлённая версия) -->
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
          <!-- Видео блок -->
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
                class="w-full h-full object-cover"
              >
                <source
                  :src="aboutVideoUrl || aboutVideoDefault"
                  type="video/mp4"
                />
              </video>
            </div>
          </div>

          <!-- Текстовый блок с выравниванием по ширине -->
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
                  Контроль на каждом этапе и гарантия до 3 лет на все виды
                  работ.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ПОРТФОЛИО (без изменений) -->
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

        <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
          <div
            v-for="item in portfolioItems"
            :key="item.id"
            class="group relative aspect-[3/4] rounded-xl overflow-hidden bg-gray-800 hover:border hover:border-[#fc9303] transition-all duration-300"
          >
            <video
              autoplay
              muted
              loop
              playsinline
              class="w-full h-full object-cover"
            >
              <source :src="item.video_url" type="video/mp4" />
            </video>
            <div
              class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"
            ></div>
          </div>
        </div>

        <div class="flex justify-center mt-8 sm:mt-12 md:mt-16">
          <router-link to="/portfolio" @click="scrollToTop">
            <button
              class="group px-5 sm:px-6 md:px-8 py-2 sm:py-2.5 md:py-3 border border-[#fc9303] text-white text-sm sm:text-base rounded-full hover:bg-[#fc9303] transition-all duration-300"
            >
              <span class="flex items-center gap-2">
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
              </span>
            </button>
          </router-link>
        </div>
      </div>
    </section>

    <!-- ФОРМА ОБРАТНОЙ СВЯЗИ -->
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
                  <label class="block text-sm text-gray-400 mb-2"
                    >Ваше имя *</label
                  >
                  <input
                    v-model="feedbackForm.name"
                    type="text"
                    required
                    class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] transition-colors"
                    placeholder="Иван Иванов"
                  />
                </div>
                <div>
                  <label class="block text-sm text-gray-400 mb-2"
                    >Номер телефона *</label
                  >
                  <input
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
                <label class="block text-sm text-gray-400 mb-2">Электронная почта</label>
                <input
                  v-model="feedbackForm.email"
                  type="email"
                  class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-[#fc9303] transition-colors"
                  placeholder="mail@example.com"
                />
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-2">Сообщение</label>
                <textarea
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
                  <a href="#" class="text-[#fc9303] hover:underline"
                    >обработку персональных данных</a
                  >
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

    <!-- Модал уведомлений формы обратной связи -->
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

    <!-- CTA -->
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
          <router-link to="/booking">
            <button
              class="px-6 sm:px-8 md:px-12 py-2 sm:py-3 md:py-4 lg:py-5 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-white font-bold text-sm sm:text-base md:text-lg rounded-full hover:scale-105 transition-all duration-300 shadow-lg shadow-orange-500/30"
            >
              Записаться
            </button>
          </router-link>
          <router-link to="/contacts">
            <button
              class="px-6 sm:px-8 md:px-12 py-2 sm:py-3 md:py-4 lg:py-5 border-2 border-white/30 text-white font-bold text-sm sm:text-base md:text-lg rounded-full hover:border-[#fc9303] hover:bg-[#fc9303]/10 transition-all duration-300"
            >
              Связаться
            </button>
          </router-link>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
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
