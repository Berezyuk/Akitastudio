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
//         DELETE FROM clients WHERE phone_number LIKE '"'"'7999555%'"'"';
//         DELETE FROM visits;"'
import { test, describe, before } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { execSync } from 'node:child_process'

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
    '/admin/analytics',
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

  test('chart_data: 7 точек, числа, а не строки из PDO', async () => {
    const cookie = await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD)
    const data = await (await fetch(API + '/admin/dashboard', { headers: { Cookie: cookie } })).json()

    assert.equal(data.chart_data.labels.length, 7)
    assert.equal(data.chart_data.values.length, 7)
    assert.equal(data.chart_data.revenue.length, 7)
    // PDO отдаёт COUNT/SUM строками. Контроллер их приводит — если рефакторинг
    // приведение потеряет, JSON поедет со строками и тест поймает это здесь.
    for (const v of data.chart_data.values) assert.equal(typeof v, 'number')
    for (const r of data.chart_data.revenue) assert.equal(typeof r, 'number')
  })
})

describe('GET /admin/analytics', () => {
  let cookie
  before(async () => {
    cookie = await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD)
  })

  const get = (qs) => fetch(API + '/admin/analytics' + qs, { headers: { Cookie: cookie } })

  test('days=7 отдаёт ряд ровно на 7 точек', async () => {
    const data = await (await get('?days=7')).json()
    assert.ok(data.success)
    assert.equal(data.chart_data.labels.length, 7)
    assert.equal(data.chart_data.visits.length, 7)
    assert.equal(data.chart_data.uniques.length, 7)
  })

  test('ряд отдаётся числами, а не строками из PDO', async () => {
    const data = await (await get('?days=7')).json()
    for (const v of data.chart_data.visits) assert.equal(typeof v, 'number')
    for (const u of data.chart_data.uniques) assert.equal(typeof u, 'number')
    assert.equal(typeof data.today.visits, 'number')
    assert.equal(typeof data.today.uniques, 'number')
  })

  test('days=30 отдаёт ряд ровно на 30 точек', async () => {
    const data = await (await get('?days=30')).json()
    assert.equal(data.chart_data.labels.length, 30)
  })

  test('days вне whitelist -> 400, а не подстановка в SQL', async () => {
    const res = await get('?days=99')
    assert.equal(res.status, 400)
  })

  test('без days работает как days=7', async () => {
    const data = await (await get('')).json()
    assert.equal(data.chart_data.labels.length, 7)
  })

  test('top_hosts отдаётся массивом и не содержит прямых заходов', async () => {
    const data = await (await get('?days=7')).json()
    assert.ok(Array.isArray(data.top_hosts))
    // У direct нет хоста (referer_host IS NULL) — в топ сайтов ему попадать нечем.
    for (const row of data.top_hosts) assert.ok(row.referer_host)
  })
})

// Beacon визитов. Referer из заголовка тут бесполезен (на fetch из SPA он равен
// URL текущей страницы), поэтому источник приходит в теле — см. спеку.
// Каждый тест берёт свой X-Real-IP: hash = f(ip, ua), значит своя корзина
// антинакрутки и никакого влияния тестов друг на друга.
describe('POST /visit: сбор визитов', () => {
  let cookie

  const visit = (body, headers = {}) =>
    fetch(API + '/visit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...headers },
      body: JSON.stringify(body),
    })

  const analytics = async () =>
    (await fetch(API + '/admin/analytics?days=7', { headers: { Cookie: cookie } })).json()

  const countBy = (rows, key, val) => Number(rows?.find((r) => r[key] === val)?.count ?? 0)

  const UA_DESKTOP = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36'
  const UA_MOBILE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148'

  before(async () => {
    cookie = await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD)
  })

  test('отвечает 204 и увеличивает счётчик визитов', async () => {
    const before = await analytics()
    const res = await visit({ referrer: '' }, { 'X-Real-IP': '198.51.100.10', 'User-Agent': UA_DESKTOP })
    assert.equal(res.status, 204)
    const after = await analytics()
    assert.equal(after.today.visits - before.today.visits, 1)
  })

  test('переход с поисковика -> source=search', async () => {
    const before = await analytics()
    await visit(
      { referrer: 'https://yandex.ru/search/?text=детейлинг' },
      { 'X-Real-IP': '198.51.100.11', 'User-Agent': UA_DESKTOP }
    )
    const after = await analytics()
    assert.equal(countBy(after.sources, 'source', 'search') - countBy(before.sources, 'source', 'search'), 1)
  })

  test('пустой referrer -> source=direct', async () => {
    const before = await analytics()
    await visit({ referrer: '' }, { 'X-Real-IP': '198.51.100.12', 'User-Agent': UA_DESKTOP })
    const after = await analytics()
    assert.equal(countBy(after.sources, 'source', 'direct') - countBy(before.sources, 'source', 'direct'), 1)
  })

  test('переход со своего же сайта -> source=direct, а не other', async () => {
    const own = env.CORS_ORIGIN
    const before = await analytics()
    await visit({ referrer: `${own}/services` }, { 'X-Real-IP': '198.51.100.13', 'User-Agent': UA_DESKTOP })
    const after = await analytics()
    assert.equal(countBy(after.sources, 'source', 'direct') - countBy(before.sources, 'source', 'direct'), 1)
  })

  test('iPhone -> device=mobile', async () => {
    const before = await analytics()
    await visit({ referrer: '' }, { 'X-Real-IP': '198.51.100.14', 'User-Agent': UA_MOBILE })
    const after = await analytics()
    assert.equal(countBy(after.devices, 'device', 'mobile') - countBy(before.devices, 'device', 'mobile'), 1)
  })

  test('десктопный User-Agent -> device=desktop', async () => {
    const before = await analytics()
    await visit({ referrer: '' }, { 'X-Real-IP': '198.51.100.15', 'User-Agent': UA_DESKTOP })
    const after = await analytics()
    assert.equal(countBy(after.devices, 'device', 'desktop') - countBy(before.devices, 'device', 'desktop'), 1)
  })

  // Потолок — MAX_PER_HOUR в контроллере, сейчас 300. Хеш ответа всегда 204
  // (и при отказе тоже — накрутчику не сообщаем, где предел), поэтому "записалось
  // или нет" проверяем только по разнице today.visits, не по HTTP-статусу.
  // UA внутри цикла заполнения нарочно каждый раз новый: потолок общий на IP
  // (ip_hash), а не на пару (IP, UA) — до фикса ротация UA его обходила.
  // 301 запрос — медленный тест (~секунды), но короче не проверить границу
  // реальной константы: занижать MAX_PER_HOUR ради теста нельзя.
  // IP — случайный, не из статичного пула остальных тестов файла: корзина
  // ip_hash копится по часу, и повторный прогон в пределах того же часа
  // на фиксированном IP застал бы её уже заполненной прошлым прогоном.
  test('потолок: 300 визитов с одного IP проходят, 301-й — нет, ротация User-Agent не спасает', async () => {
    const ip = `198.51.100.30-${crypto.randomUUID()}`
    const before = await analytics()
    for (let i = 0; i < 300; i++) {
      await visit({ referrer: '' }, { 'X-Real-IP': ip, 'User-Agent': `bench-ua-${i}` })
    }
    const afterFill = await analytics()
    assert.equal(afterFill.today.visits - before.today.visits, 300, 'не все 300 визитов в пределах потолка записались')

    // Лимит исчерпан. Новый, ранее не встречавшийся User-Agent с того же IP —
    // ровно сценарий обхода из ревью (curl с ротацией UA).
    const res = await visit({ referrer: '' }, { 'X-Real-IP': ip, 'User-Agent': 'brand-new-rotated-ua' })
    assert.equal(res.status, 204)
    const afterOver = await analytics()
    assert.equal(
      afterOver.today.visits - before.today.visits,
      300,
      'визит сверх потолка записался — ротация User-Agent обошла лимит'
    )
  })

  test('хост реферера длиннее 255 -> 204, а не 500, и без записи в top_hosts', async () => {
    const longHost = 'a'.repeat(300) + '.com'
    const before = await analytics()
    const res = await visit(
      { referrer: `https://${longHost}/x` },
      { 'X-Real-IP': '198.51.100.31', 'User-Agent': UA_DESKTOP }
    )
    assert.equal(res.status, 204)
    const after = await analytics()
    assert.ok(!after.top_hosts.some((r) => r.referer_host === longHost), 'слишком длинный хост попал в top_hosts')
  })

  test('мусор в теле запроса -> 204, без падения', async () => {
    const ip = '198.51.100.32'
    const garbage = [
      'не json',
      JSON.stringify({ referrer: 12345 }),
      JSON.stringify({ referrer: null }),
      JSON.stringify({ referrer: ['a', 'b'] }),
    ]
    for (const rawBody of garbage) {
      const res = await fetch(API + '/visit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Real-IP': ip, 'User-Agent': UA_DESKTOP },
        body: rawBody,
      })
      assert.equal(res.status, 204, `тело ${rawBody} уронило запрос`)
    }
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

describe('заголовки: версия PHP наружу не уходит', () => {
  // expose_php живёт в docker/php/php.ini, а тот КОПИРУЕТСЯ в образ. Правка без
  // --build не доезжает молча (на этом уже теряли session.cookie_secure на месяц),
  // поэтому проверка идёт по живому ответу, а не по файлу.
  test('X-Powered-By отсутствует', async () => {
    const res = await fetch(API + '/services')
    assert.equal(res.headers.get('x-powered-by'), null, 'сервер сообщает версию PHP')
  })

  test('и на ошибках тоже', async () => {
    const res = await fetch(API + '/auth/me')
    assert.equal(res.headers.get('x-powered-by'), null)
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

describe('загрузка видео: отказ перекодирования не пропускает сырьё', () => {
  // Фикстура: настоящий ftyp-заголовок (finfo распознаёт как video/mp4 и пускает
  // через проверку MIME), дальше мусор — ffmpeg разобрать не может.
  // transcodeToH264() вернёт null. До фикса контроллер в этом случае молча
  // заливал сырой оригинал в MinIO и отвечал success: true.
  test('видео, которое ffmpeg не смог перекодировать -> отказ, ничего не залито', async () => {
    const cookie = await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD)

    const bytes = readFileSync(new URL('./fixtures/broken-video.mp4', import.meta.url))
    const form = new FormData()
    form.append('media', new Blob([bytes], { type: 'video/mp4' }), 'broken-video.mp4')

    const res = await fetch(API + '/admin/portfolio/upload', {
      method: 'POST',
      headers: { Cookie: cookie },
      body: form,
    })
    const data = await res.json()

    assert.equal(res.status, 400, `ожидали отказ, а не тихую заливку сырья: ${JSON.stringify(data)}`)
    assert.ok(!data.success, `success: true при неудавшемся перекодировании: ${JSON.stringify(data)}`)
    assert.ok(!data.url, `в ответе есть url — значит сырьё всё же уехало в хранилище: ${JSON.stringify(data)}`)
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

// Троттлинг логина считает попытки по IP (RateLimiter::ip() -> HTTP_X_REAL_IP).
// Контейнерный nginx затирал этот заголовок адресом докер-шлюза, и корзина
// становилась общей на всех: 10 неудачных попыток кого угодно блокировали вход
// остальным. Тест ловит именно это — на откаченном фиксе IP-2 получает 429.
//
// ⚠️  Если тест падает, корзина докер-шлюза засорена на 15 минут и остальные
//     тесты с логином админа получат 429. Очистка:
//     docker compose exec -T postgres sh -c 'psql -U $POSTGRES_USER -d $POSTGRES_DB -c "DELETE FROM login_attempts;"'
describe('троттлинг логина: корзины по IP не общие', () => {
  const badLogin = (ip) =>
    fetch(API + '/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Real-IP': ip },
      body: JSON.stringify({ login: 'nosuchuser', password: 'wrongpassword' }),
    })

  test('исчерпание лимита одним IP не блокирует другой', async () => {
    // RateLimiter::MAX_ATTEMPTS = 10 за 15 минут
    for (let i = 0; i < 11; i++) await badLogin('198.51.100.1')

    const throttled = await badLogin('198.51.100.1')
    assert.equal(throttled.status, 429, 'лимит по своему же IP не сработал')

    const other = await badLogin('198.51.100.2')
    assert.notEqual(other.status, 429, 'чужой IP получил 429 — корзина общая на всех')
  })
})

// Видео портфолио показываются в карточке шириной максимум 320 CSS-пикселей,
// без звука и без контролов. До этого фикса FfmpegHelper не масштабировал их
// вовсе: в бакет уезжало 1080x1920 на 4.7-7.2 Мбит/с, по 12-22 МБ за штуку —
// больше типичного мобильного канала. Тест ловит откат параметров обратно.
describe('загрузка видео: перекодирование под мобильный канал', () => {
  let cookie
  before(async () => {
    cookie = await loginCookie(env.ADMIN_LOGIN, env.ADMIN_PASSWORD)
  })

  // ffprobe живёт в php-контейнере, на хосте его нет. Внутри контейнера
  // localhost:9000 — его собственный localhost, а не MinIO, поэтому ходим
  // по имени сервиса из compose-сети.
  const probe = (key, args) =>
    execSync(
      `docker compose exec -T php ffprobe -v error ${args} "http://minio:9000/${key}"`,
      { encoding: 'utf8' }
    ).trim()

  test('1080x1920 ужимается до ширины <=720 и лишается звука', async () => {
    const file = readFileSync(new URL('./fixtures/portrait-1080x1920.mp4', import.meta.url))
    const form = new FormData()
    form.append('media', new Blob([file], { type: 'video/mp4' }), 'portrait.mp4')

    const res = await fetch(API + '/admin/portfolio/upload', {
      method: 'POST',
      headers: { Cookie: cookie },
      body: form,
    })
    const data = await res.json()
    assert.ok(data.success, `загрузка не удалась: ${JSON.stringify(data)}`)

    const key = new URL(data.url).pathname.replace(/^\//, '')

    const width = probe(key, '-select_streams v:0 -show_entries stream=width -of csv=p=0')
    assert.ok(Number(width) <= 720, `ширина ${width} > 720 — scale не применился`)

    const audio = probe(key, '-select_streams a -show_entries stream=codec_type -of csv=p=0')
    assert.equal(audio, '', 'звуковая дорожка осталась — -an не применился')
  })

  // scale='min(720,iw)':-2 — -2 авто-выравнивает только вычисляемую сторону
  // (высоту), а явно заданная ширина min(720,iw) чётности не гарантирует.
  // Если исходник уже ⩽720 и с нечётной шириной, min(720,iw) возвращает эту
  // нечётную ширину как есть, yuv420p требует чётности по обеим осям —
  // ffmpeg падает ("width not divisible by 2"), transcodeToH264() отдаёт
  // null, и все три контроллера в этом случае молча заливают необработанный
  // оригинал (без ресайза, без потолка битрейта, со звуком).
  test('нечётная ширина 201x357 не валит перекодирование — результат чётный и без звука', async () => {
    const file = readFileSync(new URL('./fixtures/odd-width-201x357.mp4', import.meta.url))
    const form = new FormData()
    form.append('media', new Blob([file], { type: 'video/mp4' }), 'odd.mp4')

    const res = await fetch(API + '/admin/portfolio/upload', {
      method: 'POST',
      headers: { Cookie: cookie },
      body: form,
    })
    const data = await res.json()
    assert.ok(data.success, `загрузка не удалась: ${JSON.stringify(data)}`)

    const key = new URL(data.url).pathname.replace(/^\//, '')

    const width = probe(key, '-select_streams v:0 -show_entries stream=width -of csv=p=0')
    assert.ok(Number(width) % 2 === 0, `ширина ${width} нечётная — scale не чинит чётность`)
    assert.ok(Number(width) <= 720, `ширина ${width} > 720 — scale не применился`)

    const audio = probe(key, '-select_streams a -show_entries stream=codec_type -of csv=p=0')
    assert.equal(audio, '', 'звуковая дорожка осталась — transcodeToH264 упал, залился необработанный оригинал')
  })
})
