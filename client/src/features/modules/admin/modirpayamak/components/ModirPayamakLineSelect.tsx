import { useCallback, useEffect, useState } from "react"
import { Link } from "react-router-dom"
import { Button } from "@/components/ui/button"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { edgeField, edgeListNumbers, type EdgeRow } from "@/api/modirpayamak-edge"
import { useLocale } from "@/hooks/use-locale"

type Props = {
  value: string
  onChange: (value: string) => void
  disabled?: boolean
}

export function ModirPayamakLineSelect({ value, onChange, disabled }: Props) {
  const { t } = useLocale()
  const [lines, setLines] = useState<EdgeRow[]>([])
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    setLoading(true)
    const res = await edgeListNumbers()
    setLines(res.items)
    if (!value && res.items.length > 0) {
      const first = edgeField(res.items[0], "number", "line", "from_number", "sender")
      if (first !== "—") onChange(first)
    }
    setLoading(false)
  }, [onChange, value])

  useEffect(() => {
    void load()
  }, [load])

  if (loading) {
    return <div className="h-10 rounded-md bg-muted animate-pulse" />
  }

  if (lines.length === 0) {
    return (
      <div className="flex flex-wrap items-center gap-2">
        <Select value={value || undefined} onValueChange={onChange} disabled={disabled}>
          <SelectTrigger>
            <SelectValue placeholder={t("pages.modirpayamak.fromNumber")} />
          </SelectTrigger>
          <SelectContent />
        </Select>
        <Button variant="link" size="sm" className="h-auto p-0" asChild>
          <Link to="/admin/integrations/modirpayamak/numbers">{t("pages.modirpayamak.manageLines")}</Link>
        </Button>
      </div>
    )
  }

  return (
    <Select value={value || undefined} onValueChange={onChange} disabled={disabled}>
      <SelectTrigger>
        <SelectValue placeholder={t("pages.modirpayamak.fromNumber")} />
      </SelectTrigger>
      <SelectContent>
        {lines.map((row, i) => {
          const num = edgeField(row, "number", "line", "from_number", "sender")
          const label = edgeField(row, "title", "name", "type")
          return (
            <SelectItem key={`${num}-${i}`} value={num}>
              {label !== "—" ? `${num} (${label})` : num}
            </SelectItem>
          )
        })}
      </SelectContent>
    </Select>
  )
}
