import { StrictMode, useCallback, useEffect, useMemo, useState } from "react"
import { createRoot } from "react-dom/client"
import "@/index.css"

type PublicPayload = {
  F: number
  p: number
  p_percent: number
  V_hat: number
  S_hat: number
  alpha: number
  T: number
  p_min: number
  p_max: number
  F_min: number
  services: Array<{ id: string; name: string; billing: string; description: string }>
  clause: string
}

type CatalogItem = {
  id: string
  name: string
  billing: string
  period_months: number
  renewable: boolean
  category: string
  description: string
  default_selected: boolean
}

type Boot = {
  token: string
  restUrl: string
  siteName: string
  homeUrl: string
  isRtl: boolean
}

declare global {
  interface Window {
    webinoRahnPublic?: Boot
  }
}

function money(n: number) {
  return Math.round(n).toLocaleString("fa-IR")
}

async function api<T>(path: string, init?: RequestInit): Promise<{ success: boolean; data?: T; message?: string }> {
  const boot = window.webinoRahnPublic!
  const res = await fetch(`${boot.restUrl}${path.replace(/^\//, "")}`, {
    ...init,
    headers: {
      "Content-Type": "application/json",
      ...(init?.headers || {}),
    },
  })
  const json = (await res.json()) as { success?: boolean; data?: T; message?: string; code?: string }
  // WP REST may wrap service results differently.
  if (json && typeof json === "object" && "success" in json) {
    return { success: !!json.success, data: json.data, message: json.message }
  }
  return { success: res.ok, data: json as T }
}

function App() {
  const boot = window.webinoRahnPublic!
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [title, setTitle] = useState("")
  const [locked, setLocked] = useState(false)
  const [catalog, setCatalog] = useState<CatalogItem[]>([])
  const [selected, setSelected] = useState<string[]>([])
  const [pub, setPub] = useState<PublicPayload | null>(null)
  const [pMin, setPMin] = useState(0.05)
  const [pMax, setPMax] = useState(0.25)
  const [sHat, setSHat] = useState(0)
  const [pPercent, setPPercent] = useState(10)
  const [mode, setMode] = useState<"from_p" | "from_f">("from_p")
  const [fWanted, setFWanted] = useState(0)
  const [name, setName] = useState("")
  const [phone, setPhone] = useState("")
  const [email, setEmail] = useState("")
  const [note, setNote] = useState("")
  const [submitted, setSubmitted] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await api<{
        title: string
        locked: boolean
        catalog: CatalogItem[]
        selected_ids: string[]
        public: PublicPayload
        p_min: number
        p_max: number
        s_hat: number
      }>(`rahn/public/${boot.token}`)
      if (!res.success || !res.data) {
        setError(res.message || "پیش‌نویس یافت نشد.")
        return
      }
      setTitle(res.data.title)
      setLocked(!!res.data.locked)
      setCatalog(res.data.catalog || [])
      setSelected(res.data.selected_ids || [])
      setPub(res.data.public)
      setPMin(res.data.p_min)
      setPMax(res.data.p_max)
      setSHat(res.data.s_hat)
      setPPercent(res.data.public.p_percent)
      setFWanted(res.data.public.F)
    } catch {
      setError("خطا در دریافت اطلاعات.")
    } finally {
      setLoading(false)
    }
  }, [boot.token])

  useEffect(() => {
    void load()
  }, [load])

  const recalc = useCallback(
    async (next: { selected?: string[]; pPercent?: number; fWanted?: number; mode?: "from_p" | "from_f" }) => {
      if (locked) return
      const body = {
        selected_ids: next.selected ?? selected,
        s_hat: sHat,
        mode: next.mode ?? mode,
        p_percent: next.pPercent ?? pPercent,
        F_wanted: next.fWanted ?? fWanted,
      }
      const res = await api<{ public: PublicPayload }>(`rahn/public/${boot.token}/calculate`, {
        method: "POST",
        body: JSON.stringify(body),
      })
      if (res.success && res.data?.public) {
        setPub(res.data.public)
        setPPercent(res.data.public.p_percent)
        setFWanted(res.data.public.F)
      }
    },
    [boot.token, selected, sHat, mode, pPercent, fWanted, locked],
  )

  const fMax = useMemo(
    () => Math.max((pub?.V_hat ?? sHat) * 1.5, (pub?.F_min ?? 0) * 2, fWanted * 1.2, 1),
    [pub, sHat, fWanted],
  )

  const submit = async () => {
    const res = await api<{ message?: string }>(`rahn/public/${boot.token}/submit`, {
      method: "POST",
      body: JSON.stringify({
        name,
        phone,
        email,
        note,
        selected_ids: selected,
        s_hat: sHat,
        mode,
        p_percent: pPercent,
        F_wanted: fWanted,
      }),
    })
    if (!res.success) {
      setError(res.message || "ثبت ناموفق بود.")
      return
    }
    setSubmitted(true)
  }

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center text-muted-foreground">
        در حال بارگذاری…
      </div>
    )
  }

  if (error && !pub) {
    return (
      <div className="min-h-screen flex items-center justify-center p-6">
        <div className="max-w-md w-full rounded-2xl border bg-card p-6 shadow-sm">{error}</div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-gradient-to-b from-violet-50 to-background py-10 px-4" dir="rtl">
      <div className="mx-auto max-w-3xl space-y-6">
        <header className="text-center space-y-2">
          <p className="text-sm text-muted-foreground">{boot.siteName}</p>
          <h1 className="text-2xl font-bold tracking-tight">{title || "پیشنهاد رهن‌درصد"}</h1>
          <p className="text-muted-foreground text-sm">
            ترکیب ثابت ماهانه و درصد از فروش — شفاف و قابل مذاکره
          </p>
        </header>

        <section className="rounded-2xl border bg-card p-5 shadow-sm space-y-3">
          <h2 className="font-semibold">خدمات انتخاب‌شده</h2>
          <div className="grid gap-2">
            {catalog.map((item) => {
              const checked = selected.includes(item.id)
              return (
                <label
                  key={item.id}
                  className={`flex items-start gap-3 rounded-xl border p-3 ${locked ? "opacity-70" : "hover:bg-muted/40 cursor-pointer"}`}
                >
                  <input
                    type="checkbox"
                    className="mt-1"
                    disabled={locked}
                    checked={checked}
                    onChange={(e) => {
                      const next = e.target.checked
                        ? [...selected, item.id]
                        : selected.filter((id) => id !== item.id)
                      setSelected(next)
                      void recalc({ selected: next })
                    }}
                  />
                  <div>
                    <div className="font-medium">{item.name}</div>
                    <div className="text-xs text-muted-foreground">
                      {item.category}
                      {item.description ? ` — ${item.description}` : ""}
                    </div>
                  </div>
                </label>
              )
            })}
          </div>
        </section>

        <section className="rounded-2xl border bg-card p-5 shadow-sm space-y-5">
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="rounded-xl bg-violet-50 p-4">
              <div className="text-xs text-muted-foreground">ثابت ماهانه</div>
              <div className="text-2xl font-bold mt-1">{money(pub?.F ?? 0)} تومان</div>
            </div>
            <div className="rounded-xl bg-emerald-50 p-4">
              <div className="text-xs text-muted-foreground">درصد از فروش</div>
              <div className="text-2xl font-bold mt-1">
                {(pub?.p_percent ?? 0).toLocaleString("fa-IR")}٪
              </div>
            </div>
          </div>

          {!locked ? (
            <>
              <div className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span>درصد</span>
                  <span className="font-semibold">{pPercent.toLocaleString("fa-IR")}٪</span>
                </div>
                <input
                  type="range"
                  className="w-full"
                  min={pMin * 100}
                  max={pMax * 100}
                  step={0.1}
                  value={pPercent}
                  onChange={(e) => {
                    const v = Number(e.target.value)
                    setMode("from_p")
                    setPPercent(v)
                    void recalc({ mode: "from_p", pPercent: v })
                  }}
                />
              </div>
              <div className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span>ثابت</span>
                  <span className="font-semibold">{money(fWanted)} تومان</span>
                </div>
                <input
                  type="range"
                  className="w-full"
                  min={0}
                  max={Math.ceil(fMax)}
                  step={100000}
                  value={Math.min(fMax, fWanted)}
                  onChange={(e) => {
                    const v = Number(e.target.value)
                    setMode("from_f")
                    setFWanted(v)
                    void recalc({ mode: "from_f", fWanted: v })
                  }}
                />
              </div>
            </>
          ) : (
            <p className="text-sm text-amber-700 bg-amber-50 rounded-lg p-3">
              این پیشنهاد قفل شده و قابل تغییر نیست.
            </p>
          )}

          {pub ? (
            <p className="text-xs text-muted-foreground">
              با فروش مبنای {money(pub.S_hat)}، هر ۱٪ = {money(pub.alpha)} تومان ثابت.
            </p>
          ) : null}

          <div className="rounded-xl border border-dashed p-4 text-sm leading-7 bg-muted/30">
            {pub?.clause}
          </div>

          <div className="rounded-xl border p-4">
            <div className="text-xs text-muted-foreground">برآورد درآمد ماهانه با فروش مبنا</div>
            <div className="text-xl font-semibold mt-1">{money(pub?.V_hat ?? 0)} تومان</div>
          </div>
        </section>

        <section className="rounded-2xl border bg-card p-5 shadow-sm space-y-3">
          <h2 className="font-semibold">درخواست مذاکره / شروع همکاری</h2>
          {submitted ? (
            <p className="text-emerald-700 bg-emerald-50 rounded-lg p-3 text-sm">
              درخواست شما ثبت شد. به‌زودی با شما تماس می‌گیریم.
            </p>
          ) : (
            <>
              <input
                className="w-full rounded-lg border px-3 py-2"
                placeholder="نام"
                value={name}
                onChange={(e) => setName(e.target.value)}
              />
              <input
                className="w-full rounded-lg border px-3 py-2"
                placeholder="موبایل"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
              />
              <input
                className="w-full rounded-lg border px-3 py-2"
                placeholder="ایمیل (اختیاری)"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
              <textarea
                className="w-full rounded-lg border px-3 py-2"
                placeholder="توضیح"
                rows={3}
                value={note}
                onChange={(e) => setNote(e.target.value)}
              />
              {error ? <p className="text-sm text-destructive">{error}</p> : null}
              <button
                type="button"
                className="w-full rounded-xl bg-violet-600 text-white py-3 font-medium hover:bg-violet-700"
                onClick={() => void submit()}
              >
                ارسال درخواست
              </button>
            </>
          )}
        </section>
      </div>
    </div>
  )
}

const el = document.getElementById("rahn-root")
if (el && window.webinoRahnPublic?.token) {
  createRoot(el).render(
    <StrictMode>
      <App />
    </StrictMode>,
  )
}
