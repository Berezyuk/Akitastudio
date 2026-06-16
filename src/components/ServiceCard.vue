<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  imageUrl: {
    type: String,
    required: true,
  },
  title: {
    type: String,
    required: true,
  },
  items: {
    type: Array,
    required: true,
    default: () => [],
  },
})

const isVideo = computed(() => /\.(mp4|webm|ogg)(\?|$)/i.test(props.imageUrl))

// Определяем мобильное устройство (ширина < 768px)
const isMobile = ref(false)
const isOpen = ref(false)

const checkMobile = () => {
  isMobile.value = window.innerWidth < 768
}

const toggleContent = () => {
  if (isMobile.value) {
    isOpen.value = !isOpen.value
  }
}

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})
</script>

<template>
  <div 
    class="services_card" 
    :class="{ active: isOpen }"
    @click="toggleContent"
  >
    <video v-if="isVideo" :src="imageUrl" class="services_img" autoplay muted loop playsinline></video>
    <img v-else :src="imageUrl" :alt="title" class="services_img" />
    <h4 class="services_title">{{ title }}</h4>
    <div class="card_hover-content" :class="{ show: isOpen }">
      <div class="services_list_wrapper">
        <ul class="services_list">
          <li v-for="(item, index) in items" :key="index">
            {{ item }}
          </li>
          <li v-if="!items.length" class="text-gray-400">Нет услуг</li>
        </ul>
      </div>
    </div>
    <!-- Подсказка для мобильных устройств -->
    <div v-if="isMobile && !isOpen" class="mobile-tap-indicator">
      <span>👆</span>
      <span class="tap-text">Нажмите</span>
    </div>
  </div>
</template>

<style scoped>
/* Услуги */
.services .services-content {
  margin-top: 50px;
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 40px 10px;
}

.services_card {
  width: 100%;
  max-width: 260px;
  margin: 0 auto;
  text-align: center;
  background-color: #4d4d4d;
  overflow: hidden;
  border-radius: 10px;
  position: relative;
  cursor: pointer;
  transition: all 0.4s ease;
}

.services_card:hover {
  border: 1px solid #fc9303;
  transform: translateY(-5px);
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
}

.services_img {
  object-fit: cover;
  height: 100%;
  width: 100%;
  transition: all 0.5s ease;
}

.services_card:hover .services_img {
  height: 100%;
  width: 100%;
  filter: brightness(0.6);
  transform: scale(1.05);
}

.services_title {
  text-align: center;
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

.services_card:hover .services_title {
  top: 20px;
  bottom: auto;
  background: none;
  text-decoration: underline;
  text-decoration-color: #fc9303;
  text-underline-offset: 5px;
  white-space: normal;
  word-break: break-word;
}

.card_hover-content {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  opacity: 0;
  visibility: hidden;
  transition: all 0.4s ease;
  padding: 90px 20px 20px; /* Увеличен верхний отступ для большего расстояния между заголовком и списком */
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  z-index: 1;
}

.services_card:hover .card_hover-content {
  opacity: 1;
  visibility: visible;
}

.services_list_wrapper {
  flex: 1;
  overflow-y: auto;
  max-height: calc(100% - 20px);
  scrollbar-width: thin;
  scrollbar-color: #fc9303 #333;
}

.services_list_wrapper::-webkit-scrollbar {
  width: 4px;
}

.services_list_wrapper::-webkit-scrollbar-track {
  background: #333;
  border-radius: 4px;
}

.services_list_wrapper::-webkit-scrollbar-thumb {
  background: #fc9303;
  border-radius: 4px;
}

.services_list {
  list-style: none;
  padding: 0;
  margin: 0;
  color: white;
  text-align: left;
}

.services_list li {
  padding: 8px 0;
  font-size: 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  position: relative;
  padding-left: 20px;
}

.services_list li:before {
  content: "•";
  color: #fc9303;
  font-size: 20px;
  position: absolute;
  left: 0;
  top: 6px;
}

.services_list li:last-child {
  border-bottom: none;
}

/* Адаптация для мобильных */
@media (max-width: 640px) {
  .services_card {
    max-width: 100%;
  }
  .services_list li {
    font-size: 14px;
    padding: 6px 0 6px 20px;
  }
  .card_hover-content {
    padding: 75px 15px 15px; /* На мобильных тоже увеличиваем верхний отступ */
  }
  .services_list_wrapper {
    max-height: calc(100% - 30px);
  }
}

/* Дополнительные стили для мобильных устройств (ширина < 768px) */
@media (max-width: 768px) {
  .services_card.active .card_hover-content {
    opacity: 1;
    visibility: visible;
  }
  
  .mobile-tap-indicator {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    border-radius: 30px;
    padding: 6px 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #fc9303;
    z-index: 3;
    pointer-events: none;
    border: 1px solid rgba(252, 147, 3, 0.3);
  }
  
  .tap-text {
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.5px;
  }
}

/* На десктопе скрываем индикатор */
@media (min-width: 769px) {
  .mobile-tap-indicator {
    display: none;
  }
}
</style>