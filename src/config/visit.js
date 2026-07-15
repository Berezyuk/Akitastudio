// Граница визита для статистики посещений: 30 минут неактивности.
// Живёт отдельно от main.js, чтобы быть вызываемой из теста.

export const VISIT_WINDOW_MS = 30 * 60 * 1000

// last приходит из localStorage — это строка, null или что угодно, если её
// правили руками. Любое нечисло трактуем как «визита не было»: лишний визит
// в статистике честнее потерянного.
export function shouldTrackVisit(now, last) {
    const prev = Number(last)
    if (!Number.isFinite(prev) || prev <= 0) return true
    return now - prev >= VISIT_WINDOW_MS
}
