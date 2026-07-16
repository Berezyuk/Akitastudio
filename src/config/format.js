// Форматтеры отображения. Были скопированы по нескольким вьюхам.

// Цена → "1 234 ₽". Пустое/нечисло → "0 ₽".
export function formatPrice(price) {
  if (!price && price !== 0) return '0 ₽'
  const num = parseFloat(price)
  if (isNaN(num)) return '0 ₽'
  return num.toLocaleString('ru-RU') + ' ₽'
}

// Для CSV: число как "1234,56" (запятая — десятичный разделитель), без символа.
export function formatPriceCSV(price) {
  if (!price && price !== 0) return '0'
  const num = parseFloat(price)
  if (isNaN(num)) return '0'
  return num.toString().replace('.', ',')
}

// mp4/webm/ogg по расширению URL (учитывает query-строку presigned-ссылок).
export function isVideo(url) {
  return /\.(mp4|webm|ogg)(\?|$)/i.test(url || '')
}
