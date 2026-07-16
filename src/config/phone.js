// Маска телефона РФ. Была скопирована в BookingView / RegisterView / HomeView.

// 10 цифр (без кода страны) → "+7 (XXX) XXX-XX-XX", по мере ввода.
export function applyPhoneMask(digits) {
  if (!digits) return ''
  let r = '+7 (' + digits.slice(0, 3)
  if (digits.length >= 3) r += ')'
  if (digits.length > 3) r += ' ' + digits.slice(3, 6)
  if (digits.length > 6) r += '-' + digits.slice(6, 8)
  if (digits.length > 8) r += '-' + digits.slice(8, 10)
  return r
}

// Обработчик ввода: из сырого значения инпута и предыдущего форматированного
// возвращает новое форматированное. Трюк с backspace: если удаление символа не
// изменило маску (стёрли разделитель, не цифру) — убираем последнюю цифру.
export function maskPhoneInput(inputValue, prevFormatted) {
  let raw = inputValue.replace(/\D/g, '')
  if (raw.startsWith('7') || raw.startsWith('8')) raw = raw.slice(1)
  raw = raw.slice(0, 10)
  let result = applyPhoneMask(raw)
  if (result === prevFormatted && inputValue.length < prevFormatted.length && raw.length > 0) {
    result = applyPhoneMask(raw.slice(0, -1))
  }
  return result
}
