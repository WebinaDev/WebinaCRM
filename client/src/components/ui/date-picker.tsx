import * as React from "react"
import DatePickerLibImport from "react-multi-date-picker"
import DateObjectImport from "react-date-object"
import gregorianModule from "react-date-object/calendars/gregorian"
import { useTranslation } from "react-i18next"
import { getCalendarConfig } from "@/lib/locale"
import { resolveDatePickerComponent, unwrapDefaultExport } from "@/lib/cjs-default"
import { cn } from "@/lib/utils"

const DatePickerLib = resolveDatePickerComponent(DatePickerLibImport)
const DateObject = unwrapDefaultExport(DateObjectImport)
const gregorian = unwrapDefaultExport(gregorianModule)

type PickerDate = {
  convert: (calendar: unknown) => { format: (pattern: string) => string }
}

/** ISO date string YYYY-MM-DD (Gregorian) for API. */
export interface DatePickerProps {
  value: string
  onChange: (isoDate: string) => void
  disabled?: boolean
  required?: boolean
  placeholder?: string
  className?: string
  inputClass?: string
  id?: string
}

export const DatePicker = React.forwardRef<HTMLDivElement, DatePickerProps>(
  ({ value, onChange, disabled, required, placeholder, className, inputClass, id }, ref) => {
    const { t, i18n } = useTranslation()
    const lang = i18n.language === "en" ? "en" : "fa"
    const textDir = i18n.dir()
    const { calendar, locale } = React.useMemo(() => getCalendarConfig(lang), [lang])

    const inputClassName = cn(
      "flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30",
      inputClass
    )

    if (!DatePickerLib) {
      return (
        <div ref={ref} className={cn("w-full", className)} dir={textDir}>
          <input
            type="date"
            id={id}
            value={value}
            disabled={disabled}
            required={required}
            className={inputClassName}
            onChange={(e) => onChange(e.target.value)}
          />
        </div>
      )
    }

    const pickerValue = (() => {
      if (!value || value.trim() === "") return undefined
      try {
        return new DateObject(value)
      } catch {
        return undefined
      }
    })()

    const handleChange = (d: PickerDate | null) => {
      if (!d) {
        onChange("")
        return
      }
      const g = d.convert(gregorian)
      onChange(g.format("YYYY-MM-DD"))
    }

    return (
      <div ref={ref} className={cn("w-full", className)} dir={textDir}>
        <DatePickerLib
          value={pickerValue}
          onChange={handleChange}
          calendar={calendar}
          locale={locale}
          format="YYYY/MM/DD"
          disabled={disabled}
          required={required}
          placeholder={placeholder ?? t("datePicker.placeholder")}
          inputClass={inputClassName}
          containerClassName="w-full"
          className="rmdp-theme-crm"
          calendarPosition="bottom-start"
          id={id}
        />
      </div>
    )
  }
)
DatePicker.displayName = "DatePicker"
