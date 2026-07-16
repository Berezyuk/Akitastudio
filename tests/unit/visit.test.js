// Окно визита — единственная арифметика в сборе статистики, и соврать она может
// тихо: слишком короткое окно надувает визиты каждой перезагрузкой, слишком
// длинное — теряет их. В main.js эту логику из теста не позвать, поэтому она
// вынесена в отдельный модуль.
import { test, describe, expect } from 'vitest'
import { shouldTrackVisit, VISIT_WINDOW_MS } from '../../src/config/visit'

const NOW = 1_800_000_000_000

describe('shouldTrackVisit', () => {
  test('первый заход — метки нет', () => {
    expect(shouldTrackVisit(NOW, null)).toBe(true)
  })

  test('29 минут назад — тот же визит', () => {
    expect(shouldTrackVisit(NOW, NOW - 29 * 60 * 1000)).toBe(false)
  })

  test('31 минуту назад — новый визит', () => {
    expect(shouldTrackVisit(NOW, NOW - 31 * 60 * 1000)).toBe(true)
  })

  test('ровно на границе окна — новый визит', () => {
    expect(shouldTrackVisit(NOW, NOW - VISIT_WINDOW_MS)).toBe(true)
  })

  test('метка из localStorage приходит строкой', () => {
    expect(shouldTrackVisit(NOW, String(NOW - 29 * 60 * 1000))).toBe(false)
    expect(shouldTrackVisit(NOW, String(NOW - 31 * 60 * 1000))).toBe(true)
  })

  test('мусор в localStorage не ломает счётчик', () => {
    expect(shouldTrackVisit(NOW, 'сломали руками')).toBe(true)
    expect(shouldTrackVisit(NOW, '')).toBe(true)
  })
})
