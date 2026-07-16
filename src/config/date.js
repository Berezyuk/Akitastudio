// PostgreSQL TIMESTAMP приходит из API как "2026-07-16 12:00:00" — пробел вместо
// 'T'. new Date() на такой строке в Safari (iOS) и Firefox даёт Invalid Date:
// им нужен ISO-разделитель. Из-за этого на iPhone ломались показ, сортировка и
// фильтрация заказов по дате. Заменяем пробел на 'T'; дату-only ("2026-07-16")
// и любые не-строки не трогаем — поведение прежнее.
export function parseApiDate(value) {
  if (value == null) return value
  return new Date(typeof value === 'string' ? value.replace(' ', 'T') : value)
}
