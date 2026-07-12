import { useCallback, useRef, useState } from "react"
import ReactMarkdown from "react-markdown"
import remarkGfm from "remark-gfm"
import {
  Bold,
  Code,
  Heading2,
  Italic,
  Link2,
  List,
  ListOrdered,
} from "lucide-react"
import { Textarea } from "@/components/ui/textarea"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"
import { useLocale } from "@/hooks/use-locale"

type Props = {
  value: string
  onChange: (value: string) => void
  className?: string
  minHeight?: string
}

function wrapSelection(
  textarea: HTMLTextAreaElement,
  before: string,
  after: string,
  placeholder: string,
) {
  const start = textarea.selectionStart
  const end = textarea.selectionEnd
  const selected = textarea.value.slice(start, end) || placeholder
  const next = textarea.value.slice(0, start) + before + selected + after + textarea.value.slice(end)
  const cursorStart = start + before.length
  const cursorEnd = cursorStart + selected.length
  return { next, cursorStart, cursorEnd }
}

function prefixLines(textarea: HTMLTextAreaElement, prefix: string, placeholder: string) {
  const start = textarea.selectionStart
  const end = textarea.selectionEnd
  const block = textarea.value.slice(start, end) || placeholder
  const lines = block.split("\n").map((line) => `${prefix}${line}`)
  const next = textarea.value.slice(0, start) + lines.join("\n") + textarea.value.slice(end)
  return { next, cursorStart: start, cursorEnd: start + lines.join("\n").length }
}

export function MarkdownField({ value, onChange, className, minHeight = "min-h-[360px]" }: Props) {
  const { t, isRtl } = useLocale()
  const [tab, setTab] = useState<"edit" | "preview">("edit")
  const textareaRef = useRef<HTMLTextAreaElement>(null)

  const applyEdit = useCallback(
    (fn: (el: HTMLTextAreaElement) => { next: string; cursorStart: number; cursorEnd: number }) => {
      const el = textareaRef.current
      if (!el) return
      const { next, cursorStart, cursorEnd } = fn(el)
      onChange(next)
      requestAnimationFrame(() => {
        el.focus()
        el.setSelectionRange(cursorStart, cursorEnd)
      })
    },
    [onChange],
  )

  return (
    <div className={cn("space-y-2", className)}>
      <div className="flex flex-wrap gap-2">
        <Button
          type="button"
          size="sm"
          variant={tab === "edit" ? "default" : "outline"}
          onClick={() => setTab("edit")}
        >
          {t("pages.marketplace.markdownEdit")}
        </Button>
        <Button
          type="button"
          size="sm"
          variant={tab === "preview" ? "default" : "outline"}
          onClick={() => setTab("preview")}
        >
          {t("pages.marketplace.markdownPreview")}
        </Button>
      </div>
      {tab === "edit" ? (
        <div className="overflow-hidden rounded-md border border-input bg-background">
          <div className="flex flex-wrap gap-0.5 border-b border-input bg-muted/40 p-1.5">
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              className="size-8"
              title={t("pages.marketplace.markdownToolbar.bold")}
              onClick={() =>
                applyEdit((el) =>
                  wrapSelection(el, "**", "**", t("pages.marketplace.markdownToolbar.placeholderText")),
                )
              }
            >
              <Bold className="size-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              className="size-8"
              title={t("pages.marketplace.markdownToolbar.italic")}
              onClick={() =>
                applyEdit((el) =>
                  wrapSelection(el, "*", "*", t("pages.marketplace.markdownToolbar.placeholderText")),
                )
              }
            >
              <Italic className="size-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              className="size-8"
              title={t("pages.marketplace.markdownToolbar.heading")}
              onClick={() =>
                applyEdit((el) => {
                  const start = el.selectionStart
                  const lineStart = el.value.lastIndexOf("\n", start - 1) + 1
                  const next =
                    el.value.slice(0, lineStart) + "## " + el.value.slice(lineStart)
                  return { next, cursorStart: start + 3, cursorEnd: start + 3 }
                })
              }
            >
              <Heading2 className="size-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              className="size-8"
              title={t("pages.marketplace.markdownToolbar.bulletList")}
              onClick={() =>
                applyEdit((el) =>
                  prefixLines(el, "- ", t("pages.marketplace.markdownToolbar.placeholderItem")),
                )
              }
            >
              <List className="size-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              className="size-8"
              title={t("pages.marketplace.markdownToolbar.orderedList")}
              onClick={() =>
                applyEdit((el) => {
                  const start = el.selectionStart
                  const end = el.selectionEnd
                  const block = el.value.slice(start, end) || t("pages.marketplace.markdownToolbar.placeholderItem")
                  const lines = block.split("\n").map((line, i) => `${i + 1}. ${line}`)
                  const next = el.value.slice(0, start) + lines.join("\n") + el.value.slice(end)
                  return { next, cursorStart: start, cursorEnd: start + lines.join("\n").length }
                })
              }
            >
              <ListOrdered className="size-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              className="size-8"
              title={t("pages.marketplace.markdownToolbar.code")}
              onClick={() =>
                applyEdit((el) =>
                  wrapSelection(el, "`", "`", t("pages.marketplace.markdownToolbar.placeholderText")),
                )
              }
            >
              <Code className="size-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              className="size-8"
              title={t("pages.marketplace.markdownToolbar.link")}
              onClick={() => {
                const url = window.prompt(t("pages.marketplace.markdownToolbar.linkPrompt"), "https://")
                if (!url) return
                applyEdit((el) =>
                  wrapSelection(
                    el,
                    "[",
                    `](${url})`,
                    t("pages.marketplace.markdownToolbar.placeholderText"),
                  ),
                )
              }}
            >
              <Link2 className="size-4" />
            </Button>
          </div>
          <Textarea
            ref={textareaRef}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            className={cn("resize-y border-0 font-mono text-sm shadow-none focus-visible:ring-0", minHeight)}
            dir="ltr"
          />
        </div>
      ) : (
        <div
          className={cn(
            "border-border prose prose-sm dark:prose-invert max-w-none overflow-y-auto rounded-md border p-4",
            minHeight,
            isRtl && "prose-rtl text-start",
          )}
          dir={isRtl ? "rtl" : "ltr"}
        >
          {value.trim() ? (
            <ReactMarkdown remarkPlugins={[remarkGfm]}>{value}</ReactMarkdown>
          ) : (
            <p className="text-muted-foreground text-sm">{t("pages.marketplace.markdownEmpty")}</p>
          )}
        </div>
      )}
    </div>
  )
}
