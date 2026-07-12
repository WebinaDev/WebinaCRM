import type { LucideIcon } from "lucide-react"
import {
  Shield,
  Palette,
  GitBranch,
  Network,
  Tag,
  Bot,
  MessageSquare,
  Bell,
  MessageCircle,
  FileText,
  UserPlus,
  CreditCard,
  Settings2,
  FolderKanban,
  Users,
  Calculator,
  LineChart,
} from "lucide-react"
import type { SettingsTab } from "@/api/settings"

export type SettingsHubModuleId =
  | "general"
  | "projects"
  | "crm"
  | "bots"
  | "accounting"

export interface SettingsModuleTab {
  id: SettingsTab | "accounting_full"
  titleKey: string
  icon: LucideIcon
  path: string
}

export interface SettingsHubModule {
  id: SettingsHubModuleId
  /** Key in webinocrm_settings: module_{settingsKey}_enabled */
  settingsKey: string
  titleKey: string
  descriptionKey: string
  icon: LucideIcon
  detailPath: string
  defaultTab: string
  tabs: SettingsModuleTab[]
}

export const SETTINGS_HUB_MODULES: SettingsHubModule[] = [
  {
    id: "general",
    settingsKey: "general",
    titleKey: "pages.settings.hub.modules.general.title",
    descriptionKey: "pages.settings.hub.modules.general.description",
    icon: Settings2,
    detailPath: "/admin/settings/general",
    defaultTab: "authentication",
    tabs: [
      {
        id: "authentication",
        titleKey: "pages.settings.احراز_هویت",
        icon: Shield,
        path: "authentication",
      },
      {
        id: "style",
        titleKey: "pages.settings.ظاهر_و_استایل",
        icon: Palette,
        path: "style",
      },
      {
        id: "visitor_tracking",
        titleKey: "pages.settings.hub.tabs.visitor_tracking",
        icon: LineChart,
        path: "visitor-tracking",
      },
    ],
  },
  {
    id: "projects",
    settingsKey: "projects",
    titleKey: "pages.settings.hub.modules.projects.title",
    descriptionKey: "pages.settings.hub.modules.projects.description",
    icon: FolderKanban,
    detailPath: "/admin/settings/projects",
    defaultTab: "workflow",
    tabs: [
      {
        id: "workflow",
        titleKey: "pages.settings.hub.tabs.workflow",
        icon: GitBranch,
        path: "workflow",
      },
      {
        id: "positions",
        titleKey: "pages.settings.جایگاههای_شغلی",
        icon: Network,
        path: "positions",
      },
      {
        id: "task_categories",
        titleKey: "pages.settings.دستهبندی_وظایف",
        icon: Tag,
        path: "task_categories",
      },
      {
        id: "automations",
        titleKey: "pages.settings.hub.tabs.automations",
        icon: Bot,
        path: "automations",
      },
    ],
  },
  {
    id: "crm",
    settingsKey: "crm",
    titleKey: "pages.settings.hub.modules.crm.title",
    descriptionKey: "pages.settings.hub.modules.crm.description",
    icon: Users,
    detailPath: "/admin/settings/crm",
    defaultTab: "sms",
    tabs: [
      {
        id: "sms",
        titleKey: "pages.settings.سامانه_پیامک",
        icon: MessageSquare,
        path: "sms",
      },
      {
        id: "notifications",
        titleKey: "pages.settings.اطلاعرسانیها",
        icon: Bell,
        path: "notifications",
      },
      {
        id: "canned_responses",
        titleKey: "pages.settings.پاسخهای_آماده",
        icon: MessageCircle,
        path: "canned_responses",
      },
      {
        id: "forms",
        titleKey: "pages.settings.hub.tabs.forms",
        icon: FileText,
        path: "forms",
      },
      {
        id: "leads",
        titleKey: "pages.settings.hub.tabs.leads",
        icon: UserPlus,
        path: "leads",
      },
      {
        id: "crm_core_update",
        titleKey: "pages.settings.hub.tabs.crm_core_update",
        icon: GitBranch,
        path: "core-update",
      },
    ],
  },
  {
    id: "bots",
    settingsKey: "bots",
    titleKey: "pages.settings.hub.modules.bots.title",
    descriptionKey: "pages.settings.hub.modules.bots.description",
    icon: Bot,
    detailPath: "/admin/settings/bots",
    defaultTab: "",
    tabs: [],
  },
  {
    id: "accounting",
    settingsKey: "accounting",
    titleKey: "pages.settings.hub.modules.accounting.title",
    descriptionKey: "pages.settings.hub.modules.accounting.description",
    icon: Calculator,
    detailPath: "/admin/settings/accounting",
    defaultTab: "payment",
    tabs: [
      {
        id: "payment",
        titleKey: "pages.settings.درگاه_پرداخت",
        icon: CreditCard,
        path: "payment",
      },
      {
        id: "accounting_full",
        titleKey: "pages.settings.hub.tabs.accounting_full",
        icon: Calculator,
        path: "accounting-full",
      },
    ],
  },
]

export function getHubModule(id: string) {
  return SETTINGS_HUB_MODULES.find((m) => m.id === id)
}
