/**
 * Append missing webinocrm __() strings to fa_IR and en_US .po files, then compile .mo
 */
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'

const pluginRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const includesDir = path.join(pluginRoot, 'includes')
const langDir = path.join(pluginRoot, 'languages')

const re = /__\(\s*(['"])((?:\\.|(?!\1).)*)\1\s*,\s*['"]webinocrm['"]\s*\)/gs

function walkPhp(dir, out = []) {
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name)
    if (ent.isDirectory()) walkPhp(p, out)
    else if (ent.name.endsWith('.php')) out.push(p)
  }
  return out
}

function unescapePhp(s) {
  return s.replace(/\\'/g, "'").replace(/\\"/g, '"').replace(/\\\\/g, '\\')
}

const strings = new Set()
for (const file of walkPhp(includesDir)) {
  const src = fs.readFileSync(file, 'utf8')
  let m
  while ((m = re.exec(src))) {
    strings.add(unescapePhp(m[2]))
  }
}

function parsePo(content) {
  const ids = new Set()
  const reId = /^msgid "(.*)"$/gm
  let m
  while ((m = reId.exec(content))) {
    if (m[1]) ids.add(m[1].replace(/\\"/g, '"').replace(/\\n/g, '\n'))
  }
  return ids
}

function escPo(s) {
  return s.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n')
}

/** Very small Persian → English hints for new API/auth strings */
const enHints = {
  'شماره تلفن الزامی است.': 'Phone number is required.',
  'فرمت شماره موبایل صحیح نیست. (مثال: 09123456789)': 'Invalid mobile format (e.g. 09123456789).',
  'طول شماره موبایل باید %d رقم باشد.': 'Mobile number must be %d digits.',
  'خطا در ایجاد حساب کاربری. لطفاً مجدداً تلاش کنید.': 'Could not create account. Please try again.',
  'سرویس پیامک به درستی پیکربندی نشده است.': 'SMS service is not configured correctly.',
  'کد تایید به شماره موبایل شما ارسال شد.': 'Verification code sent to your mobile.',
  'خطا در ارسال پیامک. لطفاً بعداً تلاش کنید.': 'Failed to send SMS. Please try later.',
  'یک خطای سیستمی رخ داد.': 'A system error occurred.',
  'اطلاعات ناقص است.': 'Incomplete information.',
  'کد تایید منقضی شده است. لطفاً کد جدید درخواست کنید.': 'Code expired. Request a new code.',
  'تعداد تلاش‌های مجاز تمام شده است. لطفاً کد جدید درخواست کنید.': 'Too many attempts. Request a new code.',
  'کاربر یافت نشد.': 'User not found.',
  'ورود موفقیت‌آمیز بود.': 'Login successful.',
  'نام کاربری و رمز عبور الزامی است.': 'Username and password are required.',
  'نام کاربری، ایمیل، شماره موبایل یا کد ملی یافت نشد.': 'Username, email, mobile, or national ID not found.',
  'رمز عبور اشتباه است.': 'Incorrect password.',
  'ایمیل الزامی است.': 'Email is required.',
  'فرمت ایمیل صحیح نیست.': 'Invalid email format.',
  'کد تایید به ایمیل شما ارسال شد.': 'Verification code sent to your email.',
  'خطا در ارسال ایمیل. لطفاً بعداً تلاش کنید.': 'Failed to send email. Please try later.',
  'رمز عبور و تکرار آن الزامی است.': 'Password and confirmation are required.',
  'رمز عبور و تکرار آن یکسان نیست.': 'Passwords do not match.',
  'لایسنس‌های مارکت‌پلیس': 'Marketplace licenses',
}

function appendMissing(poPath, locale) {
  let content = fs.readFileSync(poPath, 'utf8')
  const existing = parsePo(content)
  const missing = [...strings].filter((s) => !existing.has(s)).sort()
  if (!missing.length) {
    console.log(`[sync-php-po] ${path.basename(poPath)}: nothing to add`)
    return
  }
  const block = missing
    .map((id) => {
      const msgstr =
        locale === 'fa'
          ? id
          : enHints[id] || (id.match(/[\u0600-\u06FF]/) ? id : id)
      return `\nmsgid "${escPo(id)}"\nmsgstr "${escPo(msgstr)}"\n`
    })
    .join('')
  content = content.trimEnd() + '\n' + block
  fs.writeFileSync(poPath, content, 'utf8')
  console.log(`[sync-php-po] ${path.basename(poPath)}: added ${missing.length} entries`)
}

appendMissing(path.join(langDir, 'webinocrm-fa_IR.po'), 'fa')
appendMissing(path.join(langDir, 'webinocrm-en_US.po'), 'en')

try {
  execSync(`msgfmt -o "${path.join(langDir, 'webinocrm-fa_IR.mo')}" "${path.join(langDir, 'webinocrm-fa_IR.po')}"`, {
    stdio: 'inherit',
  })
  execSync(`msgfmt -o "${path.join(langDir, 'webinocrm-en_US.mo')}" "${path.join(langDir, 'webinocrm-en_US.po')}"`, {
    stdio: 'inherit',
  })
  console.log('[sync-php-po] compiled .mo files')
} catch (e) {
  console.error('[sync-php-po] msgfmt failed', e.message)
  process.exit(1)
}
