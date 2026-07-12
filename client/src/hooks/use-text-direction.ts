import { useTranslation } from "react-i18next"

export function useTextDirection(): "rtl" | "ltr" {
  const { i18n } = useTranslation()
  return i18n.dir() === "rtl" ? "rtl" : "ltr"
}
