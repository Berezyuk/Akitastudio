// Фильтры + пагинация AdminOrders. Здесь жил самый неприятный баг: фильтрация шла
// по текущей серверной странице (50 строк), а пагинатор показывал серверный total —
// поиск заказа со второй страницы давал «не найдено» при «60 записей» в подписи.
//
// Тестируется поведение, а не разметка: сколько строк отдаёт pagedOrders и что
// видит пагинатор. Дочерние компоненты застабаны — они тут ни при чём.
import { test, describe, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import AdminOrders from '../../src/views/admin/AdminOrders.vue'
import ThePagination from '../../src/components/ThePagination.vue'

// 60 заказов: Needle — самый старый, т.е. последний при сортировке по дате desc
// и гарантированно вне первой страницы (limit 50).
const ORDERS = Array.from({ length: 60 }, (_, i) => ({
  order_id: i + 1,
  first_name: i === 59 ? 'Needle' : `Bulk${i + 1}`,
  last_name: 'Client',
  phone_number: `7999555${String(i).padStart(4, '0')}`,
  order_date: new Date(Date.UTC(2030, 0, 1) - i * 3600_000).toISOString(),
  desired_date: '2030-01-01',
  desired_time: '10:00',
  total_price: '1000.00',
  status_id: (i % 3) + 1,
  status_name: ['Новый', 'В работе', 'Готово'][i % 3],
  brand_name: 'BMW',
  model_name: 'X5',
  service_names: i % 2 ? 'Полировка' : 'Химчистка',
}))

const STATUSES = [
  { status_id: 1, name: 'Новый' },
  { status_id: 2, name: 'В работе' },
  { status_id: 3, name: 'Готово' },
]

const stubs = { AdminOrderModal: true, AlertModal: true }

beforeEach(() => {
  vi.stubGlobal('fetch', vi.fn((url) => {
    if (url.includes('/admin/order-statuses')) {
      return Promise.resolve(new Response(JSON.stringify({ success: true, statuses: STATUSES })))
    }
    // Мок ведёт себя как старый бэкенд: если у него просят page/limit — он режет
    // выборку. Без этого тесты на счётчики зеленели бы и на сломанном коде,
    // потому что мок всё равно отдавал все 60 строк.
    const { searchParams } = new URL(url, 'http://test')
    const limit = Number(searchParams.get('limit')) || 0
    const page = Number(searchParams.get('page')) || 1
    const orders = limit ? ORDERS.slice((page - 1) * limit, page * limit) : ORDERS
    return Promise.resolve(new Response(JSON.stringify({
      success: true, orders, total: ORDERS.length, page, limit,
    })))
  }))
})
afterEach(() => vi.unstubAllGlobals())

const mountView = async () => {
  const w = mount(AdminOrders, { global: { stubs } })
  await flushPromises()
  return w
}

// Пагинатор не застабан — читаем подпись, которую видит пользователь.
const range = (w) => w.findComponent(ThePagination).find('span').text()
const pagerVisible = (w) => w.findComponent(ThePagination).find('span').exists()
const rowCount = (w) => w.findAll('tbody tr').filter((r) => r.findAll('td').length > 1).length
const typeSearch = async (w, text) => {
  await w.find('input[placeholder*="Имя"]').setValue(text)
  await flushPromises()
}

describe('загрузка', () => {
  test('запрашивает все заказы без page/limit — фильтрам нужен весь список', async () => {
    await mountView()
    const urls = fetch.mock.calls.map(([u]) => u)
    const ordersCall = urls.find((u) => u.includes('/admin/orders'))
    expect(ordersCall).toBeTruthy()
    expect(ordersCall).not.toMatch(/[?&](page|limit)=/)
  })

  test('первая страница — 50 строк из 60', async () => {
    const w = await mountView()
    expect(rowCount(w)).toBe(50)
    expect(range(w)).toBe('1–50 из 60')
  })
})

describe('поиск идёт по всем заказам, а не по текущей странице', () => {
  test('находит клиента, который лежит за пределами первой страницы', async () => {
    const w = await mountView()
    await typeSearch(w, 'Needle')
    expect(rowCount(w)).toBe(1)
    expect(range(w)).toBe('1–1 из 1')
    expect(w.text()).toContain('Needle')
  })

  test('поиск со второй страницы сбрасывает на первую и находит', async () => {
    const w = await mountView()
    w.vm.pagination.page = 2
    await flushPromises()
    expect(range(w)).toBe('51–60 из 60')

    await typeSearch(w, 'Needle')
    expect(w.vm.pagination.page).toBe(1)
    expect(rowCount(w)).toBe(1)
  })

  test('поиск по телефону тоже видит весь список', async () => {
    const w = await mountView()
    await typeSearch(w, '79995550059')
    expect(rowCount(w)).toBe(1)
  })

  test('ничего не найдено — пагинатор скрыт, а не врёт про 60', async () => {
    const w = await mountView()
    await typeSearch(w, 'такого-клиента-нет')
    expect(rowCount(w)).toBe(0)
    expect(pagerVisible(w)).toBe(false)
  })
})

describe('счётчики считают всю выборку, а не одну страницу', () => {
  test('сумма по всем 60 заказам', async () => {
    const w = await mountView()
    expect(w.vm.totalAmount).toBe(60_000)
  })

  test('сумма пересчитывается под фильтр', async () => {
    const w = await mountView()
    await typeSearch(w, 'Needle')
    expect(w.vm.totalAmount).toBe(1000)
  })

  test('список услуг для фильтра собран со всех заказов', async () => {
    const w = await mountView()
    expect(w.vm.uniqueServices).toEqual(['Полировка', 'Химчистка'])
  })

  test('статусы посчитаны по всем 60, а не по 50 видимым', async () => {
    const w = await mountView()
    const total = Object.values(w.vm.statusCounts).reduce((a, b) => a + b, 0)
    expect(total).toBe(60)
  })
})

describe('пагинация', () => {
  test('вторая страница отдаёт остаток', async () => {
    const w = await mountView()
    w.vm.pagination.page = 2
    await flushPromises()
    expect(rowCount(w)).toBe(10)
  })

  test('смена сортировки тоже сбрасывает страницу', async () => {
    const w = await mountView()
    w.vm.pagination.page = 2
    await flushPromises()
    w.vm.sortBy('total_price')
    await flushPromises()
    expect(w.vm.pagination.page).toBe(1)
  })
})
