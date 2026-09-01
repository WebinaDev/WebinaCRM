import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Textarea } from "@/components/ui/textarea"
import {
  deleteModirPayamakDomainSecretary,
  getModirPayamakCustomers,
  getModirPayamakDomainSecretaries,
  saveModirPayamakDomainSecretary,
} from "@/api/modirpayamak"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { ModirPayamakBreadcrumb } from "./components/ModirPayamakBreadcrumb"

const TYPES = ["auto_reply", "inbox_forward", "code_reader", "membership"] as const

export function ModirPayamakSecretariesPage() {
  const { t } = useLocale()
  const { layoutProps, setError, applyResponse } = useCrmFeedback()
  const [domain, setDomain] = useState("")
  const [domains, setDomains] = useState<string[]>([])
  const [rows, setRows] = useState<Array<Record<string, unknown>>>([])
  const [loading, setLoading] = useState(false)
  const [name, setName] = useState("")
  const [type, setType] = useState<(typeof TYPES)[number]>("auto_reply")
  const [keywords, setKeywords] = useState("*")
  const [replyBody, setReplyBody] = useState("")
  const [forwardTo, setForwardTo] = useState("")

  const loadDomains = async () => {
    const res = await getModirPayamakCustomers()
    if (res.success && res.data?.accounts) {
      setDomains(res.data.accounts.map((a) => a.domain).filter(Boolean))
    }
  }

  const load = async (d: string) => {
    if (!d.trim()) return
    setLoading(true)
    const res = await getModirPayamakDomainSecretaries(d.trim())
    setLoading(false)
    if (applyResponse(res)) {
      setRows(res.data?.secretaries ?? [])
    }
  }

  const remove = async (id: number) => {
    const res = await deleteModirPayamakDomainSecretary(domain, id)
    if (applyResponse(res, { successMessage: t("common.deleted") })) {
      void load(domain)
    }
  }

  const addRule = async () => {
    if (!domain.trim()) {
      setError(t("pages.modirpayamak.domainRequired"))
      return
    }
    if (!name.trim()) {
      setError(t("pages.modirpayamak.secretaryNameRequired"))
      return
    }
    const res = await saveModirPayamakDomainSecretary({
      domain: domain.trim(),
      type,
      name: name.trim(),
      keywords: keywords.trim() || "*",
      reply_body: replyBody,
      forward_to: forwardTo.trim() || undefined,
      enabled: true,
    })
    if (applyResponse(res, { successMessage: t("common.saved") })) {
      setName("")
      setReplyBody("")
      setForwardTo("")
      setKeywords("*")
      void load(domain)
    }
  }

  const typeLabel = (key: string) => {
    const i18nKey = `pages.modirpayamak.secretaryTypes.${key}`
    const translated = t(i18nKey)
    return translated === i18nKey ? key : translated
  }

  return (
    <CrmPageLayout title={t("pages.modirpayamak.secretariesTitle")} {...layoutProps}>
      <ModirPayamakBreadcrumb current={t("pages.modirpayamak.secretariesTitle")} />
      <div className="mb-4 flex flex-wrap items-end gap-2">
        <div>
          <Label>{t("pages.modirpayamak.domain")}</Label>
          <Input
            className="mt-1 min-w-[220px]"
            list="mp-sec-domains"
            value={domain}
            onFocus={() => void loadDomains()}
            onChange={(e) => setDomain(e.target.value)}
          />
          <datalist id="mp-sec-domains">
            {domains.map((d) => (
              <option key={d} value={d} />
            ))}
          </datalist>
        </div>
        <Button type="button" disabled={loading} onClick={() => void load(domain)}>
          {t("common.refresh")}
        </Button>
      </div>

      <div className="mb-6 grid max-w-2xl gap-3 rounded-lg border p-4">
        <p className="text-sm font-medium">{t("pages.modirpayamak.newSecretary")}</p>
        <div className="grid gap-3 sm:grid-cols-2">
          <div>
            <Label>{t("pages.modirpayamak.secretaryName")}</Label>
            <Input className="mt-1" value={name} onChange={(e) => setName(e.target.value)} />
          </div>
          <div>
            <Label>{t("pages.modirpayamak.secretaryType")}</Label>
            <Select value={type} onValueChange={(v) => setType(v as (typeof TYPES)[number])}>
              <SelectTrigger className="mt-1">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {TYPES.map((key) => (
                  <SelectItem key={key} value={key}>
                    {typeLabel(key)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>
        <div>
          <Label>{t("pages.modirpayamak.keywords")}</Label>
          <Input className="mt-1" value={keywords} onChange={(e) => setKeywords(e.target.value)} />
        </div>
        <div>
          <Label>{t("pages.modirpayamak.replyBody")}</Label>
          <Textarea className="mt-1" rows={3} value={replyBody} onChange={(e) => setReplyBody(e.target.value)} />
        </div>
        <div>
          <Label>{t("pages.modirpayamak.forwardTo")}</Label>
          <Input
            className="mt-1 font-mono"
            dir="ltr"
            value={forwardTo}
            onChange={(e) => setForwardTo(e.target.value)}
            placeholder="09xxxxxxxxx"
          />
        </div>
        <Button type="button" className="w-fit" onClick={() => void addRule()}>
          {t("common.add")}
        </Button>
      </div>

      <ul className="divide-y rounded border">
        {rows.map((r) => {
          const id = Number(r.id ?? 0)
          return (
            <li key={id} className="flex items-center justify-between gap-3 p-3 text-sm">
              <div>
                <p className="font-medium">
                  {String(r.name || r.type)} · {typeLabel(String(r.type || ""))}
                </p>
                <p className="text-muted-foreground font-mono text-xs" dir="ltr">
                  {String(r.keywords || "")}
                </p>
                {r.reply_body ? (
                  <p className="text-muted-foreground mt-1 text-xs">{String(r.reply_body)}</p>
                ) : null}
              </div>
              <Button type="button" size="sm" variant="outline" onClick={() => void remove(id)}>
                {t("common.delete")}
              </Button>
            </li>
          )
        })}
      </ul>
      {!loading && !rows.length ? (
        <p className="text-muted-foreground mt-4 text-sm">{t("pages.modirpayamak.secretariesEmpty")}</p>
      ) : null}
    </CrmPageLayout>
  )
}
