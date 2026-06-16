/**
 * Пре-рендеринг публичных страниц после `npm run build`.
 * Запуск: npm run build:prerender
 *
 * Для каждого маршрута запускает headless Chrome, ждёт окончания рендера Vue,
 * и записывает итоговый HTML (с мета-тегами) в dist/<route>/index.html.
 */

import puppeteer from 'puppeteer'
import { createServer } from 'node:net'
import { execSync, spawn } from 'node:child_process'
import { writeFileSync, mkdirSync, readFileSync } from 'node:fs'
import { join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = fileURLToPath(new URL('.', import.meta.url))
const distDir = resolve(__dirname, '../dist')

const ROUTES = ['/', '/services', '/portfolio', '/about', '/contacts', '/booking']
const PORT = 4173

const findFreePort = () =>
  new Promise((res, rej) => {
    const srv = createServer()
    srv.listen(0, () => {
      const { port } = srv.address()
      srv.close(() => res(port))
    })
    srv.on('error', rej)
  })

const sleep = (ms) => new Promise((r) => setTimeout(r, ms))

async function main() {
  console.log('🚀 Запуск пре-рендеринга...')

  const port = PORT
  const serveProcess = spawn(
    'node',
    ['node_modules/.bin/serve', '-s', 'dist', '-l', String(port), '--no-clipboard'],
    { cwd: resolve(__dirname, '..'), stdio: 'ignore' }
  )

  await sleep(1500)

  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  })

  for (const route of ROUTES) {
    const url = `http://localhost:${port}${route}`
    console.log(`  → ${url}`)

    const page = await browser.newPage()
    await page.goto(url, { waitUntil: 'networkidle0', timeout: 30000 })
    await sleep(2000)

    const html = await page.content()
    await page.close()

    const outDir = join(distDir, route === '/' ? '' : route)
    mkdirSync(outDir, { recursive: true })
    writeFileSync(join(outDir, 'index.html'), html, 'utf-8')
    console.log(`    ✓ записан ${join(outDir, 'index.html')}`)
  }

  await browser.close()
  serveProcess.kill()

  console.log('✅ Пре-рендеринг завершён.')
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
