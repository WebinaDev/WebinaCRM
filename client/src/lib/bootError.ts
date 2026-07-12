/** Visible fallback when React cannot mount (avoids a blank white screen). */
export function showBootError(message: string, detail?: string) {
  const root = document.getElementById('root')
  if (!root) {
    return
  }

  const safeMsg = message.replace(/</g, '&lt;')
  const safeDetail = detail ? detail.replace(/</g, '&lt;') : ''

  root.innerHTML = `
    <div role="alert" style="box-sizing:border-box;min-height:100vh;padding:1.5rem;font-family:system-ui,sans-serif;background:#fef2f2;color:#7f1d1d;">
      <h1 style="margin:0 0 0.5rem;font-size:1.125rem;font-weight:600;">${safeMsg}</h1>
      ${safeDetail ? `<p style="margin:0 0 1rem;font-size:0.875rem;word-break:break-word;">${safeDetail}</p>` : ''}
      <button type="button" id="wd-boot-reload" style="padding:0.5rem 1rem;font-size:0.875rem;cursor:pointer;border:1px solid #b91c1c;border-radius:0.375rem;background:#fff;">
        Reload page
      </button>
    </div>
  `

  document.getElementById('wd-boot-reload')?.addEventListener('click', () => {
    window.location.reload()
  })
}
