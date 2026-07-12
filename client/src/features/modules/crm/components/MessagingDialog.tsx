import { useState } from "react"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { sendCustomSms, sendBaleMessage, sendBaleBulkMessage } from "@/api/customers"
import { useLocale } from "@/hooks/use-locale"
import { Loader2 } from "lucide-react"
import { cn } from "@/lib/utils"

type UserTarget = { id: number; display_name: string }

type SingleProps = {
  mode: "single"
  open: boolean
  onOpenChange: (open: boolean) => void
  user: UserTarget
  channel: "sms" | "bale"
  onSuccess?: (message: string) => void
  onError?: (message: string) => void
}

type BulkProps = {
  mode: "bulk"
  open: boolean
  onOpenChange: (open: boolean) => void
  roles?: { slug: string; name: string }[]
  onSuccess?: (message: string) => void
  onError?: (message: string) => void
}

export function MessagingDialog(props: SingleProps | BulkProps) {
  const { t, isRtl } = useLocale()
  const [text, setText] = useState("")
  const [bulkMode, setBulkMode] = useState<"all" | "filtered">("all")
  const [submitting, setSubmitting] = useState(false)

  const close = () => {
    setText("")
    props.onOpenChange(false)
  }

  const handleSubmit = async () => {
    if (!text.trim()) return
    setSubmitting(true)
    try {
      if (props.mode === "single") {
        const res =
          props.channel === "sms"
            ? await sendCustomSms(props.user.id, text.trim())
            : await sendBaleMessage(props.user.id, text.trim())
        if (res.success) {
          props.onSuccess?.(res.message ?? t("common.save"))
          close()
        } else {
          props.onError?.(res.message ?? t("common.errors.saveFailed"))
        }
      } else {
        const res = await sendBaleBulkMessage({
          message: text.trim(),
          mode: bulkMode,
          roles: bulkMode === "filtered" ? (props.roles ?? []).map((r) => r.slug) : undefined,
        })
        if (res.success) {
          props.onSuccess?.(res.message ?? t("pages.customers.bulk_bale_sent"))
          close()
        } else {
          props.onError?.(res.message ?? t("common.errors.saveFailed"))
        }
      }
    } catch {
      props.onError?.(t("common.errors.saveFailed"))
    } finally {
      setSubmitting(false)
    }
  }

  const title =
    props.mode === "single"
      ? props.channel === "sms"
        ? t("pages.customers.send_sms")
        : t("pages.customers.send_bale")
      : t("pages.customers.bulk_bale")

  return (
    <Dialog open={props.open} onOpenChange={props.onOpenChange}>
      <DialogContent className="sm:max-w-md" dir={isRtl ? "rtl" : "ltr"}>
        <DialogHeader>
          <DialogTitle>
            {props.mode === "single"
              ? `${title} — ${props.user.display_name}`
              : title}
          </DialogTitle>
        </DialogHeader>
        <div className="space-y-4 py-2">
          {props.mode === "bulk" && (
            <div className="space-y-2">
              <Label>{t("pages.customers.bulk_mode")}</Label>
              <Select value={bulkMode} onValueChange={(v) => setBulkMode(v as "all" | "filtered")}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{t("pages.customers.bulk_all")}</SelectItem>
                  <SelectItem value="filtered">{t("pages.customers.bulk_filtered_roles")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
          )}
          <div className="space-y-2">
            <Label>{t("pages.customers.message")}</Label>
            <textarea
              className="flex min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
              value={text}
              onChange={(e) => setText(e.target.value)}
            />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={close}>{t("common.cancel")}</Button>
          <Button onClick={handleSubmit} disabled={submitting || !text.trim()}>
            {submitting && <Loader2 className={cn("h-4 w-4 animate-spin", isRtl ? "ms-2" : "me-2")} />}
            {t("common.send")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
