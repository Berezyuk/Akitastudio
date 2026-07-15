export const API_BASE = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'

// Обёртка над fetch: базовый URL, cookie-сессия, JSON-заголовок, разбор ответа.
// Body передавать уже сериализованным (JSON.stringify) — FormData сюда не идёт.
export async function apiFetch(path, options = {}) {
    const res = await fetch(`${API_BASE}${path}`, {
        credentials: 'include',
        ...options,
        headers: { 'Content-Type': 'application/json', ...options.headers },
    })
    const data = await res.json().catch(() => ({}))
    // Бэкенд отдаёт валидный JSON и на 4xx/5xx — без проверки статуса
    // ошибка неотличима от обычного ответа и тонет молча.
    if (!res.ok) {
        // Статус кладём на ошибку: по тексту 401 от сетевого сбоя не отличить,
        // а вызывающим это нужно (гость на публичной странице — не авария).
        const err = new Error(data.error || `HTTP ${res.status}`)
        err.status = res.status
        throw err
    }
    return data
}
