import { useState } from "react"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { cn } from "@/lib/utils"
import { getConfigOrNull } from "@/api/client"
import { updateProfile } from "@/api/profile"
import { useLocale } from "@/hooks/use-locale"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { Loader2, User } from "lucide-react"

export function ProfilePage() {
  const { t, isRtl } = useLocale()
  const { layoutProps, setError, setSuccess } = useCrmFeedback()
  const config = getConfigOrNull()
  const user = config?.user

  const [form, setForm] = useState({
    first_name: user?.first_name ?? user?.name?.split(" ")?.[0] ?? "",
    last_name: user?.last_name ?? user?.name?.split(" ")?.slice(1).join(" ") ?? "",
    webino_mobile_phone: user?.webino_mobile_phone ?? "",
    password: "",
    password_confirm: "",
  })
  const [avatarFile, setAvatarFile] = useState<File | null>(null)
  const [avatarPreview, setAvatarPreview] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (file) {
      setAvatarFile(file)
      setAvatarPreview(URL.createObjectURL(file))
    } else {
      setAvatarFile(null)
      setAvatarPreview(null)
    }
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setSuccess(null)
    if (!form.last_name.trim()) {
      setError(t("pages.profile.نام_خانوادگی_ضروری_است"))
      return
    }
    if (form.password && form.password !== form.password_confirm) {
      setError(t("pages.profile.رمزهای_عبور_وارد_شده_یکسان_نیستند"))
      return
    }
    setSubmitting(true)
    try {
      const res = await updateProfile({
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        webino_mobile_phone: form.webino_mobile_phone.trim() || undefined,
        password: form.password || undefined,
        password_confirm: form.password_confirm || undefined,
        webino_profile_picture: avatarFile ?? undefined,
      })
      if (res.success) {
        setSuccess(t("pages.profile.پروفایل_شما_با_موفقیت_بهروزرسانی_شد"))
        setForm((f) => ({ ...f, password: "", password_confirm: "" }))
        setAvatarFile(null)
        setAvatarPreview(null)
        if ((res.data as { reload?: boolean })?.reload) {
          window.location.reload()
        }
      } else {
        setError(res.message ?? t("pages.profile.خطا_در_بهروزرسانی_پروفایل"))
      }
    } catch {
      setError(t("pages.profile.خطا_در_بهروزرسانی_پروفایل"))
    } finally {
      setSubmitting(false)
    }
  }

  const avatarUrl = avatarPreview ?? user?.avatarLarge ?? user?.avatar

  return (
    <CrmPageLayout title={t("pages.profile.پروفایل_من")} {...layoutProps}>
      <Card>
        <CardContent className="pt-6">
          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="flex flex-col gap-6 md:flex-row">
              <div className="flex flex-col items-start gap-2">
                <Label>{t("pages.profile.عکس_پروفایل")}</Label>
                <div className="flex items-center gap-4">
                  <Avatar className="h-24 w-24">
                    <AvatarImage src={avatarUrl} alt={user?.name} />
                    <AvatarFallback>
                      <User className="h-12 w-12" />
                    </AvatarFallback>
                  </Avatar>
                  <Input
                    type="file"
                    accept="image/*"
                    onChange={handleFileChange}
                    className="max-w-[200px]"
                  />
                </div>
              </div>
              <div className="flex-1 space-y-4">
                <h4 className="font-medium">{t("pages.profile.اطلاعات_اصلی_و_ورود")}</h4>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="first_name">{t("common.name")}</Label>
                    <Input
                      id="first_name"
                      value={form.first_name}
                      onChange={(e) =>
                        setForm((f) => ({ ...f, first_name: e.target.value }))
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="last_name">{t("pages.customers.نام_خانوادگی")}</Label>
                    <Input
                      id="last_name"
                      value={form.last_name}
                      onChange={(e) =>
                        setForm((f) => ({ ...f, last_name: e.target.value }))
                      }
                      required
                    />
                  </div>
                  <div className="space-y-2 sm:col-span-2">
                    <Label htmlFor="email">{t("pages.profile.ایمیل_غیرقابل_تغییر")}</Label>
                    <Input
                      id="email"
                      type="email"
                      value={user?.email ?? ""}
                      disabled
                      className="bg-muted"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="webino_mobile_phone">{t("pages.customers.شماره_موبایل")}</Label>
                    <Input
                      id="webino_mobile_phone"
                      dir="ltr"
                      className="text-left"
                      value={form.webino_mobile_phone}
                      onChange={(e) =>
                        setForm((f) => ({
                          ...f,
                          webino_mobile_phone: e.target.value,
                        }))
                      }
                    />
                  </div>
                </div>
                <hr />
                <h4 className="font-medium">{t("pages.profile.تغییر_رمز_عبور")}</h4>
                <p className="text-sm text-muted-foreground">
                  {t("pages.profile.passwordHint")}
                </p>
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="password">{t("pages.profile.رمز_عبور_جدید")}</Label>
                    <Input
                      id="password"
                      type="password"
                      value={form.password}
                      onChange={(e) =>
                        setForm((f) => ({ ...f, password: e.target.value }))
                      }
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="password_confirm">{t("pages.profile.تکرار_رمز_عبور_جدید")}</Label>
                    <Input
                      id="password_confirm"
                      type="password"
                      value={form.password_confirm}
                      onChange={(e) =>
                        setForm((f) => ({
                          ...f,
                          password_confirm: e.target.value,
                        }))
                      }
                    />
                  </div>
                </div>
              </div>
            </div>
            <div>
              <Button type="submit" disabled={submitting}>
                {submitting && (
                  <Loader2 className={cn("h-4 w-4 animate-spin", isRtl ? "ms-2" : "me-2")} />
                )}
                {t("pages.profile.ذخیره_تغییرات")}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </CrmPageLayout>
  )
}
