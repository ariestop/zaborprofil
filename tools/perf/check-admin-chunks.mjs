import { statSync, readFileSync, existsSync, appendFileSync } from 'node:fs'
import path from 'node:path'

const projectRoot = process.cwd()
const manifestPath = path.resolve(projectRoot, 'public_html/build/.vite/manifest.json')
const baselinePath = path.resolve(projectRoot, 'tools/perf/admin-chunk-baseline.json')

// admin-initial — весь JS, который браузер грузит при открытии любой страницы админки: entry и его статические
// импорты (без ленивых маршрутов). Ловит случай, когда в начальную загрузку попадает тяжёлый чанк
// (например, редактор: vendor-tiptap + vendor-prosemirror), хотя каждый чанк по отдельности в бюджете.
const budgets = [
  { name: 'admin-initial', maxBytes: 520 * 1024, initialOf: 'admin/app.ts' },
  { name: 'admin-entry', maxBytes: 300 * 1024, patterns: [/\/admin-[^/]+\.js$/] },
  { name: 'vendor-react', maxBytes: 210 * 1024, patterns: [/\/vendor-react-[^/]+\.js$/] },
  { name: 'page-editor-route', maxBytes: 120 * 1024, patterns: [/\/PageEditorPage-[^/]+\.js$/] },
  { name: 'rich-text-runtime', maxBytes: 200 * 1024, patterns: [/\/RichTextEditor-[^/]+\.js$/] },
  { name: 'vendor-tiptap', maxBytes: 280 * 1024, patterns: [/\/vendor-tiptap-[^/]+\.js$/] },
  { name: 'vendor-prosemirror', maxBytes: 370 * 1024, patterns: [/\/vendor-prosemirror-[^/]+\.js$/] },
]

const manifestRaw = readFileSync(manifestPath, 'utf8')
const manifest = JSON.parse(manifestRaw)
const baseline = existsSync(baselinePath)
  ? JSON.parse(readFileSync(baselinePath, 'utf8'))
  : {}

/** @type {Set<string>} */
const emittedFiles = new Set()
for (const entry of Object.values(manifest)) {
  if (entry && typeof entry === 'object' && typeof entry.file === 'string') {
    emittedFiles.add(entry.file)
  }
}

let hasFailures = false
const rows = []
console.log('Admin chunk budget report:')

/**
 * Файлы entry и всех его статических импортов (рекурсивно); dynamicImports не входят.
 *
 * @param {string} entryKey
 * @returns {string[]}
 */
function initialFiles(entryKey) {
  const entry = manifest[entryKey]
  if (!entry || typeof entry.file !== 'string') {
    return []
  }

  const files = new Set([entry.file])
  const visited = new Set()
  const queue = Array.isArray(entry.imports) ? [...entry.imports] : []
  while (queue.length > 0) {
    const key = queue.shift()
    if (visited.has(key) || !manifest[key]) {
      continue
    }
    visited.add(key)
    if (typeof manifest[key].file === 'string' && manifest[key].file.endsWith('.js')) {
      files.add(manifest[key].file)
    }
    if (Array.isArray(manifest[key].imports)) {
      queue.push(...manifest[key].imports)
    }
  }

  return Array.from(files)
}

for (const budget of budgets) {
  const matched = budget.initialOf
    ? initialFiles(budget.initialOf)
    : Array.from(emittedFiles).filter((file) => budget.patterns.some((pattern) => pattern.test(file)))

  const totalSize = matched.reduce((sum, relativeFile) => {
    const absolutePath = path.resolve(projectRoot, 'public_html/build', relativeFile)
    return sum + statSync(absolutePath).size
  }, 0)

  const limitLabel = `${Math.round(budget.maxBytes / 1024)}KB`
  const sizeLabel = `${Math.round(totalSize / 1024)}KB`
  const marker = totalSize <= budget.maxBytes ? 'OK' : 'FAIL'
  const baselineBytes = typeof baseline[budget.name] === 'number' ? baseline[budget.name] : null
  const deltaBytes = baselineBytes === null ? null : totalSize - baselineBytes
  const deltaLabel = deltaBytes === null
    ? 'n/a'
    : `${deltaBytes >= 0 ? '+' : ''}${Math.round(deltaBytes / 1024)}KB`

  console.log(` - [${marker}] ${budget.name}: ${sizeLabel} / ${limitLabel}`)
  rows.push({
    chunk: budget.name,
    status: marker,
    size: sizeLabel,
    limit: limitLabel,
    delta: deltaLabel,
  })
  if (matched.length === 0) {
    hasFailures = true
    console.log(`   Missing expected chunk(s): ${budget.initialOf ?? budget.patterns.map((p) => p.toString()).join(', ')}`)
    console.error(`::error title=Missing chunk::${budget.name} is not found in Vite manifest.`)
    continue
  }

  if (totalSize > budget.maxBytes) {
    hasFailures = true
    console.log(`   Files: ${matched.join(', ')}`)
    console.error(`::error title=Chunk budget exceeded::${budget.name} is ${sizeLabel} but limit is ${limitLabel}.`)
  }

  if (deltaBytes !== null && deltaBytes > 20 * 1024) {
    console.log(`   Hint: ${budget.name} grew by ${deltaLabel} vs baseline; inspect imports/manualChunks.`)
  }
}

const markdownLines = [
  '### Admin chunk budget report',
  '',
  '| Chunk | Status | Size | Limit | Delta vs baseline |',
  '| --- | --- | --- | --- | --- |',
  ...rows.map((row) => `| ${row.chunk} | ${row.status} | ${row.size} | ${row.limit} | ${row.delta} |`),
  '',
]
console.log('\n' + markdownLines.join('\n'))

if (process.env.GITHUB_STEP_SUMMARY) {
  appendFileSync(process.env.GITHUB_STEP_SUMMARY, markdownLines.join('\n') + '\n')
}

if (hasFailures) {
  process.exitCode = 1
  console.error('Chunk budget check failed.')
} else {
  console.log('Chunk budget check passed.')
}
