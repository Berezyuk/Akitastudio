// Граница визита для статистики посещений: 30 минут неактивности.
// Живёт отдельно от main.js, чтобы быть вызываемой из теста.

export const VISIT_WINDOW_MS = 30 * 60 * 1000

// last приходит из localStorage — это строка, null или что угодно, если её
// правили руками. Любое нечисло трактуем как «визита не было»: лишний визит
// в статистике честнее потерянного.
//
// isAutomated — navigator.webdriver из main.js. Флаг ставят браузеры,
// управляемые WebDriver-протоколом (Selenium/Puppeteer/Playwright — в т.ч.
// наш собственный scripts/prerender.mjs), поэтому им отсекаем визит целиком,
// не дожидаясь окна. Googlebot и прочие JS-краулеры WebDriver не используют
// и флаг не ставят — они как считались, так и продолжают считаться.
export function shouldTrackVisit(now, last, isAutomated = false) {
    if (isAutomated) return false
    const prev = Number(last)
    if (!Number.isFinite(prev) || prev <= 0) return true
    return now - prev >= VISIT_WINDOW_MS
}
