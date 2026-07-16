import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createUnhead, headSymbol } from '@unhead/vue'
import App from './App.vue'
import router from './router'
// Счётчик визитов временно отключён — beacon /visit на каждой загрузке под
// подозрением в тормозах под нагрузкой. Чтобы вернуть: раскомментировать эти
// импорты и блок в конце файла.
// import { apiFetch } from './config/api'
// import { shouldTrackVisit } from './config/visit'

const app = createApp(App)
const pinia = createPinia()
const head = createUnhead()

app.use(pinia)
app.use(router)
app.use({ install: (a) => { a.provide(headSymbol, head) } })
app.mount('#app')

// ── Счётчик визитов ВРЕМЕННО ОТКЛЮЧЁН (диагностика тормозов) ──────────────────
// Чтобы вернуть — раскомментировать блок ниже и импорты вверху файла.
// const LAST_VISIT_KEY = 'last_visit_at'
// if (shouldTrackVisit(Date.now(), localStorage.getItem(LAST_VISIT_KEY), navigator.webdriver === true)) {
//     try { localStorage.setItem(LAST_VISIT_KEY, String(Date.now())) } catch {}
//     apiFetch('/visit', {
//         method: 'POST',
//         body: JSON.stringify({ referrer: document.referrer }),
//     }).catch(() => {})
// }
