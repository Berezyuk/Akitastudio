<script setup>
import { useAuthStore } from '@/stores/auth'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

const isAuthenticated = computed(() => authStore.isAuthenticated)
const userName = computed(() => authStore.user?.name || 'Профиль')

const logout = async () => {
    await authStore.logout()
    router.push('/')
}

const mobileMenuOpen = ref(false)
const closeMenu = () => {
    mobileMenuOpen.value = false
}
</script>

<template>
  <header class="header sticky top-0 z-50 bg-black/80 backdrop-blur-md">
    <div class="container mx-auto flex justify-between items-center px-4 py-4 md:py-4">
      <!-- Логотип -->
      <router-link to="/" class="flex-shrink-0">
        <img src="../assets/Images/Logo.svg" alt="logo" class="h-8 md:h-auto" />
      </router-link>
      
      <!-- Десктопная навигация (убраны лишние пункты, "Компания" заменён на прямой "О компании") -->
      <nav class="hidden md:block">
        <ul class="flex gap-5 lg:gap-[30px] items-center">
          <li><router-link to="/" class="text-sm" :class="[$route.path === '/' ? 'opacity-100 text-[#fc9303]' : 'opacity-70 hover:opacity-90']">Главная</router-link></li>
          <li><router-link to="/services" class="text-sm" :class="[$route.path === '/services' ? 'opacity-100 text-[#fc9303]' : 'opacity-70 hover:opacity-90']">Услуги</router-link></li>
          <li><router-link to="/portfolio" class="text-sm" :class="[$route.path === '/portfolio' ? 'opacity-100 text-[#fc9303]' : 'opacity-70 hover:opacity-90']">Портфолио</router-link></li>
          <li><router-link to="/about" class="text-sm" :class="[$route.path === '/about' ? 'opacity-100 text-[#fc9303]' : 'opacity-70 hover:opacity-90']">О компании</router-link></li>
          <li><router-link to="/contacts" class="text-sm" :class="[$route.path === '/contacts' ? 'opacity-100 text-[#fc9303]' : 'opacity-70 hover:opacity-90']">Контакты</router-link></li>
        </ul>
      </nav>
      
      <div class="flex items-center gap-2 md:gap-4">
        <!-- Кнопка "Войти" (десктоп) -->
        <router-link v-if="!isAuthenticated" to="/login" class="hidden md:flex items-center gap-2 text-sm text-gray-300 hover:text-white group/login">
          <svg class="w-5 h-5 text-[#fc9303] group-hover/login:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
          <span class="opacity-80 group-hover/login:opacity-100">Войти</span>
        </router-link>
        
        <!-- Профиль (десктоп) -->
        <div v-else class="hidden md:block relative group">
          <router-link to="/profile" class="flex items-center gap-2 text-sm text-[#fc9303] hover:text-[#ff6b00]">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            <span>{{ userName }}</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </router-link>
          <div class="absolute right-0 top-full mt-2 w-48 backdrop-blur-xl bg-black/80 border border-white/10 rounded-xl shadow-2xl py-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50">
            <router-link to="/profile" class="block px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-[#fc9303]/10">Личный кабинет</router-link>
            <button @click="logout" class="block w-full text-left px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-[#fc9303]/10">Выйти</button>
          </div>
        </div>
        
        <!-- Кнопка "Записаться" (адаптивная) -->
        <router-link to="/booking" class="btn flex items-center justify-center text-xs sm:text-sm px-3 sm:px-4 py-1.5 sm:py-2 bg-black border border-white rounded-[5px] hover:bg-[#fc9303] hover:border-[#fc9303] text-white whitespace-nowrap transition-all">
          Записаться
        </router-link>

        <!-- Бургер-меню (только мобильные) -->
        <button @click="mobileMenuOpen = true" class="block md:hidden text-white focus:outline-none">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
          </svg>
        </button>
      </div>
    </div>

    <!-- Оверлей (затемнение) -->
    <Transition name="fade">
      <div v-if="mobileMenuOpen" class="fixed inset-0 z-[9998] bg-black/70" @click="closeMenu"></div>
    </Transition>

    <!-- Мобильное меню – полная ширина экрана -->
    <Transition name="slide">
      <div 
        v-if="mobileMenuOpen" 
        class="mobile-menu-panel fixed inset-y-0 right-0 w-full z-[9999] shadow-2xl flex flex-col p-6"
        style="background-color: #000000 !important; background: #000000 !important; color: white !important;"
      >
        <button @click="closeMenu" class="self-end text-white mb-6" style="color: white !important;">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
        
        <nav class="flex flex-col gap-4">
          <router-link to="/" class="text-lg" style="color: white !important;" @click="closeMenu">Главная</router-link>
          <router-link to="/services" class="text-lg" style="color: white !important;" @click="closeMenu">Услуги</router-link>
          <router-link to="/portfolio" class="text-lg" style="color: white !important;" @click="closeMenu">Портфолио</router-link>
          <router-link to="/about" class="text-lg" style="color: white !important;" @click="closeMenu">О компании</router-link>
          <router-link to="/contacts" class="text-lg" style="color: white !important;" @click="closeMenu">Контакты</router-link>
        </nav>
        
        <div class="mt-auto pt-6 border-t border-white/20">
          <router-link v-if="!isAuthenticated" to="/login" class="block text-center py-2 border border-white/30 rounded-full" style="color: white !important;" @click="closeMenu">
            Войти
          </router-link>
          <div v-else class="text-center">
            <router-link to="/profile" class="block py-2" style="color: white !important;" @click="closeMenu">Личный кабинет</router-link>
            <button @click="logout" class="block py-2 text-red-400 w-full">Выйти</button>
          </div>
        </div>
      </div>
    </Transition>
  </header>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
  transition: transform 0.3s ease;
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(100%);
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Для экранов от 750px до 900px увеличиваем расстояние между пунктами меню */
@media (min-width: 750px) and (max-width: 900px) {
  .header nav ul {
    gap: 1.5rem !important;
  }
}
</style>