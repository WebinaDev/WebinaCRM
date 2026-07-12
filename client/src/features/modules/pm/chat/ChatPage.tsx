import { Skeleton } from "@/components/ui/skeleton"
import { useCallback, useEffect, useRef, useState } from "react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { ScrollArea } from "@/components/ui/scroll-area"
import { useLocale } from "@/hooks/use-locale"
import {
  getChatChannels,
  getChatMessages,
  getDirectChats,
  markChatRead,
  sendChatMessage,
  type ChatChannel,
  type ChatMessage,
  type DirectChat,
} from "@/api/chat"
import { getAjaxMessage } from "@/api/client"
import { useChatRealtime } from "@/lib/chat-realtime"
import { PmPageHeader } from "@/features/shared/pm/PmPageHeader"
import { PmAlerts } from "@/features/shared/pm/PmAlerts"
import { Loader2, MessageSquare, Send, Wifi, WifiOff } from "lucide-react"
import { cn } from "@/lib/utils"

type Conversation =
  | { kind: "channel"; id: number; title: string }
  | { kind: "direct"; id: number; title: string }

export function ChatPage() {
  const { t, isRtl, formatDateTime } = useLocale()
  const [tab, setTab] = useState<"channels" | "direct">("channels")
  const [channels, setChannels] = useState<ChatChannel[]>([])
  const [directs, setDirects] = useState<DirectChat[]>([])
  const [conversation, setConversation] = useState<Conversation | null>(null)
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [draft, setDraft] = useState("")
  const [loadingList, setLoadingList] = useState(true)
  const [loadingMessages, setLoadingMessages] = useState(false)
  const [sending, setSending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const sinceRef = useRef<string | null>(null)
  const bottomRef = useRef<HTMLDivElement>(null)

  const wsConversation = conversation
    ? conversation.kind === "channel"
      ? { kind: "channel" as const, id: conversation.id }
      : { kind: "direct" as const, id: conversation.id }
    : null

  const appendMessage = useCallback((msg: ChatMessage) => {
    setMessages((prev) => {
      if (prev.some((m) => m.id === msg.id)) return prev
      const merged = [...prev, msg].sort((a, b) => a.sent_at.localeCompare(b.sent_at))
      sinceRef.current = merged[merged.length - 1]?.sent_at ?? sinceRef.current
      return merged
    })
  }, [])

  const { connected, reconnecting } = useChatRealtime(wsConversation, appendMessage)

  const loadLists = useCallback(async () => {
    setLoadingList(true)
    setError(null)
    try {
      const [chRes, dmRes] = await Promise.all([getChatChannels(), getDirectChats()])
      if (chRes.success && chRes.data?.channels) {
        setChannels(chRes.data.channels)
      }
      if (dmRes.success && dmRes.data?.chats) {
        setDirects(dmRes.data.chats)
      }
      if (!chRes.success && !dmRes.success) {
        setError(getAjaxMessage(chRes) ?? getAjaxMessage(dmRes) ?? t("pages.chat.loadError"))
      }
    } catch {
      setError(t("pages.chat.loadError"))
    } finally {
      setLoadingList(false)
    }
  }, [t])

  const loadMessages = useCallback(
    async (poll = false) => {
      if (!conversation) return
      if (!poll) setLoadingMessages(true)
      try {
        const params =
          conversation.kind === "channel"
            ? { channel_id: conversation.id, since: poll ? sinceRef.current ?? undefined : undefined, limit: 50 }
            : { recipient_id: conversation.id, since: poll ? sinceRef.current ?? undefined : undefined, limit: 50 }
        const res = await getChatMessages(params)
        if (res.success && res.data?.messages) {
          const list = res.data.messages
          if (poll && list.length > 0) {
            setMessages((prev) => {
              const ids = new Set(prev.map((m) => m.id))
              const merged = [...prev]
              for (const m of list) {
                if (!ids.has(m.id)) merged.push(m)
              }
              return merged.sort((a, b) => a.sent_at.localeCompare(b.sent_at))
            })
          } else if (!poll) {
            setMessages(list)
          }
          const last = list[list.length - 1]
          if (last?.sent_at) sinceRef.current = last.sent_at
        }
      } catch {
        if (!poll) setError(t("pages.chat.loadError"))
      } finally {
        if (!poll) setLoadingMessages(false)
      }
    },
    [conversation, t],
  )

  useEffect(() => {
    void loadLists()
  }, [loadLists])

  useEffect(() => {
    sinceRef.current = null
    setMessages([])
    if (conversation) {
      void loadMessages(false)
      if (conversation.kind === "channel") {
        void markChatRead({ channel_id: conversation.id })
      }
    }
  }, [conversation, loadMessages])

  useEffect(() => {
    if (!conversation || connected) return
    const id = window.setInterval(() => void loadMessages(true), 30000)
    return () => window.clearInterval(id)
  }, [conversation, loadMessages, connected])

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: "smooth" })
  }, [messages])

  const selectChannel = (ch: ChatChannel) => {
    setConversation({ kind: "channel", id: ch.id, title: ch.name })
  }

  const selectDirect = (d: DirectChat) => {
    setConversation({ kind: "direct", id: d.user_id, title: d.user_name })
  }

  const handleSend = async () => {
    const text = draft.trim()
    if (!text || !conversation) return
    setSending(true)
    setError(null)
    try {
      const params =
        conversation.kind === "channel"
          ? { channel_id: conversation.id, message: text }
          : { recipient_id: conversation.id, message: text }
      const res = await sendChatMessage(params)
      if (res.success) {
        setDraft("")
        if (res.data?.item) {
          setMessages((prev) => [...prev, res.data!.item!])
          sinceRef.current = res.data.item.sent_at
        } else {
          await loadMessages(false)
        }
      } else {
        setError(getAjaxMessage(res) ?? t("pages.chat.loadError"))
      }
    } catch {
      setError(t("pages.chat.loadError"))
    } finally {
      setSending(false)
    }
  }

  const sidebarList =
    tab === "channels"
      ? channels.map((ch) => (
          <button
            key={ch.id}
            type="button"
            onClick={() => selectChannel(ch)}
            className={cn(
              "flex w-full items-center justify-between rounded-md px-3 py-2 text-start text-sm hover:bg-muted",
              conversation?.kind === "channel" && conversation.id === ch.id && "bg-muted font-medium",
            )}
          >
            <span className="truncate">{ch.name}</span>
            {(ch.unread_count ?? 0) > 0 && (
              <span className="ms-2 shrink-0 rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground">
                {ch.unread_count}
              </span>
            )}
          </button>
        ))
      : directs.map((d) => (
          <button
            key={d.user_id}
            type="button"
            onClick={() => selectDirect(d)}
            className={cn(
              "flex w-full items-center justify-between rounded-md px-3 py-2 text-start text-sm hover:bg-muted",
              conversation?.kind === "direct" && conversation.id === d.user_id && "bg-muted font-medium",
            )}
          >
            <span className="truncate">{d.user_name}</span>
            {(d.unread_count ?? 0) > 0 && (
              <span className="ms-2 shrink-0 rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground">
                {d.unread_count}
              </span>
            )}
          </button>
        ))

  return (
    <div className="space-y-4" dir={isRtl ? "rtl" : "ltr"}>
      <PmPageHeader title={t("pages.chat.title")} isRtl={isRtl} />
      <PmAlerts error={error} />

      <div className="grid gap-4 lg:grid-cols-[280px_1fr]">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base flex items-center gap-2">
              <MessageSquare className="h-4 w-4" />
              {t("pages.chat.title")}
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <Tabs value={tab} onValueChange={(v) => setTab(v as "channels" | "direct")}>
              <TabsList className="w-full">
                <TabsTrigger value="channels" className="flex-1">
                  {t("pages.chat.channels")}
                </TabsTrigger>
                <TabsTrigger value="direct" className="flex-1">
                  {t("pages.chat.direct")}
                </TabsTrigger>
              </TabsList>
              <TabsContent value="channels" className="mt-2">
                {loadingList ? (
                  <div className="space-y-2 py-1">
                    {Array.from({ length: 6 }).map((_, i) => (
                      <Skeleton key={i} className="h-12 w-full rounded-md" />
                    ))}
                  </div>
                ) : (
                  <ScrollArea className="h-[360px]">{sidebarList}</ScrollArea>
                )}
              </TabsContent>
              <TabsContent value="direct" className="mt-2">
                {loadingList ? (
                  <div className="space-y-2 py-1">
                    {Array.from({ length: 6 }).map((_, i) => (
                      <Skeleton key={i} className="h-12 w-full rounded-md" />
                    ))}
                  </div>
                ) : (
                  <ScrollArea className="h-[360px]">{sidebarList}</ScrollArea>
                )}
              </TabsContent>
            </Tabs>
          </CardContent>
        </Card>

        <Card className="flex min-h-[480px] flex-col">
          <CardHeader className="border-b py-3">
            <CardTitle className="text-base flex items-center gap-2">
              {conversation?.title ?? t("pages.chat.empty")}
              {conversation && (
                <span className="text-xs font-normal text-muted-foreground inline-flex items-center gap-1">
                  {connected ? (
                    <>
                      <Wifi className="h-3.5 w-3.5 text-green-600" />
                      {t("pages.chat.wsConnected")}
                    </>
                  ) : (
                    <>
                      <WifiOff className="h-3.5 w-3.5" />
                      {reconnecting ? t("pages.chat.wsReconnecting") : t("pages.chat.wsOffline")}
                    </>
                  )}
                </span>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent className="flex flex-1 flex-col gap-3 p-4">
            {!conversation ? (
              <p className="text-sm text-muted-foreground py-8 text-center">{t("pages.chat.empty")}</p>
            ) : (
              <>
                <ScrollArea className="flex-1 min-h-[320px] pe-2">
                  {loadingMessages ? (
                    <div className="space-y-3 py-2">
                      {Array.from({ length: 6 }).map((_, i) => (
                        <Skeleton
                          key={i}
                          className={`h-12 rounded-lg ${i % 2 === 0 ? "w-2/3" : "ms-auto w-1/2"}`}
                        />
                      ))}
                    </div>
                  ) : (
                    <div className="space-y-3">
                      {messages.map((m) => (
                        <div key={m.id} className="rounded-lg border bg-muted/40 px-3 py-2 text-sm">
                          <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground mb-1">
                            <span className="font-medium text-foreground">{m.sender_name ?? `#${m.sender_id}`}</span>
                            <span>{formatDateTime(m.sent_at)}</span>
                          </div>
                          <p className="whitespace-pre-wrap break-words">{m.message}</p>
                        </div>
                      ))}
                      <div ref={bottomRef} />
                    </div>
                  )}
                </ScrollArea>
                <div className="flex gap-2">
                  <Input
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    placeholder={t("pages.chat.placeholder")}
                    onKeyDown={(e) => e.key === "Enter" && !e.shiftKey && void handleSend()}
                    disabled={sending}
                  />
                  <Button type="button" onClick={() => void handleSend()} disabled={sending || !draft.trim()}>
                    {sending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                    <span className="sr-only">{t("pages.chat.send")}</span>
                  </Button>
                </div>
              </>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
