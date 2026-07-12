/**
 * After Vite build: write build-entry.json + copy manifest.json (FTP often skips .vite/).
 */
import { copyFileSync, readFileSync, writeFileSync } from 'node:fs'
import { basename } from 'node:path'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const clientDir = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const buildDir = resolve(clientDir, '../assets/dashboard-build')
const viteManifest = resolve(buildDir, '.vite/manifest.json')

const manifest = JSON.parse(readFileSync(viteManifest, 'utf8'))
const entry = manifest['index.html'] ?? manifest['src/main.tsx']
if (!entry?.file) {
  console.error('[write-build-entry] No entry in manifest')
  process.exit(1)
}

const js = String(entry.file).replace(/^\//, '')
const css =
  Array.isArray(entry.css) && entry.css[0] ? String(entry.css[0]).replace(/^\//, '') : ''

let shared = ''
if (Array.isArray(entry.imports)) {
  for (const key of entry.imports) {
    if (typeof key === 'string' && key.includes('dashboard-shared')) {
      const chunk = manifest[key]
      if (chunk?.file) {
        shared = String(chunk.file).replace(/^\//, '')
        break
      }
    }
  }
}

const payload = { js, css, shared, builtAt: new Date().toISOString() }
writeFileSync(resolve(buildDir, 'build-entry.json'), JSON.stringify(payload, null, 2) + '\n', 'utf8')
copyFileSync(viteManifest, resolve(buildDir, 'manifest.json'))

const htaccess = `# Vite dev preview only — block direct access on Apache hosts.
<Files "index.html">
\t<IfModule mod_authz_core.c>
\t\tRequire all denied
\t</IfModule>
</Files>
`
writeFileSync(resolve(buildDir, '.htaccess'), htaccess, 'utf8')

// Compat aliases for stale full-page HTML cache still linking index.js / index.css.
const jsSrc = resolve(buildDir, js)
const jsAlias = resolve(buildDir, 'assets/index.js')
copyFileSync(jsSrc, jsAlias)
if (css) {
  const cssSrc = resolve(buildDir, css)
  copyFileSync(cssSrc, resolve(buildDir, 'assets/index.css'))
}
console.log('[write-build-entry] compat aliases -> assets/index.js', basename(js))

console.log('[write-build-entry]', payload)
