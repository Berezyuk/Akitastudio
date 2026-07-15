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

  // Скаляр проходил empty(), а foreach по нему — warning, не ошибка: тело цикла
  // не выполнялось, и аноним получал заказ на 0 ₽ без услуг мимо всех проверок.
  for (const ids of [1, '1', true, 'abc', {}]) {
    test(`service_ids=${JSON.stringify(ids)} (не массив) -> отказ, а не заказ на 0 ₽`, async () => {
      const data = await (await post('/order/create', { ...base, service_ids: ids })).json()
      assert.ok(!data.success, `заказ создан с service_ids=${JSON.stringify(ids)}`)
    })
  }
})

describe('admin/orders/{id}/status: валидация статуса', () => {
  let cookie
  let orderId

  before(async () => {
    cookie = await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD)
    const data = await (await post('/order/create', {
      client_name: 'Status', client_phone: '79995550003',
      car_brand: 'Kia', car_model: 'Rio', service_ids: [1],
      desired_date: '2030-01-01', desired_time: '10:00', comment: 'test-status',
    })).json()
    orderId = data.order_id
    assert.ok(orderId, 'не удалось создать заказ для теста статусов')
  })

  const putStatus = (body, id = orderId) =>
    fetch(`${API}/admin/orders/${id}/status`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', Cookie: cookie },
      body: JSON.stringify(body),
    }).then((r) => r.json())

  // Без валидации отсутствующий status_id уходил в UPDATE как NULL: заказ терял
  // статус, отвечал success и пропадал из счётчиков дашборда (status_id IN (1,2,3)).
  for (const body of [{}, { status_id: null }, { status_id: 'abc' }]) {
    test(`${JSON.stringify(body)} -> отказ, статус не обнуляется`, async () => {
      const data = await putStatus(body)
      assert.ok(!data.success, `статус обнулён телом ${JSON.stringify(body)}`)
    })
  }

  test('несуществующий статус -> отказ, а не 500 по FK', async () => {
    const data = await putStatus({ status_id: 999999 })
    assert.ok(!data.success, 'принят несуществующий статус')
  })

  test('несуществующий заказ -> отказ, а не phantom success', async () => {
    const data = await putStatus({ status_id: 2 }, 999999)
    assert.ok(!data.success, 'success на несуществующем заказе')
  })

  test('валидный статус -> применяется', async () => {
    const data = await putStatus({ status_id: 2 })
    assert.ok(data.success, `валидный статус отклонён: ${JSON.stringify(data)}`)
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

describe('личный кабинет: чужой заказ недоступен (IDOR)', () => {
  // Владение проверяется через `AND client_id = :client_id` прямо в SQL каждого
  // метода — автоматического middleware нет. Уберут условие в одном запросе, и
  // любой клиент начнёт отменять/переносить чужие заказы, зная только order_id.
  let cookieA, cookieB, orderA

  const reg = (login, phone) =>
    post('/auth/register', {
      login, password: 'test123', first_name: login, last_name: 'IdorTest', phone,
    })

  before(async () => {
    // register падает на повторном прогоне (логин занят) — это норма, дальше
    // важен только успешный login с тем же паролем.
    await reg('idor-owner', '79995550021')
    await reg('idor-intruder', '79995550022')
    cookieA = await loginCookie('idor-owner', 'test123')
    cookieB = await loginCookie('idor-intruder', 'test123')

    // client_id заказа берётся из сессии владельца.
    const data = await (await post('/order/create', {
      service_ids: [1], car_brand: 'BMW', car_model: 'X5',
      desired_date: '2030-01-01', desired_time: '10:00', comment: 'test-idor-profile',
    }, cookieA)).json()
    orderA = data.order_id
    assert.ok(orderA, `не удалось создать заказ владельца: ${JSON.stringify(data)}`)
  })

  test('чужак не видит заказ в своём списке', async () => {
    const data = await (await fetch(API + '/user/orders', { headers: { Cookie: cookieB } })).json()
    assert.ok(!data.orders.some((o) => String(o.order_id) === String(orderA)), 'чужой заказ виден в списке')
  })

  test('чужак не может отменить заказ', async () => {
    const data = await (await post(`/user/orders/${orderA}/cancel`, {}, cookieB)).json()
    assert.ok(!data.success, 'чужак отменил заказ')
  })

  test('чужак не может перенести заказ', async () => {
    const data = await (await post(`/user/orders/${orderA}/reschedule`, {
      desired_date: '2030-06-06', desired_time: '12:00',
    }, cookieB)).json()
    assert.ok(!data.success, 'чужак перенёс заказ')
  })

  test('чужак не может смотреть фото заказа', async () => {
    const data = await (await fetch(`${API}/user/orders/${orderA}/photos`, { headers: { Cookie: cookieB } })).json()
    assert.ok(!data.success, 'чужак получил фото заказа')
  })

  test('после всех попыток заказ цел и не отменён', async () => {
    const data = await (await fetch(API + '/user/orders', { headers: { Cookie: cookieA } })).json()
    const order = data.orders.find((o) => String(o.order_id) === String(orderA))
    assert.ok(order, 'заказ владельца пропал')
    assert.notEqual(order.status_id, 5, 'заказ отменён чужаком')
  })

  // Контроль: без него тесты выше зелёные и на сломанной авторизации.
  test('КОНТРОЛЬ: владелец свой заказ перенести может', async () => {
    const data = await (await post(`/user/orders/${orderA}/reschedule`, {
      desired_date: '2030-07-07', desired_time: '15:00',
    }, cookieA)).json()
    assert.ok(data.success, `владельцу отказано: ${JSON.stringify(data)}`)
  })

  test('КОНТРОЛЬ: владелец свой заказ отменить может', async () => {
    const data = await (await post(`/user/orders/${orderA}/cancel`, {}, cookieA)).json()
    assert.ok(data.success, `владельцу отказано: ${JSON.stringify(data)}`)
  })
})

describe('смена пароля админа: пути отказа', () => {
  // Happy-path сознательно не покрыт: он менял бы пароль реального админа, и
  // падение теста на середине оставило бы стенд без доступа. Здесь — только
  // отказы, они ничего не меняют в БД.
  let cookie
  before(async () => { cookie = await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD) })

  const change = (body) => post('/admin/change-password', body, cookie).then((r) => r.json())

  test('неверный текущий пароль -> отказ', async () => {
    const data = await change({
      old_password: 'заведомо-неверный', new_password: 'newpass123', confirm_password: 'newpass123',
    })
    assert.ok(!data.success, 'пароль сменили без знания текущего')
  })

  test('новый пароль не совпадает с подтверждением -> отказ', async () => {
    const data = await change({
      old_password: env.ADMIN_PASSWORD, new_password: 'newpass123', confirm_password: 'другой456',
    })
    assert.ok(!data.success, 'приняты несовпадающие пароли')
  })

  test('слишком короткий новый пароль -> отказ', async () => {
    const data = await change({
      old_password: env.ADMIN_PASSWORD, new_password: 'abc', confirm_password: 'abc',
    })
    assert.ok(!data.success, 'принят пароль короче 6 символов')
  })

  test('пустые поля -> отказ', async () => {
    const data = await change({ old_password: '', new_password: '', confirm_password: '' })
    assert.ok(!data.success, 'приняты пустые поля')
  })

  test('старый пароль всё ещё рабочий (ничего не сменилось)', async () => {
    await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD)
  })
})

describe('HTTP-статусы: ошибка не должна приходить как 200', () => {
  // Контроллеры отдают ошибки через echo json_encode(['error' => ...]) и статус не
  // трогают — 400 проставляет роутер в одном месте. Тест держит это соглашение:
  // на 200 apiFetch не бросает, и ошибка молча выглядит как успешный ответ.
  test('неверный пароль -> 400, а не 200', async () => {
    const res = await post('/auth/login', { login: 'нет-такого', password: 'нет' })
    assert.equal(res.status, 400)
  })

  test('order/create без услуг -> 400, а не 200', async () => {
    const res = await post('/order/create', { client_name: 'X', client_phone: '79995550004' })
    assert.equal(res.status, 400)
  })

  test('auth/me без сессии -> 401 (не 400 и не 200)', async () => {
    const res = await fetch(API + '/auth/me')
    assert.equal(res.status, 401)
  })

  test('успешный ответ остаётся 200', async () => {
    const res = await fetch(API + '/services')
    assert.equal(res.status, 200)
  })

  test('HEAD на GET-роут -> 200, а не 404', async () => {
    const res = await fetch(API + '/services', { method: 'HEAD' })
    assert.equal(res.status, 200)
  })
})

describe('session-cookie: Secure по HTTPS без настройки в .env', () => {
  // На проде SESSION_COOKIE_SECURE в .env просто не оказалось, и кука на
  // HTTPS-сайте уходила без Secure — настройка проваливалась в небезопасную
  // сторону. Теперь схему определяет api/index.php сам; тест держит это.
  const cookieOf = (res) => (res.headers.getSetCookie?.() || []).join('; ')

  test('X-Forwarded-Proto: https -> Secure выставлен', async () => {
    const res = await fetch(API + '/services', { headers: { 'X-Forwarded-Proto': 'https' } })
    assert.match(cookieOf(res), /;\s*secure/i, 'кука по HTTPS ушла без Secure')
  })

  test('обычный HTTP -> Secure не выставлен (иначе dev-стенд не залогинится)', async () => {
    const res = await fetch(API + '/services')
    assert.doesNotMatch(cookieOf(res), /;\s*secure/i, 'Secure по HTTP сломает вход в dev')
  })

  test('X-Forwarded-Proto: http -> Secure не выставлен', async () => {
    const res = await fetch(API + '/services', { headers: { 'X-Forwarded-Proto': 'http' } })
    assert.doesNotMatch(cookieOf(res), /;\s*secure/i)
  })

  test('кука в любом случае HttpOnly и SameSite', async () => {
    const c = cookieOf(await fetch(API + '/services'))
    assert.match(c, /HttpOnly/i)
    assert.match(c, /SameSite/i)
  })
})

describe('order/create: отказ не оставляет клиента-сироту', () => {
  // Модель создавала клиента до проверки услуг: любой отказ ниже оставлял в БД
  // карточку без единого заказа, и аноним мог так засорять таблицу clients.
  const phone = '79995550005'

  test('отказ по услугам -> клиент не создан', async () => {
    const cookie = await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD)
    const before = await (await fetch(`${API}/admin/clients?search=${phone}`, { headers: { Cookie: cookie } })).json()
    assert.equal(before.total, 0, 'тестовый телефон уже занят — почисти БД')

    // service_id (единственное число) модель не читает: раньше запрос проходил
    // проверку контроллера и падал уже после findOrCreate клиента.
    await post('/order/create', {
      service_id: 1, client_name: 'Orphan', client_phone: phone,
      car_brand: 'BMW', car_model: 'X5',
    })

    const after = await (await fetch(`${API}/admin/clients?search=${phone}`, { headers: { Cookie: cookie } })).json()
    assert.equal(after.total, 0, 'отказ оставил клиента-сироту в БД')
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
