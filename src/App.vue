<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import TheHeader from "./components/TheHeader.vue";
import TheFooter from "./components/TheFooter.vue";

const route = useRoute()
const isAdmin = computed(() => route.path.startsWith('/admin'))
</script>

<template>
  <div class="app">
    <TheHeader v-if="!isAdmin" />
    <main class="main-content">
      <router-view />
    </main>
    <TheFooter v-if="!isAdmin" />
  </div>
</template>

<style scoped>
.app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.main-content {
  flex: 1;
}
</style>