import { watch, nextTick, onUnmounted } from 'vue'

const FOCUSABLE = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

/**
 * Запирает фокус внутри модалки и возвращает его на триггер при закрытии.
 *
 * Модалки объявлены как aria-modal, но вели себя иначе: Tab уводил в форму под
 * ними, а после закрытия фокус терялся на body.
 *
 * Нативный <dialog>.showModal() дал бы это бесплатно, но он не дружит с
 * v-if + <Transition>, на которых построены все модалки проекта.
 *
 * @param {import('vue').Ref<boolean>} isOpen   открыта ли модалка
 * @param {import('vue').Ref<HTMLElement|null>} containerRef  корень модалки
 */
export function useFocusTrap(isOpen, containerRef) {
  let restoreTo = null

  const items = () =>
    [...(containerRef.value?.querySelectorAll(FOCUSABLE) || [])]
      .filter((el) => el.offsetParent !== null)

  const onKeydown = (e) => {
    if (e.key !== 'Tab') return
    const list = items()
    if (!list.length) return

    const first = list[0]
    const last = list[list.length - 1]
    const active = document.activeElement

    // Фокус мог оказаться вне модалки (клик мимо) — возвращаем внутрь.
    if (!containerRef.value?.contains(active)) {
      e.preventDefault()
      first.focus()
      return
    }
    if (e.shiftKey && active === first) {
      e.preventDefault()
      last.focus()
    } else if (!e.shiftKey && active === last) {
      e.preventDefault()
      first.focus()
    }
  }

  const stop = () => document.removeEventListener('keydown', onKeydown)

  watch(isOpen, async (open) => {
    if (open) {
      restoreTo = document.activeElement
      await nextTick()
      items()[0]?.focus()
      document.addEventListener('keydown', onKeydown)
    } else {
      stop()
      // Возвращаем фокус туда, откуда модалку открыли.
      if (restoreTo?.isConnected) restoreTo.focus()
      restoreTo = null
    }
  })

  onUnmounted(stop)
}
