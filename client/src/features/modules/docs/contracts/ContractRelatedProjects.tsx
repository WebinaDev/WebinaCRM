import { useState } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Card, CardContent } from "@/components/ui/card"
import { addProjectToContract } from "@/api/contracts"
import { useLocale } from "@/hooks/use-locale"
import { Loader2 } from "lucide-react"
import { cn } from "@/lib/utils"

type Props = {
  contractId: number
  projects: { id: number; title: string }[]
  onAdded: (project: { id: number; title: string }) => void
}

export function ContractRelatedProjects({ contractId, projects, onAdded }: Props) {
  const { t, isRtl } = useLocale()
  const [title, setTitle] = useState("")
  const [submitting, setSubmitting] = useState(false)

  const handleAdd = async () => {
    if (!title.trim()) return
    setSubmitting(true)
    try {
      const res = await addProjectToContract(contractId, title.trim())
      if (res.success) {
        onAdded({ id: res.data?.project_id ?? 0, title: title.trim() })
        setTitle("")
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Card>
      <CardContent className="pt-6 space-y-4">
        <h3 className="font-medium">{t("pages.contracts.پروژههای_مرتبط")}</h3>
        {projects.length > 0 ? (
          <ul className="space-y-1 text-sm">
            {projects.map((p) => (
              <li key={p.id} className="rounded border px-3 py-2">{p.title}</li>
            ))}
          </ul>
        ) : (
          <p className="text-sm text-muted-foreground">{t("common.empty")}</p>
        )}
        <div className="flex flex-wrap gap-2 items-end">
          <div className="flex-1 min-w-[200px] space-y-2">
            <Label>{t("pages.contracts.عنوان_پروژه_جدید")}</Label>
            <Input value={title} onChange={(e) => setTitle(e.target.value)} />
          </div>
          <Button type="button" onClick={handleAdd} disabled={submitting || !title.trim()}>
            {submitting && <Loader2 className={cn("h-4 w-4 animate-spin", isRtl ? "ms-2" : "me-2")} />}
            {t("pages.contracts.افزودن_پروژه")}
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}
