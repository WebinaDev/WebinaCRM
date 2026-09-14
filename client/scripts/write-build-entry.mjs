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

/** Resolve CSS from entry or imported chunks (Rolldown may attach CSS to _src-* chunks). */
function resolveCss(manifestObj, entryChunk) {
  if (Array.isArray(entryChunk.css) && entryChunk.css[0]) {
    return String(entryChunk.css[0]).replace(/^\//, '')
  }
  if (Array.isArray(entryChunk.imports)) {
    for (const key of entryChunk.imports) {
      const chunk = manifestObj[key]
      if (Array.isArray(chunk?.css) && chunk.css[0]) {
        return String(chunk.css[0]).replace(/^\//, '')
      }
    }
  }
  for (const chunk of Object.values(manifestObj)) {
    if (Array.isArray(chunk?.css) && chunk.css[0]) {
      return String(chunk.css[0]).replace(/^\//, '')
    }
  }
  return ''
}

const css = resolveCss(manifest, entry)

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
if (css) {
  console.log('[write-build-entry] compat aliases -> assets/index.css', basename(css))
} else {
  console.warn('[write-build-entry] WARNING: no CSS found in Vite manifest')
}

console.log('[write-build-entry]', payload)
