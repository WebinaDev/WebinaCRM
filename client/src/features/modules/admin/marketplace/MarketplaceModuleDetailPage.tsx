import { DetailSkeleton } from "@/components/skeletons"
import { useCallback, useEffect, useMemo, useState } from "react"
import { Link, useNavigate, useParams } from "react-router-dom"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Checkbox } from "@/components/ui/checkbox"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  createMarketplaceModuleRepo,
  deleteMarketplaceRelease,
  getMarketplaceCategories,
  getMarketplaceModule,
  getMarketplaceModules,
  publishMarketplaceRelease,
  saveMarketplaceModule,
  saveMarketplaceRelease,
  setMarketplaceModuleRepoVisibility,
  syncMarketplaceModuleReadme,
  syncMarketplaceModuleRepo,
  type MarketplaceCategory,
  type MarketplaceModule,
  type MarketplaceRelease,
} from "@/api/marketplace"
import { marketplaceError, marketplaceTechnicalDetail } from "./marketplace-messages"
import { useLocale } from "@/hooks/use-locale"
import { useTextDirection } from "@/hooks/use-text-direction"
import { CrmPageLayout } from "@/features/shared/layout/CrmPageLayout"
import { RichTextEditor } from "@/features/shared/pm/RichTextEditor"
import { MarkdownField } from "@/features/shared/MarkdownField"
import { ModuleIconField } from "./ModuleIconField"
import { useCrmFeedback } from "@/features/shared/hooks/useCrmFeedback"
import { PmConfirmDialog } from "@/features/shared/pm/PmConfirmDialog"
import {
  ChevronLeft,
  ChevronRight,
  ExternalLink,
  Eye,
  EyeOff,
  GitBranch,
  Link2,
  RefreshCw,
  Tag,
  Trash2,
} from "lucide-react"

export function MarketplaceModuleDetailPage() {
  const { t, isRtl, formatDateTime } = useLocale()
  const textDir = useTextDirection()
  const navigate = useNavigate()
  const { layoutProps, setError, setSuccess, error } = useCrmFeedback()
  const { id } = useParams<{ id: string }>()
  const isCreateMode = id === "new"
  const moduleId = isCreateMode ? 0 : Number(id)
  const [module, setModule] = useState<MarketplaceModule | null>(null)
  const [categories, setCategories] = useState<MarketplaceCategory[]>([])
  const [parentCandidates, setParentCandidates] = useState<MarketplaceModule[]>([])
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [createSlug, setCreateSlug] = useState("")
  const [createVersion, setCreateVersion] = useState("1.0.0")
  const [useGitea, setUseGitea] = useState(false)
  const [createZip, setCreateZip] = useState<File | null>(null)
  const [settingsForm, setSettingsForm] = useState({
    name: "",
    description: "",
    price: "0",
    category_id: "",
    detail_url: "",
    icon_url: "",
    settings_route: "",
    readme_md: "",
  })
  const [isFree, setIsFree] = useState(true)
  const [isBuiltin, setIsBuiltin] = useState(false)
  const [parentModuleId, setParentModuleId] = useState("")
  const [pushReadmeGitea, setPushReadmeGitea] = useState(false)
  const [saveWarnings, setSaveWarnings] = useState<Record<string, string> | null>(null)
  const [releaseForm, setReleaseForm] = useState({ version: "", tag_name: "", changelog: "" })
  const [releaseZip, setReleaseZip] = useState<File | null>(null)
  const [deleteReleaseId, setDeleteReleaseId] = useState<number | null>(null)
  const [deletingRelease, setDeletingRelease] = useState(false)

  const BackIcon = isRtl ? ChevronRight : ChevronLeft
  const isGitea = module?.package_source === "gitea"
  const repoLinked = Boolean(module?.gitea_repo_linked)

  const categorySelectValue = settingsForm.category_id || "__none__"

  const categoryLabel = useMemo(() => {
    if (!settingsForm.category_id) return ""
    const found = categories.find((c) => String(c.id) === settingsForm.category_id)
    if (found) return found.name
    return module?.category_name ?? ""
  }, [categories, settingsForm.category_id, module?.category_name])

  const orphanCategory =
    settingsForm.category_id &&
    !categories.some((c) => String(c.id) === settingsForm.category_id) &&
    module?.category_name
      ? { id: Number(settingsForm.category_id), name: module.category_name }
      : null

  const populateSettings = useCallback((m: MarketplaceModule) => {
    setSettingsForm({
      name: m.name ?? "",
      description: m.description ?? "",
      price: String(m.price ?? 0),
      category_id:
        m.category_id != null && Number(m.category_id) > 0 ? String(m.category_id) : "",
      detail_url: m.detail_url ?? "",
      icon_url: m.icon_url ?? "",
      settings_route: m.settings_route ?? "",
      readme_md: m.readme_md ?? "",
    })
    setIsFree(Boolean(m.is_free))
    setIsBuiltin(Boolean(m.is_builtin))
    setParentModuleId(m.parent_module_id ? String(m.parent_module_id) : "")
  }, [])

  const loadParentCandidates = useCallback(async (excludeId?: number) => {
    const res = await getMarketplaceModules(true)
    if (res.success && res.data?.modules) {
      setParentCandidates(
        res.data.modules.filter(
          (m) => !m.parent_module_id && !m.is_core && (!excludeId || m.id !== excludeId),
        ),
      )
    }
  }, [])

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    if (isCreateMode) {
      const catRes = await getMarketplaceCategories()
      if (catRes.success && catRes.data?.categories) {
        setCategories(catRes.data.categories)
      }
      await loadParentCandidates()
      setModule(null)
      setLoading(false)
      return
    }
    if (!moduleId) {
      setLoading(false)
      return
    }
    const [modRes, catRes] = await Promise.all([
      getMarketplaceModule(moduleId),
      getMarketplaceCategories(),
    ])
    if (catRes.success && catRes.data?.categories) {
      setCategories(catRes.data.categories)
    }
    if (modRes.success && modRes.data?.module) {
      setModule(modRes.data.module)
      populateSettings(modRes.data.module)
      await loadParentCandidates(modRes.data.module.id)
      if (modRes.data.sync_warning) {
        setError(
          marketplaceTechnicalDetail(t, modRes.data.sync_warning) ??
            t("pages.marketplace.syncWarning"),
        )
      }
    } else {
      setError(marketplaceError(modRes, t("pages.marketplace.loadError")))
      setModule(null)
    }
    setLoading(false)
  }, [isCreateMode, moduleId, populateSettings, loadParentCandidates, setError, t])

  useEffect(() => {
    void load()
  }, [load])

  const handleCreateRepo = async () => {
    if (!moduleId) return
    setBusy(true)
    setError(null)
    const res = await createMarketplaceModuleRepo(moduleId)
    if (res.success && res.data?.module) {
      setModule(res.data.module)
      populateSettings(res.data.module)
      setSuccess(t("pages.marketplace.repoReady"))
    } else {
      setError(marketplaceError(res, t("pages.marketplace.saveError")))
    }
    setBusy(false)
  }

  const handleSyncRepo = async () => {
    if (!moduleId) return
    setBusy(true)
    setError(null)
    const res = await syncMarketplaceModuleRepo(moduleId)
    if (res.success && res.data?.module) {
      setModule(res.data.module)
      populateSettings(res.data.module)
      setSuccess(t("pages.marketplace.repoSynced"))
    } else {
      setError(marketplaceError(res, t("pages.marketplace.saveError")))
    }
    setBusy(false)
  }

  const handleToggleVisibility = async () => {
    if (!moduleId || !module) return
    setBusy(true)
    setError(null)
    const nextPrivate = !module.gitea_private
    const res = await setMarketplaceModuleRepoVisibility(moduleId, nextPrivate)
    if (res.success && res.data?.module) {
      setModule(res.data.module)
      setSuccess(t("pages.marketplace.repoVisibilityUpdated"))
    } else {
      setError(marketplaceError(res, t("pages.marketplace.saveError")))
    }
    setBusy(false)
  }

  const handlePullReadme = async () => {
    if (!moduleId) return
    setBusy(true)
    setError(null)
    const res = await syncMarketplaceModuleReadme(moduleId)
    if (res.success && res.data?.module) {
      setModule(res.data.module)
      populateSettings(res.data.module)
      setSuccess(t("pages.marketplace.readmeSynced"))
    } else {
      setError(marketplaceError(res, t("pages.marketplace.saveError")))
    }
    setBusy(false)
  }

  const handleSaveSettings = async (e: React.FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    const fd = new FormData()
    if (isCreateMode) {
      const slug = createSlug.trim()
      if (!slug) {
        setBusy(false)
        setError(t("pages.marketplace.slug"))
        return
      }
      fd.set("slug", slug)
      fd.set("name", settingsForm.name.trim() || slug)
      fd.set("description", settingsForm.description)
      fd.set("price", settingsForm.price)
      fd.set("category_id", settingsForm.category_id)
      fd.set("version", createVersion.trim() || "1.0.0")
      fd.set("settings_area", "shop")
      fd.set("settings_route", settingsForm.settings_route)
      fd.set("detail_url", settingsForm.detail_url)
      fd.set("icon_url", settingsForm.icon_url)
      fd.set("is_free", isFree ? "1" : "0")
      fd.set("is_builtin", isBuiltin ? "1" : "0")
      fd.set("status", "active")
      if (parentModuleId) fd.set("parent_module_id", parentModuleId)
      if (useGitea) {
        fd.set("package_source", "gitea")
        fd.set("create_gitea_repo", "1")
      }
      if (createZip) fd.set("package_zip", createZip)
    } else {
      if (!module) {
        setBusy(false)
        return
      }
      fd.set("id", String(module.id))
      fd.set("slug", module.slug)
      fd.set("name", settingsForm.name.trim())
      fd.set("description", settingsForm.description)
      fd.set("readme_md", settingsForm.readme_md)
      fd.set("price", settingsForm.price)
      fd.set("category_id", settingsForm.category_id)
      fd.set("detail_url", settingsForm.detail_url)
      fd.set("icon_url", settingsForm.icon_url)
      fd.set("settings_route", settingsForm.settings_route)
      fd.set("settings_area", module.settings_area ?? "shop")
      fd.set("version", module.version ?? "1.0.0")
      fd.set("is_free", isFree ? "1" : "0")
      fd.set("is_builtin", isBuiltin ? "1" : "0")
      fd.set("status", module.status ?? "active")
      fd.set("package_source", module.package_source ?? "local")
      fd.set("parent_module_id", parentModuleId)
      if (pushReadmeGitea && isGitea) fd.set("push_readme_gitea", "1")
    }
    const res = await saveMarketplaceModule(fd)
    if (res.success) {
      if (isCreateMode && res.data?.id) {
        navigate(`/marketplace/modules/${res.data.id}`, { replace: true })
        return
      }
      setSuccess(t("pages.marketplace.saved"))
      setSaveWarnings(res.data?.warnings ?? null)
      void load()
    } else {
      setSaveWarnings(null)
      setError(marketplaceError(res, t("pages.marketplace.saveError")))
    }
    setBusy(false)
  }

  const handleAddRelease = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!moduleId) return
    setBusy(true)
    setError(null)
    const fd = new FormData()
    fd.set("version", releaseForm.version.trim())
    fd.set("tag_name", releaseForm.tag_name.trim() || `v${releaseForm.version.trim()}`)
    fd.set("changelog", releaseForm.changelog)
    if (releaseZip) fd.set("package_zip", releaseZip)
    const res = await saveMarketplaceRelease(moduleId, fd)
    if (res.success) {
      setSuccess(t("pages.marketplace.api.releaseSaved"))
      setReleaseForm({ version: "", tag_name: "", changelog: "" })
      setReleaseZip(null)
      void load()
    } else {
      setError(marketplaceError(res, t("pages.marketplace.saveError")))
    }
    setBusy(false)
  }

  const handlePublish = async (release: MarketplaceRelease) => {
    setBusy(true)
    setError(null)
    const res = await publishMarketplaceRelease(release.id)
    if (res.success) {
      setSuccess(t("pages.marketplace.releasePublished"))
      void load()
    } else {
      setError(marketplaceError(res, t("pages.marketplace.saveError")))
    }
    setBusy(false)
  }

  const confirmDeleteRelease = async () => {
    if (deleteReleaseId == null) return
    setDeletingRelease(true)
    const res = await deleteMarketplaceRelease(deleteReleaseId)
    setDeletingRelease(false)
    if (res.success) {
      setDeleteReleaseId(null)
      setSuccess(t("pages.marketplace.api.releaseDeleted"))
      void load()
    } else {
      setError(marketplaceError(res, t("pages.marketplace.deleteError")))
    }
  }

  const repoUrl = module?.gitea_html_url || module?.gitea_repo_url || ""
  const erpSettingsLink = useMemo(() => {
    if (!module?.erp_submodule_settings_key) return null
    return `/admin/settings`
  }, [module?.erp_submodule_settings_key])

  if (loading) {
    return (
      <CrmPageLayout
        title={
          isCreateMode
            ? t("pages.marketplace.createProductTitle")
            : t("pages.marketplace.moduleDetailTitle")
        }
        {...layoutProps}
      >
        <DetailSkeleton showPageHeader={false} />
      </CrmPageLayout>
    )
  }

  if (!isCreateMode && !module) {
    return (
      <CrmPageLayout
        title={t("pages.marketplace.moduleDetailTitle")}
        error={error ?? t("pages.marketplace.loadError")}
        onDismissError={layoutProps.onDismissError}
      >
        <div />
      </CrmPageLayout>
    )
  }

  const releases = module?.releases ?? []

  return (
    <CrmPageLayout
      title={isCreateMode ? t("pages.marketplace.createProductTitle") : (module?.name ?? "")}
      description={isCreateMode ? t("pages.marketplace.createProductDesc") : undefined}
      {...layoutProps}
    >
      <div className="flex flex-wrap items-center gap-3">
        <Button variant="ghost" size="sm" asChild>
          <Link to="/marketplace/products">
            <BackIcon className="me-2 h-4 w-4" />
            {t("pages.marketplace.productsTitle")}
          </Link>
        </Button>
        {!isCreateMode && module ? (
          <p className="text-muted-foreground text-sm">
            {module.slug} · v{module.version} · {module.package_source ?? "local"}
          </p>
        ) : null}
        {!isCreateMode && module?.erp_submodule_settings_key ? (
          <Button variant="outline" size="sm" asChild>
            <Link to={erpSettingsLink ?? "/admin/settings"}>
              {t("pages.marketplace.erpSubmodule")}: {module.erp_submodule_settings_key}
            </Link>
          </Button>
        ) : null}
      </div>

      {!isCreateMode && module ? (
      <Card>
        <CardHeader>
          <CardTitle>{t("pages.marketplace.giteaRepo")}</CardTitle>
          <CardDescription>{t("pages.marketplace.repoToolbarDesc")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-center gap-2">
            {repoUrl ? (
              <Button variant="outline" size="sm" asChild>
                <a href={repoUrl} target="_blank" rel="noreferrer">
                  <ExternalLink className="me-2 h-4 w-4" />
                  {t("pages.marketplace.openGitea")}
                </a>
              </Button>
            ) : null}
            {repoLinked ? (
              <>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={busy}
                  onClick={() => void handleSyncRepo()}
                >
                  <RefreshCw className="me-2 h-4 w-4" />
                  {t("pages.marketplace.syncRepo")}
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={busy}
                  onClick={() => void handleToggleVisibility()}
                >
                  {module.gitea_private ? (
                    <>
                      <Eye className="me-2 h-4 w-4" />
                      {t("pages.marketplace.makePublic")}
                    </>
                  ) : (
                    <>
                      <EyeOff className="me-2 h-4 w-4" />
                      {t("pages.marketplace.makePrivate")}
                    </>
                  )}
                </Button>
                {module.detail_url ? (
                  <Button variant="outline" size="sm" asChild>
                    <a href={module.detail_url} target="_blank" rel="noreferrer">
                      <Link2 className="me-2 h-4 w-4" />
                      {t("pages.marketplace.detailUrl")}
                    </a>
                  </Button>
                ) : null}
                <Button variant="outline" size="sm" asChild>
                  <a href={`#releases`}>
                    <Tag className="me-2 h-4 w-4" />
                    {t("pages.marketplace.releasesTitle")}
                  </a>
                </Button>
              </>
            ) : (
              <Button type="button" size="sm" disabled={busy} onClick={() => void handleCreateRepo()}>
                <GitBranch className="me-2 h-4 w-4" />
                {t("pages.marketplace.createGiteaRepo")}
              </Button>
            )}
          </div>
          {repoUrl ? (
            <p className="text-muted-foreground text-sm break-all">{repoUrl}</p>
          ) : (
            <span className="text-muted-foreground text-sm">—</span>
          )}
          {repoLinked && module.gitea_default_branch ? (
            <p className="text-muted-foreground text-xs">
              {t("pages.marketplace.defaultBranch")}: {module.gitea_default_branch}
              {module.gitea_private != null
                ? ` · ${module.gitea_private ? t("pages.marketplace.privateRepo") : t("pages.marketplace.publicRepo")}`
                : ""}
            </p>
          ) : null}
        </CardContent>
      </Card>
      ) : null}

      <Card>
        <CardHeader>
          <CardTitle>{t("pages.marketplace.moduleSettings")}</CardTitle>
        </CardHeader>
        <CardContent>
          {!isCreateMode && saveWarnings ? (
            <div className="mb-4 space-y-1 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm">
              <p className="font-medium">{t("pages.marketplace.giteaSaveWarningTitle")}</p>
              {Object.entries(saveWarnings).map(([key, msg]) => {
                const detail = marketplaceTechnicalDetail(t, msg) ?? msg
                return (
                  <p key={key} className="text-muted-foreground">
                    {key === "gitea_repo"
                      ? t("pages.marketplace.giteaSaveWarningRepo", { message: detail })
                      : key === "gitea_readme"
                        ? t("pages.marketplace.giteaSaveWarningReadme", { message: detail })
                        : detail}
                  </p>
                )
              })}
            </div>
          ) : null}
          <form onSubmit={handleSaveSettings} className="grid gap-4 md:grid-cols-2">
            {isCreateMode ? (
              <>
                <div className="space-y-2">
                  <Label>{t("pages.marketplace.slug")}</Label>
                  <Input
                    value={createSlug}
                    onChange={(e) => setCreateSlug(e.target.value)}
                    placeholder="my-module"
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t("pages.marketplace.slugVersion")}</Label>
                  <Input
                    value={createVersion}
                    onChange={(e) => setCreateVersion(e.target.value)}
                    placeholder="1.0.0"
                  />
                </div>
              </>
            ) : null}
            <div className="space-y-2">
              <Label>{t("pages.marketplace.name")}</Label>
              <Input
                value={settingsForm.name}
                onChange={(e) => setSettingsForm((p) => ({ ...p, name: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.marketplace.price")}</Label>
              <Input
                type="number"
                value={settingsForm.price}
                onChange={(e) => setSettingsForm((p) => ({ ...p, price: e.target.value }))}
              />
            </div>
            <div className="space-y-2 md:col-span-2">
              <Label>{t("pages.marketplace.description")}</Label>
              <Textarea
                value={settingsForm.description}
                onChange={(e) => setSettingsForm((p) => ({ ...p, description: e.target.value }))}
                rows={3}
              />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.marketplace.category")}</Label>
              <Select
                value={categorySelectValue}
                onValueChange={(v) =>
                  setSettingsForm((p) => ({ ...p, category_id: v === "__none__" ? "" : v }))
                }
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t("pages.marketplace.noCategory")}>
                    {categorySelectValue === "__none__"
                      ? t("pages.marketplace.noCategory")
                      : categoryLabel || t("pages.marketplace.noCategory")}
                  </SelectValue>
                </SelectTrigger>
                <SelectContent
                  position="popper"
                  side="bottom"
                  sideOffset={4}
                  collisionPadding={8}
                  align={textDir === "rtl" ? "end" : "start"}
                >
                  <SelectItem value="__none__">{t("pages.marketplace.noCategory")}</SelectItem>
                  {orphanCategory ? (
                    <SelectItem value={String(orphanCategory.id)}>{orphanCategory.name}</SelectItem>
                  ) : null}
                  {categories.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {c.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t("pages.marketplace.detailUrl")}</Label>
              <Input
                value={settingsForm.detail_url}
                onChange={(e) => setSettingsForm((p) => ({ ...p, detail_url: e.target.value }))}
              />
            </div>
            <div className="md:col-span-2">
              <ModuleIconField
                iconUrl={settingsForm.icon_url}
                resetKey={isCreateMode ? "create" : module?.id}
                onChange={(v) => setSettingsForm((p) => ({ ...p, icon_url: v }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.marketplace.settingsRoute")}</Label>
              <Input
                value={settingsForm.settings_route}
                onChange={(e) => setSettingsForm((p) => ({ ...p, settings_route: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.marketplace.parentProduct")}</Label>
              <Select
                value={parentModuleId || "__none__"}
                onValueChange={(v) => setParentModuleId(v === "__none__" ? "" : v)}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t("pages.marketplace.noParentProduct")} />
                </SelectTrigger>
                <SelectContent
                  position="popper"
                  side="bottom"
                  sideOffset={4}
                  collisionPadding={8}
                  align={textDir === "rtl" ? "end" : "start"}
                >
                  <SelectItem value="__none__">{t("pages.marketplace.noParentProduct")}</SelectItem>
                  {parentCandidates.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>
                      {p.name} ({p.slug})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex items-center gap-2">
              <Checkbox
                checked={isFree}
                onCheckedChange={(v) => setIsFree(Boolean(v))}
                id="md-free"
              />
              <Label htmlFor="md-free">{t("pages.marketplace.isFree")}</Label>
            </div>
            <div className="flex items-start gap-2 md:col-span-2">
              <Checkbox
                checked={isBuiltin}
                onCheckedChange={(v) => setIsBuiltin(Boolean(v))}
                id="md-builtin"
              />
              <div className="space-y-1">
                <Label htmlFor="md-builtin">{t("pages.marketplace.isBuiltin")}</Label>
                <p className="text-muted-foreground text-xs">{t("pages.marketplace.isBuiltinHelp")}</p>
              </div>
            </div>
            {isCreateMode ? (
              <>
                <div className="flex items-center gap-2 md:col-span-2">
                  <Checkbox
                    checked={useGitea}
                    onCheckedChange={(v) => setUseGitea(Boolean(v))}
                    id="md-gitea"
                  />
                  <Label htmlFor="md-gitea">{t("pages.marketplace.useGitea")}</Label>
                </div>
                {!useGitea ? (
                  <div className="space-y-2 md:col-span-2">
                    <Label>{t("pages.marketplace.packageZip")}</Label>
                    <Input
                      type="file"
                      accept=".zip"
                      onChange={(e) => setCreateZip(e.target.files?.[0] ?? null)}
                    />
                  </div>
                ) : null}
              </>
            ) : null}
            <Button type="submit" disabled={busy} className="md:col-span-2 w-fit">
              {t("common.save")}
            </Button>
          </form>
        </CardContent>
      </Card>

      {!isCreateMode ? (
      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-4">
          <div>
            <CardTitle>{t("pages.marketplace.readmeTitle")}</CardTitle>
            <CardDescription>{t("pages.marketplace.readmeDesc")}</CardDescription>
          </div>
          {repoLinked ? (
            <Button type="button" size="sm" variant="outline" disabled={busy} onClick={() => void handlePullReadme()}>
              <RefreshCw className="me-2 h-4 w-4" />
              {t("pages.marketplace.pullReadme")}
            </Button>
          ) : null}
        </CardHeader>
        <CardContent className="space-y-3">
          <MarkdownField
            value={settingsForm.readme_md}
            onChange={(v) => setSettingsForm((p) => ({ ...p, readme_md: v }))}
          />
          {isGitea ? (
            <div className="flex items-center gap-2">
              <Checkbox
                checked={pushReadmeGitea}
                onCheckedChange={(v) => setPushReadmeGitea(Boolean(v))}
                id="md-push-readme"
              />
              <Label htmlFor="md-push-readme">{t("pages.marketplace.pushReadmeGitea")}</Label>
            </div>
          ) : null}
        </CardContent>
      </Card>
      ) : null}

      {!isCreateMode && module ? (
      <Card id="releases">
        <CardHeader>
          <CardTitle>{t("pages.marketplace.releasesTitle")}</CardTitle>
          {isGitea ? (
            <CardDescription>{t("pages.marketplace.giteaReleaseHint")}</CardDescription>
          ) : null}
        </CardHeader>
        <CardContent className="space-y-6">
          <form onSubmit={handleAddRelease} className="grid gap-3 md:grid-cols-2">
            <div className="space-y-2">
              <Label>{t("pages.marketplace.version")}</Label>
              <Input
                value={releaseForm.version}
                onChange={(e) => setReleaseForm((p) => ({ ...p, version: e.target.value }))}
                placeholder="1.2.0"
              />
            </div>
            <div className="space-y-2">
              <Label>{t("pages.marketplace.tagName")}</Label>
              <Input
                value={releaseForm.tag_name}
                onChange={(e) => setReleaseForm((p) => ({ ...p, tag_name: e.target.value }))}
                placeholder="v1.2.0"
              />
            </div>
            <div className="space-y-2 md:col-span-2">
              <Label>{t("pages.marketplace.changelog")}</Label>
              <RichTextEditor
                value={releaseForm.changelog}
                onChange={(v) => setReleaseForm((p) => ({ ...p, changelog: v }))}
                dir={isRtl ? "rtl" : "ltr"}
                className="min-h-[200px]"
              />
            </div>
            {!isGitea ? (
              <div className="space-y-2 md:col-span-2">
                <Label>{t("pages.marketplace.packageZip")}</Label>
                <Input
                  type="file"
                  accept=".zip"
                  onChange={(e) => setReleaseZip(e.target.files?.[0] ?? null)}
                />
              </div>
            ) : (
              <p className="text-muted-foreground text-sm md:col-span-2">
                {t("pages.marketplace.giteaReleaseNoZip")}
              </p>
            )}
            <Button type="submit" disabled={busy}>
              {t("pages.marketplace.addRelease")}
            </Button>
          </form>

          <div className="space-y-2">
            {releases.map((r) => (
              <div
                key={r.id}
                className="border-border flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3"
              >
                <div>
                  <div className="font-medium">
                    {r.version} ({r.tag_name})
                  </div>
                  <div className="text-muted-foreground text-xs">
                    {t("pages.marketplace.releaseStatus")}: {r.status}
                    {r.published_at ? ` · ${formatDateTime(r.published_at)}` : ""}
                    {r.gitea_release_id ? ` · Gitea #${r.gitea_release_id}` : ""}
                  </div>
                </div>
                <div className="flex gap-2">
                  {r.status === "draft" ? (
                    <Button
                      type="button"
                      size="sm"
                      disabled={busy}
                      onClick={() => void handlePublish(r)}
                    >
                      {t("pages.marketplace.publishRelease")}
                    </Button>
                  ) : null}
                  {repoUrl && r.tag_name ? (
                    <Button variant="outline" size="sm" asChild>
                      <a
                        href={`${repoUrl}/releases/tag/${encodeURIComponent(r.tag_name)}`}
                        target="_blank"
                        rel="noreferrer"
                      >
                        <ExternalLink className="h-4 w-4" />
                      </a>
                    </Button>
                  ) : null}
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="text-destructive"
                    disabled={busy}
                    onClick={() => setDeleteReleaseId(r.id)}
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            ))}
            {releases.length === 0 ? (
              <p className="text-muted-foreground text-sm">—</p>
            ) : null}
          </div>
        </CardContent>
      </Card>
      ) : null}

      <PmConfirmDialog
        open={deleteReleaseId != null}
        onOpenChange={(open) => !open && setDeleteReleaseId(null)}
        title={t("common.delete")}
        description={t("pages.marketplace.confirm.deleteRelease")}
        confirmLabel={t("common.delete")}
        cancelLabel={t("common.cancel")}
        onConfirm={confirmDeleteRelease}
        loading={deletingRelease}
        isRtl={isRtl}
      />
    </CrmPageLayout>
  )
}
