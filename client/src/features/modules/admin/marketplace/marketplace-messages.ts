import { getAjaxMessage, type AjaxResponse } from "@/api/client"
import type { TFunction } from "i18next"

/** Maps exact API message strings (PHP __()) to pages.marketplace i18n keys. */
const SERVER_MESSAGE_KEYS: Record<string, string> = {
  "Saved.": "pages.marketplace.saved",
  "Deleted.": "pages.marketplace.api.deleted",
  "Could not save category.": "pages.marketplace.api.categorySaveFailed",
  "Could not save module.": "pages.marketplace.api.moduleSaveFailed",
  "Module not found.": "pages.marketplace.api.moduleNotFound",
  "Invalid id.": "pages.marketplace.api.invalidId",
  "Invalid module id.": "pages.marketplace.api.invalidModuleId",
  "Invalid release id.": "pages.marketplace.api.invalidReleaseId",
  "Repository ready.": "pages.marketplace.repoReady",
  "Repository synchronized.": "pages.marketplace.repoSynced",
  "Repository is now private.": "pages.marketplace.api.repoPrivate",
  "Repository is now public.": "pages.marketplace.api.repoPublic",
  "Could not update repository.": "pages.marketplace.api.couldNotUpdateRepo",
  "Gitea repository is not linked.": "pages.marketplace.api.giteaNotLinked",
  "Could not read README from Gitea.": "pages.marketplace.api.readmePullFailed",
  "README synchronized from Gitea.": "pages.marketplace.readmeSynced",
  "Could not push README to Gitea.": "pages.marketplace.api.pushReadmeFailed",
  "Release saved.": "pages.marketplace.api.releaseSaved",
  "Release published.": "pages.marketplace.releasePublished",
  "Release deleted.": "pages.marketplace.api.releaseDeleted",
  "Could not save release.": "pages.marketplace.api.releaseSaveFailed",
  "Release not found.": "pages.marketplace.api.releaseNotFound",
  "Gitea settings saved.": "pages.marketplace.api.giteaSettingsSaved",
  "Gitea release will be validated on publish.": "pages.marketplace.api.releaseValidatedOnPublish",
  "Release package ZIP is missing or unreadable.": "pages.marketplace.api.releaseZipMissing",
  "ZIP contract validation passed.": "pages.marketplace.api.zipValidationPassed",
  "Slug is required for package upload.": "pages.marketplace.api.slugRequired",
  "Package must be a ZIP file.": "pages.marketplace.api.invalidZip",
  "Could not store package ZIP.": "pages.marketplace.api.uploadFailed",
  "دسترسی غیرمجاز.": "pages.marketplace.api.unauthorized",
  "خطا در بارگذاری دسته‌های مارکت‌پلیس.": "pages.marketplace.api.categoriesLoadError",
  "خطا در بارگذاری ماژول‌های مارکت‌پلیس.": "pages.marketplace.api.modulesLoadError",
}

export function translateMarketplaceMessage(
  t: TFunction,
  raw: string | undefined | null,
  fallbackKey: string,
): string {
  const trimmed = raw?.trim()
  if (!trimmed) return t(fallbackKey)
  const mapped = SERVER_MESSAGE_KEYS[trimmed]
  if (mapped) return t(mapped)
  return t(fallbackKey)
}

export function marketplaceError(
  t: TFunction,
  res: AjaxResponse<unknown> | null | undefined,
  fallbackKey: string,
): string {
  return translateMarketplaceMessage(t, getAjaxMessage(res), fallbackKey)
}

/** For dynamic Gitea/WP_Error text shown under a translated label. */
export function marketplaceTechnicalDetail(
  t: TFunction,
  raw: string | undefined | null,
): string | null {
  const trimmed = raw?.trim()
  if (!trimmed) return null
  const mapped = SERVER_MESSAGE_KEYS[trimmed]
  if (mapped) return t(mapped)
  return trimmed
}
