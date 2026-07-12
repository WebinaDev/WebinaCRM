import { useEffect } from "react"
import { useTranslation } from "react-i18next"
import { LoginForm } from "@/components/login-form"
import { LoginThemeControls } from "@/components/login-theme-controls"
import { useLocale } from "@/hooks/use-locale"
import { getLoginConfig } from "@/api/login"
import { applyDashboardDocumentSeo } from "@/lib/dashboard-seo"

export function LoginPage() {
  const { t: tPages } = useLocale()
  const { t } = useTranslation()

  const loginConfig = getLoginConfig()
  const dir = loginConfig?.dir ?? (window.webinoDashboard?.isRtl === false ? "ltr" : "rtl")
  const siteName =
    loginConfig?.siteName?.trim() ||
    window.webinoDashboard?.siteName?.trim() ||
    t("app.title")
  const logoUrl = loginConfig?.logoUrl?.trim() || window.webinoDashboard?.siteIconUrl?.trim()

  useEffect(() => {
    const title = `${t("login.title")} — ${siteName}`
    const description = t("seo.loginDescription", { site: siteName })
    const canonicalUrl = `${window.location.origin}${window.location.pathname}${window.location.search}`
    applyDashboardDocumentSeo({
      title,
      description,
      canonicalUrl,
      ogImageUrl: logoUrl || undefined,
      structuredSite: {
        name: siteName,
        url: window.webinoDashboard?.homeUrl || `${window.location.origin}/`,
      },
    })
  }, [t, siteName, logoUrl])

  return (
    <div
      className="relative flex min-h-svh w-full flex-col bg-muted p-6 md:p-10 lg:grid lg:min-h-svh lg:grid-cols-2 lg:items-stretch lg:gap-0 lg:p-0"
      dir={dir}
    >
      <LoginThemeControls isRtl={dir === "rtl"} />
      <div className="flex flex-1 flex-col justify-center px-0 py-8 md:px-8 lg:px-12 lg:py-10">
        <div className="mx-auto w-full max-w-sm md:max-w-4xl">
          <LoginForm />
        </div>
      </div>

      <div className="relative hidden flex-col justify-between bg-gradient-to-br from-primary/15 via-background to-muted p-10 text-primary-foreground lg:flex">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,hsl(var(--primary)/0.25),transparent_50%)]" />
        <div className="relative z-10 mt-auto space-y-4">
          {logoUrl ? (
            <img
              src={logoUrl}
              alt=""
              className="h-14 max-w-[200px] object-contain opacity-90 invert dark:invert-0"
            />
          ) : null}
          <blockquote className="space-y-2 text-lg font-medium leading-relaxed text-foreground">
            <p>{tPages("pages.login.پنل_مدیریت_امن_و_یکپارچه_برای_تیم_شما")}</p>
          </blockquote>
        </div>
        <div className="relative z-10 text-xs text-muted-foreground">
          © {new Date().getFullYear()} {siteName}
        </div>
      </div>
    </div>
  )
}
