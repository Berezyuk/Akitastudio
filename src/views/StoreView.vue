<script setup>
import { ref, computed } from 'vue'
import AlertModal from '@/components/admin/AlertModal.vue'

// ============================================
// ДАННЫЕ ТОВАРОВ
// ============================================

// Шумоизоляция ComfortMat
const soundproofing = ref([
  {
    id: 1,
    name: 'ComfortMat Silver',
    category: 'soundproofing',
    brand: 'ComfortMat',
    description: 'Алюминиевая шумоизоляция, толщина 2.5 мм. Для дверей, крыши, багажника.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=Silver',
    price: 1200,
    unit: 'лист',
    features: ['Алюминиевый слой', '2.5 мм', 'Вибропоглощение'],
    popular: true
  },
  {
    id: 2,
    name: 'ComfortMat Gold',
    category: 'soundproofing',
    brand: 'ComfortMat',
    description: 'Битумная шумоизоляция премиум-класса, толщина 3.5 мм. Для пола, арок.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=Gold',
    price: 1800,
    unit: 'лист',
    features: ['Битумная основа', '3.5 мм', 'Максимальная защита'],
    popular: true
  },
  {
    id: 3,
    name: 'ComfortMat Black',
    category: 'soundproofing',
    brand: 'ComfortMat',
    description: 'Без алюминиевого слоя, для салона и сложных поверхностей.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=Black',
    price: 900,
    unit: 'лист',
    features: ['Без алюминия', 'Гибкий', 'Для салона'],
    popular: false
  },
  {
    id: 4,
    name: 'Комплект ComfortMat',
    category: 'soundproofing',
    brand: 'ComfortMat',
    description: 'Полный комплект для шумоизоляции легкового автомобиля. 12 листов.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=Kit',
    price: 12500,
    unit: 'комплект',
    features: ['12 листов', 'Разные толщины', 'Инструкция'],
    popular: true
  }
])

// Краска
const paints = ref([
  {
    id: 5,
    name: 'Акриловая автоэмаль',
    category: 'paint',
    brand: 'Akita',
    description: 'Высококачественная акриловая эмаль. Подбор цвета по VIN или образцу.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=Acrylic',
    price: 1200,
    unit: 'банка',
    features: ['Подбор цвета', 'Высокая стойкость', 'Глянец'],
    popular: true
  },
  {
    id: 6,
    name: 'Аэрозольная краска',
    category: 'paint',
    brand: 'Akita',
    description: 'Для локального ремонта сколов и царапин. Удобный формат.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=Spray',
    price: 450,
    unit: 'баллон',
    features: ['Удобный формат', 'Для сколов', 'Быстрое высыхание'],
    popular: false
  },
  {
    id: 7,
    name: 'Автомобильный лак',
    category: 'paint',
    brand: 'Akita',
    description: 'Прозрачный лак для финишного покрытия. Защита от УФ.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=ClearCoat',
    price: 850,
    unit: 'банка',
    features: ['Прозрачный', 'Защита от УФ', 'Глубокий блеск'],
    popular: false
  }
])

// Ароматизаторы Chemical Guys
const airFresheners = ref([
  {
    id: 8,
    name: 'Leather',
    category: 'freshener',
    brand: 'Chemical Guys',
    description: 'Аромат натуральной кожи. Элегантный, благородный.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=Leather',
    price: 890,
    unit: 'шт',
    features: ['Натуральная кожа', 'Длительный эффект', 'Премиум'],
    popular: true
  },
  {
    id: 9,
    name: 'New Car',
    category: 'freshener',
    brand: 'Chemical Guys',
    description: 'Классический аромат нового автомобиля.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=NewCar',
    price: 890,
    unit: 'шт',
    features: ['Новый авто', 'Классика', 'Стойкий'],
    popular: true
  },
  {
    id: 10,
    name: 'Vanilla',
    category: 'freshener',
    brand: 'Chemical Guys',
    description: 'Сладкий аромат ванили. Уютный, теплый.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=Vanilla',
    price: 890,
    unit: 'шт',
    features: ['Ваниль', 'Сладкий', 'Уютный'],
    popular: false
  },
  {
    id: 11,
    name: 'Комплект 3 аромата',
    category: 'freshener',
    brand: 'Chemical Guys',
    description: 'Набор из трех лучших ароматов: Leather, New Car, Vanilla.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=Set3',
    price: 2490,
    unit: 'комплект',
    features: ['3 аромата', 'Экономия', 'Подарочная упаковка'],
    popular: true
  }
])

// Наборы Letech
const letechKits = ref([
  {
    id: 12,
    name: 'Набор для кожи',
    category: 'letech',
    brand: 'Letech',
    description: 'Очиститель + кондиционер для ухода за кожаным салоном.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=LeatherKit',
    price: 2500,
    unit: 'набор',
    features: ['Очиститель', 'Кондиционер', 'Для кожи'],
    popular: true
  },
  {
    id: 13,
    name: 'Набор для салона',
    category: 'letech',
    brand: 'Letech',
    description: 'Шампунь для салона + протектор пластика.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=InteriorKit',
    price: 2200,
    unit: 'набор',
    features: ['Шампунь', 'Протектор', 'Для пластика'],
    popular: false
  },
  {
    id: 14,
    name: 'Premium Kit',
    category: 'letech',
    brand: 'Letech',
    description: 'Полный набор для всего салона: кожа, пластик, стекла.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=PremiumKit',
    price: 4900,
    unit: 'набор',
    features: ['4 в 1', 'Для всех поверхностей', 'Подарочная коробка'],
    popular: true
  }
])

// Аксессуары
const accessories = ref([
  {
    id: 15,
    name: 'Микрофибра 40x40',
    category: 'accessory',
    brand: 'Akita',
    description: 'Высококачественная микрофибра для полировки и ухода.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=Microfiber',
    price: 150,
    unit: 'шт',
    features: ['400 GSM', 'Для полировки', 'Многоразовая'],
    popular: true
  },
  {
    id: 16,
    name: 'Аппликатор для нанесения',
    category: 'accessory',
    brand: 'Akita',
    description: 'Мягкий аппликатор для равномерного нанесения средств.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=Applicator',
    price: 120,
    unit: 'шт',
    features: ['Мягкий', 'Равномерное нанесение', 'Многоразовый'],
    popular: false
  },
  {
    id: 17,
    name: 'Ведро с грязеотделителем',
    category: 'accessory',
    brand: 'Akita',
    description: 'Профессиональное ведро для бесконтактной мойки.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=Bucket',
    price: 1800,
    unit: 'шт',
    features: ['Грязеотделитель', '20 литров', 'Профессиональное'],
    popular: false
  }
])

// Подарочные наборы
const giftSets = ref([
  {
    id: 18,
    name: 'Starter Pack',
    category: 'gift',
    brand: 'Akita',
    description: 'Набор для начинающего автолюбителя: ароматизатор + микрофибра + аппликатор.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=StarterPack',
    price: 1990,
    unit: 'набор',
    features: ['Ароматизатор', 'Микрофибра', 'Аппликатор'],
    popular: true
  },
  {
    id: 19,
    name: 'Car Care Kit',
    category: 'gift',
    brand: 'Akita',
    description: 'Набор для ухода за салоном: Letech + микрофибра + аппликатор.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=CarCareKit',
    price: 3990,
    unit: 'набор',
    features: ['Набор Letech', 'Микрофибра', 'Аппликатор'],
    popular: true
  },
  {
    id: 20,
    name: 'Premium Gift',
    category: 'gift',
    brand: 'Akita',
    description: 'Премиальный подарочный набор в фирменной коробке. Всё для идеального авто.',
    image: 'https://placehold.co/400x400/1a1a1a/fc9303?text=PremiumGift',
    price: 7990,
    unit: 'набор',
    features: ['Подарочная коробка', 'Набор Letech', 'Шампунь', 'Керамика'],
    popular: true
  }
])

// Корзина
const cart = ref([])
const showCart = ref(false)
const cartTotal = computed(() => {
  return cart.value.reduce((sum, item) => sum + (item.price * item.quantity), 0)
})

const cartCount = computed(() => {
  return cart.value.reduce((sum, item) => sum + item.quantity, 0)
})

// Калькулятор шумоизоляции
const showCalculator = ref(false)
const carBrand = ref('')
const carModel = ref('')
const selectedZones = ref({
  doors: true,
  floor: true,
  arches: true,
  roof: false,
  trunk: false
})

const calculateSoundproofing = () => {
  let sheets = 0
  if (selectedZones.value.doors) sheets += 8
  if (selectedZones.value.floor) sheets += 6
  if (selectedZones.value.arches) sheets += 4
  if (selectedZones.value.roof) sheets += 3
  if (selectedZones.value.trunk) sheets += 4
  
  return {
    sheets,
    price: sheets * 1800,
    material: 'ComfortMat Gold'
  }
}

const addToCart = (item) => {
  const existing = cart.value.find(i => i.id === item.id)
  if (existing) {
    existing.quantity++
  } else {
    cart.value.push({ ...item, quantity: 1 })
  }
}

const removeFromCart = (itemId) => {
  const index = cart.value.findIndex(i => i.id === itemId)
  if (index !== -1) {
    cart.value.splice(index, 1)
  }
}

const updateQuantity = (itemId, delta) => {
  const item = cart.value.find(i => i.id === itemId)
  if (item) {
    item.quantity += delta
    if (item.quantity <= 0) {
      removeFromCart(itemId)
    }
  }
}

const alertModal = ref({ show: false, title: '', message: '' })
const showAlert = (title, message = '') => { alertModal.value = { show: true, title, message } }

const checkout = () => {
  showAlert('Оформление заказа', 'Функция в разработке. Свяжитесь с нами по телефону для оформления заказа.', )
}

// Активная категория
const activeCategory = ref('all')

const categories = [
  { id: 'all', name: 'Все товары', icon: '📦' },
  { id: 'soundproofing', name: 'Шумоизоляция', icon: '🔊' },
  { id: 'paint', name: 'Краска', icon: '🎨' },
  { id: 'freshener', name: 'Ароматизаторы', icon: '🍃' },
  { id: 'letech', name: 'Наборы Letech', icon: '🧴' },
  { id: 'accessory', name: 'Аксессуары', icon: '🧽' },
  { id: 'gift', name: 'Подарочные наборы', icon: '🎁' }
]

const filteredProducts = computed(() => {
  if (activeCategory.value === 'all') {
    return [
      ...soundproofing.value,
      ...paints.value,
      ...airFresheners.value,
      ...letechKits.value,
      ...accessories.value,
      ...giftSets.value
    ]
  }
  
  switch (activeCategory.value) {
    case 'soundproofing': return soundproofing.value
    case 'paint': return paints.value
    case 'freshener': return airFresheners.value
    case 'letech': return letechKits.value
    case 'accessory': return accessories.value
    case 'gift': return giftSets.value
    default: return []
  }
})
</script>

<template>
  <div class="store-page bg-black text-white min-h-screen">
    
    <!-- Hero секция -->
    <section class="relative pt-32 pb-20 overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-b from-black via-black to-[#4d4d4d]/20"></div>
      <div class="absolute inset-0 opacity-30">
        <div class="absolute top-20 left-10 w-64 h-64 bg-[#fc9303] rounded-full filter blur-[100px]"></div>
        <div class="absolute bottom-20 right-10 w-96 h-96 bg-[#ff6b00] rounded-full filter blur-[150px]"></div>
      </div>
      
      <div class="site-container mx-auto px-4 relative z-10 text-center">
        <h1 class="text-5xl md:text-7xl font-bold mb-6">
          <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">
            AKITA STORE
          </span>
        </h1>
        <p class="text-xl text-gray-400 max-w-2xl mx-auto">
          Всё для ухода, защиты и ремонта вашего автомобиля. Оригинальная продукция от ведущих брендов.
        </p>
      </div>
    </section>

    <!-- Категории -->
    <div class="sticky top-[106px] z-40 bg-black/80 backdrop-blur-xl border-b border-gray-800">
      <div class="site-container mx-auto px-4">
        <div class="flex gap-4 overflow-x-auto py-4 scrollbar-hide">
          <button
            v-for="cat in categories"
            :key="cat.id"
            @click="activeCategory = cat.id"
            class="px-6 py-3 rounded-full text-sm font-medium transition-all duration-300 whitespace-nowrap"
            :class="[
              activeCategory === cat.id
                ? 'bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-white shadow-lg shadow-orange-500/30'
                : 'bg-gray-900 text-gray-400 hover:text-white border border-gray-800'
            ]"
          >
            {{ cat.icon }} {{ cat.name }}
          </button>
        </div>
      </div>
    </div>

    <!-- Каталог -->
    <section class="py-16">
      <div class="site-container mx-auto px-4">
        
        <!-- Калькулятор шумоизоляции -->
        <div v-if="activeCategory === 'soundproofing'" class="mb-12">
          <button
            @click="showCalculator = !showCalculator"
            class="flex items-center gap-2 text-[#fc9303] hover:text-[#ff6b00] transition-colors"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
            </svg>
            <span>{{ showCalculator ? 'Скрыть калькулятор' : 'Рассчитать шумоизоляцию' }}</span>
          </button>
          
          <div v-if="showCalculator" class="mt-6 bg-gray-900/50 backdrop-blur-sm rounded-2xl border border-gray-800 p-6">
            <h3 class="text-xl font-bold mb-4">Калькулятор шумоизоляции</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm text-gray-400 mb-2">Марка автомобиля</label>
                <input v-model="carBrand" type="text" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-2">Модель</label>
                <input v-model="carModel" type="text" class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-white">
              </div>
            </div>
            
            <div class="mt-6">
              <p class="text-sm text-gray-400 mb-3">Зоны шумоизоляции:</p>
              <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <label class="flex items-center gap-2 text-sm">
                  <input type="checkbox" v-model="selectedZones.doors" class="accent-[#fc9303]">
                  Двери
                </label>
                <label class="flex items-center gap-2 text-sm">
                  <input type="checkbox" v-model="selectedZones.floor" class="accent-[#fc9303]">
                  Пол
                </label>
                <label class="flex items-center gap-2 text-sm">
                  <input type="checkbox" v-model="selectedZones.arches" class="accent-[#fc9303]">
                  Арки
                </label>
                <label class="flex items-center gap-2 text-sm">
                  <input type="checkbox" v-model="selectedZones.roof" class="accent-[#fc9303]">
                  Крыша
                </label>
                <label class="flex items-center gap-2 text-sm">
                  <input type="checkbox" v-model="selectedZones.trunk" class="accent-[#fc9303]">
                  Багажник
                </label>
              </div>
            </div>
            
            <div class="mt-6 p-4 bg-gray-800/50 rounded-xl">
              <div class="flex justify-between items-center">
                <div>
                  <p class="text-gray-400 text-sm">Рекомендуемый материал</p>
                  <p class="text-lg font-semibold">{{ calculateSoundproofing().material }}</p>
                  <p class="text-sm text-gray-500">{{ calculateSoundproofing().sheets }} листов</p>
                </div>
                <div class="text-right">
                  <p class="text-gray-400 text-sm">Ориентировочная стоимость</p>
                  <p class="text-2xl font-bold text-[#fc9303]">{{ calculateSoundproofing().price.toLocaleString() }} ₽</p>
                </div>
              </div>
              <button class="mt-4 w-full bg-gradient-to-r from-[#fc9303] to-[#ff6b00] py-3 rounded-xl font-semibold">
                Добавить комплект в корзину
              </button>
            </div>
          </div>
        </div>
        
        <!-- Сетка товаров -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          <div
            v-for="item in filteredProducts"
            :key="item.id"
            class="group bg-gradient-to-br from-gray-900 to-black rounded-2xl border border-gray-800 overflow-hidden hover:border-[#fc9303] transition-all duration-300 hover:-translate-y-1"
          >
            <!-- Изображение -->
            <div class="relative aspect-square overflow-hidden bg-gray-800">
              <img 
                :src="item.image" 
                :alt="item.name"
                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
              >
              <div v-if="item.popular" class="absolute top-3 left-3 px-2 py-1 bg-[#fc9303] text-xs font-bold rounded-full">
                ХИТ
              </div>
            </div>
            
            <!-- Информация -->
            <div class="p-5">
              <div class="flex justify-between items-start mb-2">
                <h3 class="text-lg font-bold text-white">{{ item.name }}</h3>
                <span class="text-xs text-gray-500">{{ item.brand }}</span>
              </div>
              <p class="text-sm text-gray-400 mb-3 line-clamp-2">{{ item.description }}</p>
              
              <div class="flex flex-wrap gap-2 mb-4">
                <span v-for="feature in item.features.slice(0, 2)" :key="feature" class="text-xs text-gray-500 bg-gray-800 px-2 py-1 rounded-full">
                  {{ feature }}
                </span>
              </div>
              
              <div class="flex items-center justify-between">
                <div>
                  <span class="text-2xl font-bold text-[#fc9303]">{{ item.price.toLocaleString() }} ₽</span>
                  <span class="text-xs text-gray-500">/ {{ item.unit }}</span>
                </div>
                <button
                  @click="addToCart(item)"
                  class="w-10 h-10 rounded-full bg-gradient-to-r from-[#fc9303] to-[#ff6b00] flex items-center justify-center hover:scale-110 transition-transform"
                >
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Корзина (плавающая кнопка) -->
    <button
      @click="showCart = true"
      class="fixed bottom-8 right-8 z-50 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] rounded-full p-4 shadow-lg shadow-orange-500/30 hover:scale-110 transition-transform"
    >
      <div class="relative">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>
        <span v-if="cartCount" class="absolute -top-2 -right-2 w-5 h-5 bg-white text-black text-xs font-bold rounded-full flex items-center justify-center">
          {{ cartCount }}
        </span>
      </div>
    </button>

    <!-- Модальное окно корзины -->
    <Transition name="modal">
      <div v-if="showCart" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-end">
        <div class="w-full max-w-md h-full bg-gradient-to-b from-gray-900 to-black border-l border-gray-800 shadow-2xl flex flex-col">
          <!-- Заголовок -->
          <div class="flex justify-between items-center p-6 border-b border-gray-800">
            <h2 class="text-2xl font-bold">Корзина</h2>
            <button @click="showCart = false" class="text-gray-400 hover:text-white">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <!-- Товары -->
          <div class="flex-1 overflow-y-auto p-6 space-y-4">
            <div v-for="item in cart" :key="item.id" class="flex gap-4 border-b border-gray-800 pb-4">
              <img :src="item.image" :alt="item.name" class="w-16 h-16 rounded-lg object-cover bg-gray-800">
              <div class="flex-1">
                <h3 class="font-semibold">{{ item.name }}</h3>
                <p class="text-sm text-gray-400">{{ item.price.toLocaleString() }} ₽ / {{ item.unit }}</p>
                <div class="flex items-center gap-3 mt-2">
                  <button @click="updateQuantity(item.id, -1)" class="w-6 h-6 rounded-full bg-gray-800 text-white">-</button>
                  <span class="w-8 text-center">{{ item.quantity }}</span>
                  <button @click="updateQuantity(item.id, 1)" class="w-6 h-6 rounded-full bg-gray-800 text-white">+</button>
                  <button @click="removeFromCart(item.id)" class="ml-auto text-red-500 text-sm">Удалить</button>
                </div>
              </div>
            </div>
            <div v-if="cart.length === 0" class="text-center text-gray-400 py-12">
              Корзина пуста
            </div>
          </div>
          
          <!-- Итого и кнопка -->
          <div class="border-t border-gray-800 p-6">
            <div class="flex justify-between mb-4">
              <span class="text-gray-400">Итого:</span>
              <span class="text-2xl font-bold text-[#fc9303]">{{ cartTotal.toLocaleString() }} ₽</span>
            </div>
            <button
              @click="checkout"
              :disabled="cart.length === 0"
              class="w-full bg-gradient-to-r from-[#fc9303] to-[#ff6b00] py-4 rounded-xl font-semibold disabled:opacity-50 disabled:cursor-not-allowed hover:scale-[1.02] transition-transform"
            >
              Оформить заказ
            </button>
            <p class="text-xs text-gray-500 text-center mt-4">
              При заказе от 5 000 ₽ — фирменный стикер в подарок
            </p>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Доставка -->
    <section class="py-20 border-t border-gray-800">
      <div class="site-container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
          <div>
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[#fc9303]/20 flex items-center justify-center">
              <svg class="w-8 h-8 text-[#fc9303]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0-4H4m6 10H4m0 0l4 4m-4-4l4-4"></path>
              </svg>
            </div>
            <h3 class="text-lg font-bold mb-2">Самовывоз</h3>
            <p class="text-gray-400 text-sm">г. Хабаровск, ул. Кавказская 35/5</p>
            <p class="text-[#fc9303] text-sm mt-1">Бесплатно</p>
          </div>
          
          <div>
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[#fc9303]/20 flex items-center justify-center">
              <svg class="w-8 h-8 text-[#fc9303]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
              </svg>
            </div>
            <h3 class="text-lg font-bold mb-2">Доставка по городу</h3>
            <p class="text-gray-400 text-sm">300 ₽</p>
            <p class="text-[#fc9303] text-sm mt-1">От 3 000 ₽ — бесплатно</p>
          </div>
          
          <div>
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[#fc9303]/20 flex items-center justify-center">
              <svg class="w-8 h-8 text-[#fc9303]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15v4m0 0h18m-18 0v-4m18 4v-4m-8-4h4m-4 0H7m4 0v4m4-4v4m-4-8V5m0 0h4m-4 0H7m10 2v4"></path>
              </svg>
            </div>
            <h3 class="text-lg font-bold mb-2">Доставка по России</h3>
            <p class="text-gray-400 text-sm">СДЭК, Почта России</p>
            <p class="text-[#fc9303] text-sm mt-1">От 500 ₽</p>
          </div>
        </div>
        
        <div class="mt-12 text-center">
          <p class="text-gray-500 text-sm">🎁 При заказе от 5 000 ₽ — фирменный стикерпак в подарок</p>
          <p class="text-gray-500 text-sm mt-1">🎁 При заказе от 10 000 ₽ — скидка 10% на следующий заказ</p>
        </div>
      </div>
    </section>
    <AlertModal
      :show="alertModal.show"
      :title="alertModal.title"
      :message="alertModal.message"
      type="info"
      @close="alertModal.show = false"
    />
  </div>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
  transform: translateX(100%);
}

.scrollbar-hide::-webkit-scrollbar {
  display: none;
}

.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>