<script setup>
import { onMounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import AdminDashboard from './admin/AdminDashboard.vue'
import AdminServices from './admin/AdminServices.vue'
import AdminPortfolio from './admin/AdminPortfolio.vue'
import AdminClients from './admin/AdminClients.vue'
import AdminOrders from './admin/AdminOrders.vue'
import AdminSettings from './admin/AdminSettings.vue'
import AdminFeedbacks from './admin/AdminFeedbacks.vue'

const authStore = useAuthStore()
const router = useRouter()
const STORAGE_KEY = 'admin_active_tab'
const validTabs = ['dashboard', 'services', 'portfolio', 'orders', 'clients', 'feedbacks', 'settings']
const savedTab = localStorage.getItem(STORAGE_KEY)
const activeAdminTab = ref(validTabs.includes(savedTab) ? savedTab : 'services')
watch(activeAdminTab, (val) => localStorage.setItem(STORAGE_KEY, val))
const sidebarCollapsed = ref(false)
const mobileMenuOpen = ref(false)
const isMobile = ref(false)

const adminTabs = [
  { id: 'dashboard', name: 'Дашборд', icon: '📊' },
  { id: 'services', name: 'Услуги', icon: '🛠️' },
  { id: 'portfolio', name: 'Портфолио', icon: '🎬' },
  { id: 'orders', name: 'Заказы', icon: '📋' },
  { id: 'clients', name: 'Клиенты', icon: '👥' },
  { id: 'feedbacks', name: 'Обратная связь', icon: '💬' },
  { id: 'settings', name: 'Настройки', icon: '⚙️' }
]

// Определяем мобильное устройство
const checkMobile = () => {
  isMobile.value = window.innerWidth < 768
  // При переходе с мобильного на десктоп закрываем мобильное меню
  if (!isMobile.value) {
    mobileMenuOpen.value = false
  }
}

// Закрыть мобильное меню
const closeMobileMenu = () => {
  if (isMobile.value) {
    mobileMenuOpen.value = false
  }
}

// Блокировка скролла при открытом мобильном меню
watch(mobileMenuOpen, (val) => {
  if (isMobile.value && val) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
  }
})

onMounted(async () => {
  if (!authStore.isAuthenticated) {
    router.push('/login')
  }
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

const handleLogout = async () => {
  await authStore.logout()
  router.push('/')
}
</script>

<template>
  <div class="profile-page bg-black text-white min-h-screen">
    <!-- Шапка для мобильных устройств (бургер) -->
    <div v-if="isMobile" class="md:hidden sticky top-0 z-30 bg-black/80 backdrop-blur-md px-4 py-3 flex items-center justify-between border-b border-gray-800">
      <h2 class="text-lg font-bold text-[#fc9303]">AKITA CONTROL</h2>
      <button @click="mobileMenuOpen = true" class="text-white focus:outline-none">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
    </div>

    <!-- Оверлей для мобильного меню -->
    <Transition name="fade">
      <div v-if="isMobile && mobileMenuOpen" class="fixed inset-0 bg-black/70 z-40" @click="mobileMenuOpen = false"></div>
    </Transition>

    <!-- Боковое меню (для десктопа – статическое слева, для мобильных – выезжающее) -->
    <aside 
      class="fixed top-0 left-0 h-full bg-gray-900 border-r border-gray-800 transition-all duration-300 z-50 flex flex-col shadow-xl"
      :class="[
        isMobile 
          ? (mobileMenuOpen ? 'translate-x-0 w-64' : '-translate-x-full w-64')
          : (sidebarCollapsed ? 'w-20' : 'w-64')
      ]"
    >
      <!-- Шапка меню -->
      <div class="flex items-center justify-between p-4 border-b border-gray-800">
        <div v-if="!isMobile && !sidebarCollapsed" class="flex items-center gap-2">
          <span class="text-xl font-bold text-[#fc9303]">AKITA</span>
          <span class="text-sm text-gray-400">CONTROL</span>
        </div>
        <div v-if="!isMobile && sidebarCollapsed" class="w-full flex justify-center">
          <span class="text-xl font-bold text-[#fc9303]">A</span>
        </div>
        <div v-if="isMobile && mobileMenuOpen" class="flex items-center gap-2">
          <span class="text-xl font-bold text-[#fc9303]">AKITA</span>
          <span class="text-sm text-gray-400">CONTROL</span>
        </div>
        <button 
          v-if="!isMobile"
          @click="sidebarCollapsed = !sidebarCollapsed" 
          class="text-gray-400 hover:text-white transition-colors"
          :class="isMobile ? 'ml-auto' : ''"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path v-if="!sidebarCollapsed" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7m-8-14l7 7-7 7" />
          </svg>
        </button>
        <button v-if="isMobile && mobileMenuOpen" @click="mobileMenuOpen = false" class="text-gray-400 hover:text-white">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Навигация -->
      <nav class="flex-1 overflow-y-auto py-4">
        <div class="space-y-1 px-2">
          <button
            v-for="tab in adminTabs"
            :key="tab.id"
            @click="activeAdminTab = tab.id; closeMobileMenu()"
            class="w-full text-left px-3 py-3 rounded-lg transition-all duration-200 flex items-center gap-3 hover:translate-x-1"
            :class="[
              activeAdminTab === tab.id 
                ? 'bg-[#fc9303]/20 text-[#fc9303] border-l-2 border-[#fc9303]' 
                : 'text-gray-400 hover:bg-gray-800 hover:text-white',
              (!isMobile && sidebarCollapsed) ? 'justify-center' : ''
            ]"
          >
            <span class="text-xl">{{ tab.icon }}</span>
            <span v-if="(!isMobile && !sidebarCollapsed) || (isMobile && mobileMenuOpen)" class="text-sm">{{ tab.name }}</span>
          </button>
        </div>
      </nav>

      <!-- Кнопка выхода -->
      <div class="p-4 border-t border-gray-800">
        <button 
          @click="handleLogout" 
          class="w-full flex items-center gap-2 text-sm text-gray-500 hover:text-white transition-colors"
          :class="(!isMobile && sidebarCollapsed) ? 'justify-center' : ''"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
          <span v-if="(!isMobile && !sidebarCollapsed) || (isMobile && mobileMenuOpen)">Выйти</span>
        </button>
      </div>
    </aside>

    <!-- Основной контент -->
    <main 
      class="transition-all duration-300 min-h-screen"
      :class="[
        isMobile ? 'ml-0' : (sidebarCollapsed ? 'ml-20' : 'ml-64')
      ]"
    >
      <div class="p-4 md:p-8">
        <div v-if="activeAdminTab === 'dashboard'"><AdminDashboard /></div>
        <div v-if="activeAdminTab === 'services'"><AdminServices /></div>
        <div v-if="activeAdminTab === 'portfolio'"><AdminPortfolio /></div>
        <div v-if="activeAdminTab === 'clients'"><AdminClients /></div>
        <div v-if="activeAdminTab === 'orders'"><AdminOrders /></div>
        <div v-if="activeAdminTab === 'feedbacks'"><AdminFeedbacks /></div>
        <div v-if="activeAdminTab === 'settings'"><AdminSettings /></div>
      </div>
    </main>
  </div>
</template>

<style scoped>
/* Анимации для оверлея */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Кастомный скролл для меню */
aside .overflow-y-auto::-webkit-scrollbar {
  width: 4px;
}
aside .overflow-y-auto::-webkit-scrollbar-track {
  background: #1f2937;
}
aside .overflow-y-auto::-webkit-scrollbar-thumb {
  background: #fc9303;
  border-radius: 4px;
}
</style>