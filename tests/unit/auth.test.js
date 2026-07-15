// Стор auth — единственный стор в проекте и единственный источник правды о том,
// залогинен ли пользователь (роутер сверяется с isAuthenticated).
//
// Покрыт потому, что уже ломался: logout не чистил user при упавшем бэкенде —
// apiFetch бросал, присваивание не выполнялось, и пользователь оставался
// «залогиненным» в UI при мёртвой сессии.
import { test, describe, expect, vi, beforeEach, afterEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '../../src/stores/auth.js'

const json = (body, status = 200) =>
  vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status }))

beforeEach(() => {
  setActivePinia(createPinia())
  vi.spyOn(console, 'error').mockImplementation(() => {})
})
afterEach(() => vi.unstubAllGlobals())

describe('login', () => {
  test('успех кладёт пользователя в стор', async () => {
    vi.stubGlobal('fetch', json({ success: true, user: { login: 'admin', role: 'admin' } }))
    const s = useAuthStore()
    expect(await s.login('admin', 'pw')).toEqual({ success: true })
    expect(s.user.role).toBe('admin')
    expect(s.isAuthenticated).toBe(true)
  })

  test('неверный пароль (400) -> текст ошибки наружу, стор пуст', async () => {
    vi.stubGlobal('fetch', json({ error: 'Неверный логин или пароль' }, 400))
    const s = useAuthStore()
    expect(await s.login('admin', 'bad')).toEqual({ success: false, error: 'Неверный логин или пароль' })
    expect(s.isAuthenticated).toBe(false)
  })

  test('сеть упала -> login не бросает наружу', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))
    const s = useAuthStore()
    const res = await s.login('admin', 'pw')
    expect(res.success).toBe(false)
    expect(s.isAuthenticated).toBe(false)
  })

  test('loading сбрасывается даже при ошибке', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('boom')))
    const s = useAuthStore()
    await s.login('admin', 'pw')
    expect(s.loading).toBe(false)
  })
})

describe('logout', () => {
  test('успех чистит пользователя', async () => {
    vi.stubGlobal('fetch', json({ success: true }))
    const s = useAuthStore()
    s.user = { login: 'admin' }
    await s.logout()
    expect(s.user).toBe(null)
  })

  // Тот самый баг: без catch/finally исключение улетало наружу, user оставался,
  // а router.push('/') у вызывающих не выполнялся.
  test('бэкенд отдал 500 -> пользователь всё равно разлогинен, наружу не бросает', async () => {
    vi.stubGlobal('fetch', json({ error: 'Внутренняя ошибка сервера' }, 500))
    const s = useAuthStore()
    s.user = { login: 'admin' }
    await s.logout()
    expect(s.user).toBe(null)
    expect(s.isAuthenticated).toBe(false)
  })

  test('сеть недоступна -> пользователь всё равно разлогинен', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))
    const s = useAuthStore()
    s.user = { login: 'admin' }
    await s.logout()
    expect(s.user).toBe(null)
  })
})

describe('checkAuth', () => {
  test('живая сессия -> пользователь восстановлен', async () => {
    vi.stubGlobal('fetch', json({ success: true, user: { login: 'admin', role: 'admin' } }))
    const s = useAuthStore()
    await s.checkAuth()
    expect(s.user.login).toBe('admin')
  })

  test('гость (401) -> не бросает, стор пуст', async () => {
    vi.stubGlobal('fetch', json({ error: 'Не авторизован' }, 401))
    const s = useAuthStore()
    await s.checkAuth()
    expect(s.isAuthenticated).toBe(false)
  })
})
