// Смоук-тесты API. Запуск: npm test (нужен поднятый стек: docker compose up -d).
//
// Зависимостей не добавляют: node:test встроен в Node 20, БД берётся из compose.
// Покрывают то, что уже ломалось и чинилось вручную — чтобы не сломалось молча снова.
//
// ⚠️  ТОЛЬКО ДЛЯ DEV-СТЕНДА. Тесты создают реальные заказы и клиентов
//     (телефоны 7999555xxxx, client_notes 'test-...') и НЕ убирают за собой:
//     эндпоинтов удаления заказа/клиента в API нет. По продовой БД не гонять.
//     Очистка:
//       docker compose exec -T postgres sh -c 'psql -U $POSTGRES_USER -d $POSTGRES_DB -c "
//         DELETE FROM order_services WHERE order_id IN (SELECT order_id FROM orders WHERE client_notes LIKE '"'"'test-%'"'"');
//         DELETE FROM orders WHERE client_notes LIKE '"'"'test-%'"'"';
//         DELETE FROM clients WHERE phone_number LIKE '"'"'7999555%'"'"';"'
import { test, describe, before } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const API = process.env.API_BASE || 'http://localhost:8000/api'

// Креды админа берём из .env — того же, что использует стек.
const env = Object.fromEntries(
  readFileSync(new URL('../.env', import.meta.url), 'utf8')
    .split('\n')
    .filter((l) => l.includes('=') && !l.trim().startsWith('#'))
    .map((l) => [l.slice(0, l.indexOf('=')).trim(), l.slice(l.indexOf('=') + 1).trim()])
)

const post = (path, body, cookie) =>
  fetch(API + path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...(cookie ? { Cookie: cookie } : {}) },
    body: JSON.stringify(body),
  })

const loginCookie = async (login, password) => {
  const res = await post('/auth/login', { login, password })
  const data = await res.json()
  assert.ok(data.success, `login failed: ${JSON.stringify(data)}`)
  return (res.headers.getSetCookie?.() || []).map((c) => c.split(';')[0]).join('; ')
}

describe('guard: админские роуты без сессии', () => {
  // Авторизация висит на каждом методе контроллера вручную — роутер её не
  // навешивает. Один забытый checkAdmin() = открытый админский эндпоинт,
  // и никто не заметит. Этот тест падает в тот же день.
  const adminRoutes = [
    '/admin/dashboard',
    '/admin/services',
    '/admin/service-categories',
    '/admin/portfolio',
    '/admin/clients',
    '/admin/orders',
    '/admin/order-statuses',
    '/admin/feedbacks',
    '/admin/settings',
  ]

  for (const route of adminRoutes) {
    test(`GET ${route} -> 401`, async () => {
      const res = await fetch(API + route)
      assert.equal(res.status, 401, `${route} доступен без авторизации!`)
    })
  }
})

describe('order/create: подмена client_id (IDOR)', () => {
  let victimClientId

  before(async () => {
    // Жертва оформляет обычную заявку — так создаётся её карточка клиента.
    await post('/order/create', {
      client_name: 'IDOR Victim', client_phone: '79995550001',
      car_brand: 'BMW', car_model: 'X5', service_ids: [1],
      desired_date: '2030-01-01', desired_time: '10:00', comment: 'test-idor-victim',
    })
    const cookie = await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD)
    const list = await (await fetch(API + '/admin/clients?search=79995550001', { headers: { Cookie: cookie } })).json()
    victimClientId = list.clients?.[0]?.client_id
    assert.ok(victimClientId, 'не удалось создать клиента-жертву')
  })

  test('аноним не может подбросить заказ в чужой кабинет', async () => {
    const res = await post('/order/create', {
      client_id: victimClientId,           // <- значение из тела не должно приниматься
      car_brand: 'Audi', car_model: 'Q7', service_ids: [1],
      desired_date: '2030-01-01', desired_time: '10:00', comment: 'test-idor-attack',
    })
    const data = await res.json()
    assert.ok(!data.success, `заказ создан от имени чужого клиента: ${JSON.stringify(data)}`)
  })
})

describe('order/create: резолв услуг', () => {
  const base = {
    client_name: 'Test', client_phone: '79995550002',
    car_brand: 'Kia', car_model: 'Rio',
    desired_date: '2030-01-01', desired_time: '10:00', comment: 'test-services',
  }

  test('несуществующая услуга -> отказ, а не заказ на 0 ₽', async () => {
    const data = await (await post('/order/create', { ...base, service_ids: [999999] })).json()
    assert.ok(!data.success, 'заказ создан с несуществующей услугой')
  })

  test('частичный список -> отказ, а не тихо заниженная сумма', async () => {
    const data = await (await post('/order/create', { ...base, service_ids: [1, 999999] })).json()
    assert.ok(!data.success, 'заказ создан, часть услуг молча отброшена')
  })

  test('валидная услуга -> заказ создаётся', async () => {
    const data = await (await post('/order/create', { ...base, service_ids: [1] })).json()
    assert.ok(data.success, `валидный заказ отклонён: ${JSON.stringify(data)}`)
  })
})

describe('дашборд', () => {
  test('today_orders считает заказы за сегодня, а не только созданные в полночь', async () => {
    const cookie = await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD)
    const data = await (await fetch(API + '/admin/dashboard', { headers: { Cookie: cookie } })).json()
    assert.ok(data.success)
    // Тесты выше создали заказы сегодня — счётчик обязан быть > 0.
    assert.ok(data.stats.today_orders > 0, 'today_orders = 0 при созданных сегодня заказах')
  })
})

describe('удалённые мёртвые роуты не воскресли', () => {
  for (const route of ['/admin/employees', '/car-brands', '/user/cars', '/admin/orders/export']) {
    test(`${route} -> 404`, async () => {
      const res = await fetch(API + route)
      assert.equal(res.status, 404)
    })
  }
})
