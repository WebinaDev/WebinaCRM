/**
 * Extract __('...', 'webinocrm') strings from PHP includes for .po maintenance.
 */
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../includes')
const re = /__\(\s*'((?:\\'|[^'])*)'\s*,\s*'webinocrm'\s*\)/g
const strings = new Set()

function walk(dir) {
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name)
    if (ent.isDirectory()) walk(p)
    else if (ent.name.endsWith('.php')) {
      const src = fs.readFileSync(p, 'utf8')
      let m
      while ((m = re.exec(src))) {
        strings.add(m[1].replace(/\\'/g, "'"))
      }
    }
  }
}

walk(root)
console.log([...strings].sort().join('\n'))
console.error(`\n# ${strings.size} strings`)
