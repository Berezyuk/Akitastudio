<template>
  <div class="gift-page bg-black text-white min-h-screen">
    <!-- Hero секция -->
    <section class="relative pt-32 pb-20 overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-b from-black via-black to-[#4d4d4d]/20"></div>
      <div class="absolute inset-0 opacity-30">
        <div class="absolute top-20 left-10 w-64 h-64 bg-[#fc9303] rounded-full filter blur-[100px]"></div>
        <div class="absolute bottom-20 right-10 w-96 h-96 bg-[#ff6b00] rounded-full filter blur-[150px]"></div>
      </div>
      <div class="site-container mx-auto px-4 relative z-10 text-center">
        <div class="inline-block mb-6">
          <span class="px-4 py-2 text-xs tracking-[0.2em] text-[#fc9303] border border-[#fc9303]/30 rounded-full bg-black/30 backdrop-blur-sm">
            ПОДАРОЧНЫЙ СЕРТИФИКАТ
          </span>
        </div>
        <h1 class="text-5xl md:text-7xl font-bold mb-6">
          <span class="bg-gradient-to-r from-[#fc9303] to-[#ff6b00] bg-clip-text text-transparent">
            Идеальный подарок
          </span>
        </h1>
        <p class="text-xl text-gray-400 max-w-2xl mx-auto">
          Подарите близким премиальный уход за автомобилем. Сертификат действует на любые услуги студии.
        </p>
      </div>
    </section>

    <!-- Карточки номиналов -->
    <section class="py-16">
      <div class="site-container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
          <!-- Номинал 10 000 ₽ -->
          <div class="group relative bg-gradient-to-br from-gray-900 to-black rounded-2xl border border-gray-800 p-8 text-center hover:border-[#fc9303] transition-all duration-500 hover:-translate-y-2">
            <div class="absolute inset-0 rounded-2xl bg-gradient-to-r from-[#fc9303]/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
            <div class="relative z-10">
              <div class="text-5xl font-bold text-[#fc9303] mb-2">10 000 ₽</div>
              <div class="w-16 h-px bg-gradient-to-r from-[#fc9303] to-transparent mx-auto my-6"></div>
              <ul class="text-gray-400 space-y-2 text-sm mb-8">
                <li>✓ Любая услуга до 10 000 ₽</li>
                <li>✓ Срок действия 6 месяцев</li>
                <li>✓ Именной или на предъявителя</li>
              </ul>
              <button @click="selectCertificate(10000)" class="w-full py-3 rounded-full border border-[#fc9303] text-white hover:bg-[#fc9303] transition-all duration-300">
                Выбрать
              </button>
            </div>
          </div>

          <!-- Номинал 15 000 ₽ -->
          <div class="group relative bg-gradient-to-br from-gray-900 to-black rounded-2xl border-2 border-[#fc9303]/50 p-8 text-center hover:border-[#fc9303] transition-all duration-500 hover:-translate-y-2 shadow-lg shadow-orange-500/10">
            <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-[#fc9303] text-black text-xs font-bold px-4 py-1 rounded-full">
              ПОПУЛЯРНЫЙ
            </div>
            <div class="relative z-10">
              <div class="text-5xl font-bold text-[#fc9303] mb-2">15 000 ₽</div>
              <div class="w-16 h-px bg-gradient-to-r from-[#fc9303] to-transparent mx-auto my-6"></div>
              <ul class="text-gray-400 space-y-2 text-sm mb-8">
                <li>✓ Любая услуга до 15 000 ₽</li>
                <li>✓ Срок действия 9 месяцев</li>
                <li>✓ Именной или на предъявителя</li>
                <li>✓ Подарочная упаковка</li>
              </ul>
              <button @click="selectCertificate(15000)" class="w-full py-3 rounded-full bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-black font-semibold hover:scale-105 transition-all duration-300">
                Выбрать
              </button>
            </div>
          </div>

          <!-- Номинал 25 000 ₽ -->
          <div class="group relative bg-gradient-to-br from-gray-900 to-black rounded-2xl border border-gray-800 p-8 text-center hover:border-[#fc9303] transition-all duration-500 hover:-translate-y-2">
            <div class="absolute inset-0 rounded-2xl bg-gradient-to-r from-[#fc9303]/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
            <div class="relative z-10">
              <div class="text-5xl font-bold text-[#fc9303] mb-2">25 000 ₽</div>
              <div class="w-16 h-px bg-gradient-to-r from-[#fc9303] to-transparent mx-auto my-6"></div>
              <ul class="text-gray-400 space-y-2 text-sm mb-8">
                <li>✓ Любая услуга до 25 000 ₽</li>
                <li>✓ Срок действия 12 месяцев</li>
                <li>✓ Именной или на предъявителя</li>
                <li>✓ Подарочная упаковка + открытка</li>
              </ul>
              <button @click="selectCertificate(25000)" class="w-full py-3 rounded-full border border-[#fc9303] text-white hover:bg-[#fc9303] transition-all duration-300">
                Выбрать
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Преимущества -->
    <section class="py-20 bg-gradient-to-b from-black to-[#4d4d4d]/20">
      <div class="site-container mx-auto px-4">
        <div class="text-center mb-16">
          <h2 class="text-4xl md:text-5xl font-bold mb-4">
            <span class="bg-gradient-to-r from-white to-gray-400 bg-clip-text text-transparent">
              Почему сертификат Akita?
            </span>
          </h2>
          <p class="text-gray-400 max-w-2xl mx-auto">
            Дарите не просто деньги, а эмоции и премиальный сервис
          </p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
          <div class="text-center group">
            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-[#fc9303]/20 flex items-center justify-center text-3xl group-hover:scale-110 transition-transform">
              🎁
            </div>
            <h3 class="text-xl font-bold mb-2">Подарочная упаковка</h3>
            <p class="text-gray-400 text-sm">Красивая коробка с лентой и персональной открыткой</p>
          </div>
          <div class="text-center group">
            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-[#fc9303]/20 flex items-center justify-center text-3xl group-hover:scale-110 transition-transform">
              ⚡
            </div>
            <h3 class="text-xl font-bold mb-2">Мгновенная отправка</h3>
            <p class="text-gray-400 text-sm">Электронный сертификат придёт на почту сразу после оплаты</p>
          </div>
          <div class="text-center group">
            <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-[#fc9303]/20 flex items-center justify-center text-3xl group-hover:scale-110 transition-transform">
              🏆
            </div>
            <h3 class="text-xl font-bold mb-2">Любые услуги</h3>
            <p class="text-gray-400 text-sm">Можно использовать на любые услуги студии без ограничений</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Как это работает -->
    <section class="py-20">
      <div class="site-container mx-auto px-4">
        <div class="text-center mb-16">
          <h2 class="text-4xl md:text-5xl font-bold mb-4">
            <span class="bg-gradient-to-r from-white to-gray-400 bg-clip-text text-transparent">
              Как это работает
            </span>
          </h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
          <div class="flex flex-col items-center text-center">
            <div class="w-12 h-12 rounded-full bg-[#fc9303] text-black font-bold text-xl flex items-center justify-center mb-4">1</div>
            <h3 class="text-lg font-semibold mb-2">Выберите номинал</h3>
            <p class="text-gray-400 text-sm">10 000, 15 000 или 25 000 рублей</p>
          </div>
          <div class="flex flex-col items-center text-center">
            <div class="w-12 h-12 rounded-full bg-[#fc9303] text-black font-bold text-xl flex items-center justify-center mb-4">2</div>
            <h3 class="text-lg font-semibold mb-2">Оформите заказ</h3>
            <p class="text-gray-400 text-sm">Укажите имя получателя и ваши контакты</p>
          </div>
          <div class="flex flex-col items-center text-center">
            <div class="w-12 h-12 rounded-full bg-[#fc9303] text-black font-bold text-xl flex items-center justify-center mb-4">3</div>
            <h3 class="text-lg font-semibold mb-2">Получите сертификат</h3>
            <p class="text-gray-400 text-sm">На email в течение 5 минут после оплаты</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Форма заказа (модальное окно) -->
    <Transition name="modal">
      <div v-if="showModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="closeModal">
        <div class="bg-gray-900 rounded-2xl border border-gray-800 w-full max-w-md p-6">
          <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold">Оформление сертификата</h3>
            <button @click="closeModal" class="text-gray-400 hover:text-white">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <form @submit.prevent="submitOrder" class="space-y-4">
            <div>
              <label class="block text-sm text-gray-400 mb-1">Номинал</label>
              <input :value="selectedAmount?.toLocaleString() + ' ₽'" disabled class="w-full px-4 py-2 bg-gray-800 rounded-lg text-white">
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-1">Имя получателя</label>
              <input v-model="recipientName" type="text" required class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-[#fc9303]">
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-1">Email получателя</label>
              <input v-model="recipientEmail" type="email" required class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-[#fc9303]">
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-1">Ваше имя</label>
              <input v-model="buyerName" type="text" required class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-[#fc9303]">
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-1">Ваш телефон</label>
              <input v-model="buyerPhone" type="tel" required class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-[#fc9303]">
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-1">Поздравительный текст (опционально)</label>
              <textarea v-model="message" rows="3" class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white resize-none" placeholder="С пожеланиями..."></textarea>
            </div>
            <button type="submit" :disabled="loading" class="w-full bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-black font-semibold py-3 rounded-lg hover:scale-105 transition-all duration-300 disabled:opacity-50">
              {{ loading ? 'Отправка...' : 'Заказать' }}
            </button>
          </form>
        </div>
      </div>
    </Transition>

    <!-- CTA секция -->
    <section class="relative py-20 overflow-hidden bg-gradient-to-r from-black to-[#4d4d4d]/30">
      <div class="site-container mx-auto px-4 text-center">
        <h2 class="text-3xl md:text-5xl font-bold mb-6">
          <span class="bg-gradient-to-r from-white to-[#fc9303] bg-clip-text text-transparent">
            Подарите радость автолюбителю
          </span>
        </h2>
        <p class="text-gray-400 mb-8 max-w-2xl mx-auto">
          Сертификат действует на все услуги студии: полировка, керамика, химчистка и многое другое
        </p>
        <router-link to="/booking" class="inline-block px-8 py-4 bg-gradient-to-r from-[#fc9303] to-[#ff6b00] text-black font-bold rounded-full hover:scale-105 transition-all duration-300 shadow-lg shadow-orange-500/30">
          Записаться по сертификату
        </router-link>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const showModal = ref(false)
const selectedAmount = ref(null)
const recipientName = ref('')
const recipientEmail = ref('')
const buyerName = ref('')
const buyerPhone = ref('')
const message = ref('')
const loading = ref(false)

const selectCertificate = (amount) => {
  selectedAmount.value = amount
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  selectedAmount.value = null
  recipientName.value = ''
  recipientEmail.value = ''
  buyerName.value = ''
  buyerPhone.value = ''
  message.value = ''
}

const submitOrder = async () => {
  loading.value = true
  // Здесь будет отправка заказа на бэкенд (API)
  // Пока имитируем отправку
  setTimeout(() => {
    alert('Заявка отправлена! Мы свяжемся с вами для подтверждения.')
    closeModal()
    loading.value = false
  }, 1000)
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: all 0.3s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
  transform: scale(0.95);
}
</style>