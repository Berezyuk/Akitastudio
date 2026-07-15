// apiFetch — единственная обёртка над fetch. Стенд не нужен: fetch мокается.
//
// Почему это вообще покрыто: бэкенд отдаёт ошибки как JSON с полем error, а
// отличить отказ от успеха можно только по статусу. Если apiFetch перестанет
// бросать на 4xx, любой вызов молча примет ошибку за успешный ответ.
import { test, describe, expect, vi, afterEach } from 'vitest'
import { apiFetch, API_BASE } from '../../src/config/api.js'

const mockFetch = (body, init = {}) => {
  const fn = vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status: 200, ...init }))
  vi.stubGlobal('fetch', fn)
  return fn
}

afterEach(() => vi.unstubAllGlobals())

describe('apiFetch', () => {
  test('возвращает разобранный JSON на 200', async () => {
    mockFetch({ success: true, services: [1, 2] })
    expect(await apiFetch('/services')).toEqual({ success: true, services: [1, 2] })
  })

  test('бросает на 4xx с текстом ошибки от бэкенда', async () => {
    mockFetch({ error: 'Неверный логин или пароль' }, { status: 400 })
    await expect(apiFetch('/auth/login')).rejects.toThrow('Неверный логин или пароль')
  })

  test('бросает на 401', async () => {
    mockFetch({ error: 'Не авторизован' }, { status: 401 })
    await expect(apiFetch('/auth/me')).rejects.toThrow('Не авторизован')
  })

  test('бросает на 5xx даже без тела', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('', { status: 500 })))
    await expect(apiFetch('/services')).rejects.toThrow('HTTP 500')
  })

  test('шлёт cookie-сессию и JSON-заголовок', async () => {
    const fn = mockFetch({ success: true })
    await apiFetch('/services')
    const [url, opts] = fn.mock.calls[0]
    expect(url).toBe(`${API_BASE}/services`)
    expect(opts.credentials).toBe('include')
    expect(opts.headers['Content-Type']).toBe('application/json')
  })

  test('свои заголовки не затирают Content-Type', async () => {
    const fn = mockFetch({ success: true })
    await apiFetch('/x', { headers: { 'X-Custom': '1' } })
    const [, opts] = fn.mock.calls[0]
    expect(opts.headers['Content-Type']).toBe('application/json')
    expect(opts.headers['X-Custom']).toBe('1')
  })
})
