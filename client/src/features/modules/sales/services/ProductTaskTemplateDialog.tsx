import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { useLocale } from "@/hooks/use-locale"
import { Loader2 } from "lucide-react"
import { cn } from "@/lib/utils"
import type { Product, TaskTemplate } from "@/api/services"

type ServiceType = { value: string; label: string }

type Props = {
  open: boolean
  product: Product | null
  taskTemplates: TaskTemplate[]
  serviceTypes: ServiceType[]
  form: { task_template_id: number | null; service_task_type: string }
  setForm: React.Dispatch<
    React.SetStateAction<{ task_template_id: number | null; service_task_type: string }>
  >
  submitting: boolean
  onOpenChange: (open: boolean) => void
  onSave: () => void
}

export function ProductTaskTemplateDialog({
  open,
  product,
  taskTemplates,
  serviceTypes,
  form,
  setForm,
  submitting,
  onOpenChange,
  onSave,
}: Props) {
  const { t, isRtl } = useLocale()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent dir={isRtl ? "rtl" : "ltr"}>
        <DialogHeader>
          <DialogTitle>{t("pages.services.اتصال_قالب_تسک_به_محصول")}</DialogTitle>
        </DialogHeader>
        {product && (
          <div className="space-y-4">
            <p className="text-sm text-muted-foreground">{product.name}</p>
            <div className="space-y-2">
              <label className="text-sm font-medium">{t("pages.services.قالب_تسک")}</label>
              <Select
                value={form.task_template_id ? String(form.task_template_id) : "0"}
                onValueChange={(v) =>
                  setForm((f) => ({ ...f, task_template_id: v === "0" ? null : parseInt(v, 10) }))
                }
              >
                <SelectTrigger>
                  <SelectValue placeholder={t("pages.leads.انتخاب_کنید")} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="0">{t("pages.services.هیچکدام")}</SelectItem>
                  {taskTemplates.map((tpl) => (
                    <SelectItem key={tpl.id} value={String(tpl.id)}>
                      {tpl.title} {tpl.is_recurring && `(${tpl.recurring_type})`}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">{t("pages.services.نوع_خدمت_تکرار_تسک")}</label>
              <Select
                value={form.service_task_type}
                onValueChange={(v) => setForm((f) => ({ ...f, service_task_type: v }))}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {serviceTypes.map((s) => (
                    <SelectItem key={s.value} value={s.value}>
                      {s.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
        )}
        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            {t("common.cancel")}
          </Button>
          <Button onClick={onSave} disabled={submitting}>
            {submitting && <Loader2 className={cn("h-4 w-4 animate-spin", isRtl ? "ms-2" : "me-2")} />}
            {t("common.save")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
