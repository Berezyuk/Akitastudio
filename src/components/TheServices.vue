<script setup>
import TheHeader from "../components/TheHeader.vue";
import TheFooter from "../components/TheFooter.vue";


</script>
<template>
  <TheHeader/>
  <main class="min-h-screen">
    <div class="container mx-auto py-12 px-4">
      <h1 class="text-4xl font-bold text-center mb-8">Наши услуги</h1>
      <p class="text-center text-gray-600 mb-12">Подробное описание всех наших услуг</p>
      
      <div class="bg-gray-50 p-8 rounded-2xl">
        <h2 class="text-2xl font-bold mb-6">Все услуги</h2>
        <p>Здесь будет детальная информация о каждой услуге...</p>
      </div>
      
      <!-- Кнопка назад на главную -->
      <div class="mt-12 text-center">
        <a 
          href="/"
          class="inline-flex items-center gap-2 px-6 py-3 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
          </svg>
          Вернуться на главную
        </a>
      </div>
    </div>
  </main>
  <TheFooter/>

</template>