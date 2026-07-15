// ThePagination — чистая арифметика страниц, общая для четырёх админ-вью.
// Ошибка тут врёт про размер выборки во всей админке сразу.
import { test, describe, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ThePagination from '../../src/components/ThePagination.vue'

const make = (props) => mount(ThePagination, { props: { limit: 50, ...props } })
const range = (w) => w.find('span').text()
const pageButtons = (w) =>
  w.findAll('button').map((b) => b.text()).filter((t) => /^\d+$/.test(t))

describe('диапазон «X–Y из N»', () => {
  test('первая страница', () => {
    expect(range(make({ page: 1, total: 60 }))).toBe('1–50 из 60')
  })

  test('последняя страница обрезается по total, а не по limit', () => {
    expect(range(make({ page: 2, total: 60 }))).toBe('51–60 из 60')
  })

  test('ровно одна полная страница', () => {
    expect(range(make({ page: 1, total: 50 }))).toBe('1–50 из 50')
  })

  test('одна запись', () => {
    expect(range(make({ page: 1, total: 1 }))).toBe('1–1 из 1')
  })

  test('пустая выборка — пагинатор скрыт целиком', () => {
    expect(make({ page: 1, total: 0 }).find('span').exists()).toBe(false)
  })
})

describe('номера страниц', () => {
  test('до 7 страниц — показываются все подряд', () => {
    expect(pageButtons(make({ page: 1, total: 350 }))).toEqual(['1', '2', '3', '4', '5', '6', '7'])
  })

  test('много страниц — первая, последняя и окно вокруг текущей', () => {
    // 20 страниц, стоим на 10-й: 1 … 9 10 11 … 20
    expect(pageButtons(make({ page: 10, total: 1000 }))).toEqual(['1', '9', '10', '11', '20'])
  })

  test('номер страницы не вылезает за границы', () => {
    const btns = pageButtons(make({ page: 20, total: 1000 }))
    expect(btns.at(-1)).toBe('20')
    expect(btns).not.toContain('21')
  })
})

describe('навигация', () => {
  test('«назад» заблокирована на первой странице', () => {
    expect(make({ page: 1, total: 100 }).findAll('button')[0].attributes('disabled')).toBeDefined()
  })

  test('«вперёд» заблокирована на последней', () => {
    const w = make({ page: 2, total: 100 })
    expect(w.findAll('button').at(-1).attributes('disabled')).toBeDefined()
  })

  test('клик по номеру эмитит update:page', async () => {
    const w = make({ page: 1, total: 100 })
    await w.findAll('button').find((b) => b.text() === '2').trigger('click')
    expect(w.emitted('update:page')).toEqual([[2]])
  })
})
