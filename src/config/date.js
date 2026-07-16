// PostgreSQL TIMESTAMP приходит из API как "2026-07-16 12:00:00" — пробел вместо
// 'T'. new Date() на такой строке в Safari (iOS) и Firefox даёт Invalid Date:
// им нужен ISO-разделитель. Из-за этого на iPhone ломались показ, сортировка и
// фильтрация заказов по дате. Заменяем пробел на 'T'; дату-only ("2026-07-16")
// и любые не-строки не трогаем — поведение прежнее.
export function parseApiDate(value) {
  if (value == null) return value
  return new Date(typeof value === 'string' ? value.replace(' ', 'T') : value)
}

// "16.07.2026" (ru). Пустое/битое → fallback. Была скопирована по вьюхам.
export function formatDate(value, fallback = '—') {
  if (!value) return fallback
  const d = parseApiDate(value)
  return d && !isNaN(d) ? d.toLocaleDateString('ru-RU') : fallback
}

// "16.07.2026 14:30". Для карточки заказа.
export function formatDateTime(value, fallback = '') {
  if (!value) return fallback
  const d = parseApiDate(value)
  if (!d || isNaN(d)) return fallback
  return d.toLocaleDateString('ru-RU') + ' ' +
    d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}
