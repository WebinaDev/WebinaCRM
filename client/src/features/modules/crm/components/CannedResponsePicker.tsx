import { useEffect, useState } from "react"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { getCannedResponses } from "@/api/tickets"
import { useLocale } from "@/hooks/use-locale"
import { Loader2 } from "lucide-react"

type Props = {
  onSelect: (content: string) => void
}

export function CannedResponsePicker({ onSelect }: Props) {
  const { t } = useLocale()
  const [items, setItems] = useState<{ id: number; title: string; content: string }[]>([])
  const [loading, setLoading] = useState(true)
  const [value, setValue] = useState("")

  useEffect(() => {
    void (async () => {
      setLoading(true)
      try {
        const res = await getCannedResponses()
        if (res.success && res.data?.responses) {
          setItems(res.data.responses)
        }
      } finally {
        setLoading(false)
      }
    })()
  }, [])

  if (loading) {
    return (
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <Loader2 className="h-4 w-4 animate-spin" />
        {t("common.loading")}
      </div>
    )
  }

  if (items.length === 0) return null

  return (
    <div className="space-y-2">
      <Label>{t("pages.tickets.canned_responses")}</Label>
      <Select
        value={value}
        onValueChange={(id) => {
          setValue(id)
          const item = items.find((x) => String(x.id) === id)
          if (item) {
            onSelect(item.content)
            setValue("")
          }
        }}
      >
        <SelectTrigger className="max-w-xs">
          <SelectValue placeholder={t("pages.tickets.pick_canned")} />
        </SelectTrigger>
        <SelectContent>
          {items.map((item) => (
            <SelectItem key={item.id} value={String(item.id)}>
              {item.title}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  )
}
