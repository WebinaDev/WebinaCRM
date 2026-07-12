import { FormSettingsSkeleton } from "@/components/skeletons"
import type {
  SettingsTab,
  CannedResponseItem,
  PositionItem,
  TaskCategoryItem,
} from "@/api/settings"
import { useSettingsTab } from "./hooks/useSettingsTab"
import type { SettingsTabShellProps } from "./types"
import { AuthenticationTab } from "./tabs/AuthenticationTab"
import { VisitorTrackingTab } from "./tabs/VisitorTrackingTab"
import { StyleTab } from "./tabs/StyleTab"
import { PaymentTab } from "./tabs/PaymentTab"
import { SmsTab } from "./tabs/SmsTab"
import { NotificationsTab } from "./tabs/NotificationsTab"
import { CrmCoreUpdateTab } from "./tabs/CrmCoreUpdateTab"
import { CannedResponsesTab } from "./tabs/CannedResponsesTab"
import { PositionsTab } from "./tabs/PositionsTab"
import { TaskCategoriesTab } from "./tabs/TaskCategoriesTab"
import { PlaceholderTab } from "./tabs/PlaceholderTab"

export function SettingsTabContent(props: SettingsTabShellProps) {
  const { tab, isRtl, onError, onMessage, loading, saving, setLoading, setSaving } = props
  const { data, form, setForm, load, handleSave } = useSettingsTab(
    tab,
    onError,
    onMessage,
    setLoading,
    setSaving,
  )

  if (loading) {
    return <FormSettingsSkeleton />
  }

  if (tab === "visitor_tracking") {
    return (
      <VisitorTrackingTab
        form={form}
        setForm={setForm}
        saving={saving}
        setSaving={setSaving}
        onError={onError}
        onMessage={onMessage}
      />
    )
  }

  if (tab === "authentication") {
    return (
      <AuthenticationTab
        form={form}
        setForm={setForm}
        saving={saving}
        onSave={() => void handleSave()}
      />
    )
  }

  if (tab === "crm_core_update") {
    return <CrmCoreUpdateTab onError={onError} onMessage={onMessage} />
  }

  if (tab === "style") {
    return (
      <StyleTab form={form} setForm={setForm} saving={saving} onSave={() => void handleSave()} />
    )
  }

  if (tab === "payment") {
    return (
      <PaymentTab form={form} setForm={setForm} saving={saving} onSave={() => void handleSave()} />
    )
  }

  if (tab === "sms") {
    return (
      <SmsTab form={form} setForm={setForm} saving={saving} onSave={() => void handleSave()} />
    )
  }

  if (tab === "notifications") {
    return (
      <NotificationsTab
        form={form}
        setForm={setForm}
        saving={saving}
        onSave={() => void handleSave()}
      />
    )
  }

  if (tab === "canned_responses") {
    const items = ((data?.items as CannedResponseItem[]) ?? []) as CannedResponseItem[]
    return (
      <CannedResponsesTab
        items={items}
        onError={onError}
        onMessage={onMessage}
        onReload={load}
        isRtl={isRtl}
      />
    )
  }

  if (tab === "positions") {
    const items = ((data?.items as PositionItem[]) ?? []) as PositionItem[]
    return (
      <PositionsTab
        items={items}
        onError={onError}
        onMessage={onMessage}
        onReload={load}
        isRtl={isRtl}
      />
    )
  }

  if (tab === "task_categories") {
    const items = ((data?.items as TaskCategoryItem[]) ?? []) as TaskCategoryItem[]
    return (
      <TaskCategoriesTab
        items={items}
        onError={onError}
        onMessage={onMessage}
        onReload={load}
        isRtl={isRtl}
      />
    )
  }

  if (["workflow", "automations", "forms", "leads"].includes(tab)) {
    return <PlaceholderTab tab={tab as SettingsTab} />
  }

  return <PlaceholderTab tab={tab} />
}
