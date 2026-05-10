import { createApp } from 'vue'
import { createPinia } from 'pinia'  // <-- ДОБАВЬ ЭТО
import App from './App.vue'
import router from './router'

const app = createApp(App)
const pinia = createPinia()  // <-- ДОБАВЬ ЭТО

app.use(pinia)  // <-- ДОБАВЬ ЭТО
app.use(router)
app.mount('#app')
