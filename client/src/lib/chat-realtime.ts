import { useCallback, useEffect, useRef, useState } from "react"
import { getChatWsToken, type ChatMessage } from "@/api/chat"

type Conversation =
  | { kind: "channel"; id: number }
  | { kind: "direct"; id: number }

type WsPayload = {
  type?: string
  message?: ChatMessage
}

function matchConversation(msg: ChatMessage, conv: Conversation): boolean {
  if (conv.kind === "channel") {
    return msg.channel_id === conv.id
  }
  const uid = conv.id
  return msg.sender_id === uid || msg.recipient_id === uid
}

export function useChatRealtime(
  conversation: Conversation | null,
  onMessage: (msg: ChatMessage) => void,
) {
  const [connected, setConnected] = useState(false)
  const [reconnecting, setReconnecting] = useState(false)
  const wsRef = useRef<WebSocket | null>(null)
  const onMessageRef = useRef(onMessage)
  onMessageRef.current = onMessage

  const connect = useCallback(async () => {
    const cfg = window.webinoDashboard
    if (!cfg?.wsEnabled) return false

    try {
      const res = await getChatWsToken()
      if (!res.success || !res.data?.token) return false
      const wsUrl = res.data.ws_url || cfg.wsUrl
      if (!wsUrl) return false

      const url = `${wsUrl}${wsUrl.includes("?") ? "&" : "?"}token=${encodeURIComponent(res.data.token)}`
      const ws = new WebSocket(url)
      wsRef.current = ws

      ws.onopen = () => {
        setConnected(true)
        setReconnecting(false)
      }
      ws.onclose = () => {
        setConnected(false)
        wsRef.current = null
      }
      ws.onerror = () => {
        setConnected(false)
      }
      ws.onmessage = (ev) => {
        try {
          const data = JSON.parse(String(ev.data)) as WsPayload
          if (data.type === "chat_message" && data.message && conversation) {
            if (matchConversation(data.message, conversation)) {
              onMessageRef.current(data.message)
            }
          }
        } catch {
          /* ignore */
        }
      }
      return true
    } catch {
      return false
    }
  }, [conversation])

  useEffect(() => {
    let cancelled = false
    let retryTimer: number | undefined

    const run = async () => {
      if (cancelled) return
      setReconnecting(true)
      const ok = await connect()
      if (!ok && !cancelled) {
        retryTimer = window.setTimeout(run, 10000)
      }
    }
    void run()

    return () => {
      cancelled = true
      if (retryTimer) window.clearTimeout(retryTimer)
      wsRef.current?.close()
      wsRef.current = null
    }
  }, [connect])

  return { connected, reconnecting }
}
