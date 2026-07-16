import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createUnhead, headSymbol } from '@unhead/vue'
import App from './App.vue'
import router from './router'
import { apiFetch } from './config/api'
import { shouldTrackVisit } from './config/visit'

const app = createApp(App)
const pinia = createPinia()
const head = createUnhead()

app.use(pinia)
app.use(router)
app.use({ install: (a) => { a.provide(headSymbol, head) } })
app.mount('#app')

// Счётчик визитов для админки. document.referrer, а не заголовок Referer: на
// fetch из SPA тот равен URL текущей страницы и внешний источник не показывает.
const LAST_VISIT_KEY = 'last_visit_at'
// navigator.webdriver: пре-рендер (scripts/prerender.mjs) гоняет реальный main.js
// в Puppeteer на боевом VITE_API_URL — без этой проверки каждый деплой писал
// в visits фейковый визит с IP сборочной машины.
if (shouldTrackVisit(Date.now(), localStorage.getItem(LAST_VISIT_KEY), navigator.webdriver === true)) {
    // Метку ставим до запроса и независимо от исхода: иначе при лежащем API
    // beacon уходил бы на каждой навигации.
    localStorage.setItem(LAST_VISIT_KEY, String(Date.now()))
    apiFetch('/visit', {
        method: 'POST',
        body: JSON.stringify({ referrer: document.referrer }),
    }).catch(() => {})   // статистика не должна ронять сайт
}
