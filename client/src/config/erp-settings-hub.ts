import type { LucideIcon } from "lucide-react"
import {
  Users,
  Calculator,
  UserPlus,
  FolderKanban,
  Warehouse,
  Megaphone,
  Factory,
  FileText,
  Settings2,
  Store,
  MessageSquare,
  Bot,
} from "lucide-react"

import { ERP_MODULES, ERP_SUBMODULES } from "@/modules/registry"

export type ErpHubModuleId =
  | "hrm"
  | "finance"
  | "crm"
  | "pm"
  | "scm"
  | "sales"
  | "mfg"
  | "docs"
  | "distribution"
  | "admin"

export type ErpHubSubmoduleId = "modirpayamak" | "bale_business"

export interface ErpHubModuleCard {
  id: ErpHubModuleId
  settingsKey: string
  titleKey: string
  descriptionKey: string
  icon: LucideIcon
}

export interface ErpHubSubmoduleCard {
  id: ErpHubSubmoduleId
  settingsKey: string
  titleKey: string
  descriptionKey: string
  icon: LucideIcon
}

const ICONS: Record<ErpHubModuleId, LucideIcon> = {
  hrm: Users,
  finance: Calculator,
  crm: UserPlus,
  pm: FolderKanban,
  scm: Warehouse,
  sales: Megaphone,
  mfg: Factory,
  docs: FileText,
  distribution: Store,
  admin: Settings2,
}

const SUBMODULE_ICONS: Record<ErpHubSubmoduleId, LucideIcon> = {
  modirpayamak: MessageSquare,
  bale_business: Bot,
}

export const ERP_SETTINGS_HUB_MODULES: ErpHubModuleCard[] = ERP_MODULES.map((m) => ({
  id: m.id as ErpHubModuleId,
  settingsKey: m.settingsKey,
  titleKey: m.sidebarCategoryKey,
  descriptionKey: `nav.erp.${m.id}.description`,
  icon: ICONS[m.id as ErpHubModuleId],
}))

export const ERP_SETTINGS_HUB_SUBMODULES: ErpHubSubmoduleCard[] = ERP_SUBMODULES.map((sub) => ({
  id: sub.settingsKey as ErpHubSubmoduleId,
  settingsKey: sub.settingsKey,
  titleKey: `nav.erp.submodule.${sub.settingsKey}.title`,
  descriptionKey: `nav.erp.submodule.${sub.settingsKey}.description`,
  icon: SUBMODULE_ICONS[sub.settingsKey as ErpHubSubmoduleId],
}))
