import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior: () => ({ top: 0 }),
  routes: [
    { path: '/', name: 'home', component: HomeView },
    { path: '/services', name: 'services', component: () => import('../views/ServicesView.vue') },
    { path: '/portfolio', name: 'portfolio', component: () => import('../views/PortfolioView.vue') },
    { path: '/about', name: 'about', component: () => import('../views/AboutView.vue') },
    { path: '/contacts', name: 'contacts', component: () => import('../views/ContactsView.vue') },
    { path: '/booking', name: 'booking', component: () => import('../views/BookingView.vue') },
    // Аутентификация
    { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { guest: true } },
    { path: '/register', name: 'register', component: () => import('../views/RegisterView.vue'), meta: { guest: true } },
    
    // Личный кабинет клиента
    { path: '/profile', name: 'profile', component: () => import('../views/ClientProfileView.vue'), meta: { requiresAuth: true, role: 'client' } },
    
    // Админ-панель (оставляем как было)
    { path: '/admin/profile', name: 'admin-profile', component: () => import('../views/ProfileView.vue'), meta: { requiresAuth: true, role: 'admin' } },

    // Несуществующие страницы → главная
    { path: '/:pathMatch(.*)*', redirect: '/' }
  ]
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  if (!authStore.isAuthenticated && authStore.user === null) {
    await authStore.checkAuth()
  }

  // Гостевые маршруты
  if (to.meta.guest && authStore.isAuthenticated) {
    if (authStore.user?.role === 'admin') {
      next('/admin/profile')
    } else {
      next('/profile')
    }
    return
  }

  // Требуется авторизация
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next('/login')
    return
  }

  // Проверка роли
  if (to.meta.requiresAuth && to.meta.role) {
    const userRole = authStore.user?.role
    if (userRole !== to.meta.role) {
      if (userRole === 'admin') {
        next('/admin/profile')
      } else if (userRole === 'client') {
        next('/profile')
      } else {
        next('/')
      }
      return
    }
  }

  next()
})

export default router