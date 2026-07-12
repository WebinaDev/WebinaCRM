import Link from "@tiptap/extension-link"
import Placeholder from "@tiptap/extension-placeholder"
import { EditorContent, useEditor } from "@tiptap/react"
import StarterKit from "@tiptap/starter-kit"
import {
  Bold,
  Code,
  Heading2,
  Italic,
  Link2,
  List,
  ListOrdered,
  Quote,
  Strikethrough,
} from "lucide-react"
import { useEffect, useState, type ReactNode } from "react"
import { Button } from "@/components/ui/button"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Textarea } from "@/components/ui/textarea"
import { useLocale } from "@/hooks/use-locale"
import { cn } from "@/lib/utils"

type Props = {
  value: string
  onChange: (html: string) => void
  placeholder?: string
  className?: string
  dir?: "rtl" | "ltr"
  disabled?: boolean
}

function ToolbarButton({
  active,
  onClick,
  children,
  title,
}: {
  active?: boolean
  onClick: () => void
  children: ReactNode
  title: string
}) {
  return (
    <Button
      type="button"
      variant={active ? "secondary" : "ghost"}
      size="icon-sm"
      className="size-8"
      title={title}
      onClick={onClick}
    >
      {children}
    </Button>
  )
}

export function RichTextEditor({
  value,
  onChange,
  placeholder,
  className,
  dir = "rtl",
  disabled,
}: Props) {
  const { t } = useLocale()
  const [tab, setTab] = useState<"visual" | "code">("visual")
  const [codeValue, setCodeValue] = useState(value)

  const editor = useEditor({
    immediatelyRender: false,
    extensions: [
      StarterKit.configure({ link: false }),
      Link.configure({ openOnClick: false, autolink: true }),
      Placeholder.configure({ placeholder: placeholder ?? "" }),
    ],
    content: value || "",
    editable: !disabled,
    onUpdate: ({ editor: ed }) => {
      const html = ed.getHTML()
      onChange(html)
      setCodeValue(html)
    },
    editorProps: {
      attributes: {
        class: "prose prose-sm dark:prose-invert max-w-none min-h-[120px] outline-none",
        dir,
      },
    },
  })

  useEffect(() => {
    if (!editor) return
    editor.setEditable(!disabled)
  }, [disabled, editor])

  useEffect(() => {
    if (!editor) return
    const current = editor.getHTML()
    if (value !== current) {
      editor.commands.setContent(value || "", { emitUpdate: false })
      setCodeValue(value || "")
    }
  }, [value, editor])

  function switchTab(next: string) {
    if (next === "code" && editor) {
      setCodeValue(editor.getHTML())
    }
    if (next === "visual" && editor) {
      editor.commands.setContent(codeValue, { emitUpdate: false })
      onChange(codeValue)
    }
    setTab(next as "visual" | "code")
  }

  function applyCodeChange(html: string) {
    setCodeValue(html)
    onChange(html)
    if (editor) {
      editor.commands.setContent(html, { emitUpdate: false })
    }
  }

  function setLink() {
    if (!editor) return
    const prev = editor.getAttributes("link").href as string | undefined
    const url = window.prompt(t("pages.marketplace.richEditor.linkPrompt"), prev ?? "https://")
    if (url === null) return
    if (url === "") {
      editor.chain().focus().extendMarkRange("link").unsetLink().run()
      return
    }
    editor.chain().focus().extendMarkRange("link").setLink({ href: url }).run()
  }

  return (
    <Tabs value={tab} onValueChange={switchTab} className={cn("gap-0", className)} dir={dir}>
      <div className="flex flex-wrap items-center justify-between gap-2 rounded-t-md border border-b-0 border-input bg-muted/40 px-2 py-1.5">
        <TabsList className="h-8 bg-transparent p-0">
          <TabsTrigger value="visual" className="h-7 px-3 text-xs">
            {t("pages.marketplace.richEditor.visual")}
          </TabsTrigger>
          <TabsTrigger value="code" className="h-7 px-3 text-xs">
            {t("pages.marketplace.richEditor.code")}
          </TabsTrigger>
        </TabsList>
        {tab === "visual" && editor ? (
          <div className="flex flex-wrap items-center gap-0.5">
            <ToolbarButton
              active={editor.isActive("bold")}
              onClick={() => editor.chain().focus().toggleBold().run()}
              title={t("pages.marketplace.richEditor.bold")}
            >
              <Bold className="size-4" />
            </ToolbarButton>
            <ToolbarButton
              active={editor.isActive("italic")}
              onClick={() => editor.chain().focus().toggleItalic().run()}
              title={t("pages.marketplace.richEditor.italic")}
            >
              <Italic className="size-4" />
            </ToolbarButton>
            <ToolbarButton
              active={editor.isActive("strike")}
              onClick={() => editor.chain().focus().toggleStrike().run()}
              title={t("pages.marketplace.richEditor.strike")}
            >
              <Strikethrough className="size-4" />
            </ToolbarButton>
            <ToolbarButton
              active={editor.isActive("heading", { level: 2 })}
              onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
              title={t("pages.marketplace.richEditor.heading")}
            >
              <Heading2 className="size-4" />
            </ToolbarButton>
            <ToolbarButton
              active={editor.isActive("bulletList")}
              onClick={() => editor.chain().focus().toggleBulletList().run()}
              title={t("pages.marketplace.richEditor.bulletList")}
            >
              <List className="size-4" />
            </ToolbarButton>
            <ToolbarButton
              active={editor.isActive("orderedList")}
              onClick={() => editor.chain().focus().toggleOrderedList().run()}
              title={t("pages.marketplace.richEditor.orderedList")}
            >
              <ListOrdered className="size-4" />
            </ToolbarButton>
            <ToolbarButton
              active={editor.isActive("blockquote")}
              onClick={() => editor.chain().focus().toggleBlockquote().run()}
              title={t("pages.marketplace.richEditor.quote")}
            >
              <Quote className="size-4" />
            </ToolbarButton>
            <ToolbarButton
              active={editor.isActive("codeBlock")}
              onClick={() => editor.chain().focus().toggleCodeBlock().run()}
              title={t("pages.marketplace.richEditor.codeBlock")}
            >
              <Code className="size-4" />
            </ToolbarButton>
            <ToolbarButton
              active={editor.isActive("link")}
              onClick={setLink}
              title={t("pages.marketplace.richEditor.link")}
            >
              <Link2 className="size-4" />
            </ToolbarButton>
          </div>
        ) : null}
      </div>
      <TabsContent value="visual" className="mt-0">
        <EditorContent
          editor={editor}
          className={cn(
            "min-h-[200px] rounded-b-md border border-input bg-background px-3 py-2 text-sm",
            "[&_.tiptap]:min-h-[180px] [&_.tiptap]:outline-none",
            "[&_.tiptap_p.is-editor-empty:first-child]:before:pointer-events-none [&_.tiptap_p.is-editor-empty:first-child]:before:float-start [&_.tiptap_p.is-editor-empty:first-child]:before:h-0 [&_.tiptap_p.is-editor-empty:first-child]:before:text-muted-foreground [&_.tiptap_p.is-editor-empty:first-child]:before:content-[attr(data-placeholder)]",
            disabled && "pointer-events-none opacity-60",
          )}
        />
      </TabsContent>
      <TabsContent value="code" className="mt-0">
        <Textarea
          value={codeValue}
          onChange={(e) => applyCodeChange(e.target.value)}
          disabled={disabled}
          className="min-h-[200px] rounded-t-none rounded-b-md border border-input font-mono text-xs leading-relaxed"
          dir="ltr"
          spellCheck={false}
        />
      </TabsContent>
    </Tabs>
  )
}
