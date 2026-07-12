import { crmDelete, crmGet, crmPost } from "./client"

export interface ChatChannel {
  id: number
  name: string
  description?: string
  type?: string
  creator_name?: string
  member_count?: number
  message_count?: number
  unread_count?: number
  last_activity?: string
}

export interface DirectChat {
  user_id: number
  user_name: string
  user_email?: string
  last_message?: string
  last_message_time?: string
  unread_count?: number
}

export interface ChatMessage {
  id: number
  sender_id: number
  sender_name?: string
  channel_id?: number
  recipient_id?: number
  message: string
  message_type?: string
  sent_at: string
  parent_id?: number
}

export function getChatWsToken() {
  return crmGet<{ token: string; ws_url: string }>("chat/ws-token")
}

export function getChatChannels() {
  return crmGet<{ channels: ChatChannel[] }>("chat/channels")
}

export function getDirectChats() {
  return crmGet<{ chats: DirectChat[] }>("chat/direct")
}

export function getChatMessages(params: {
  channel_id?: number
  recipient_id?: number
  since?: string
  limit?: number
}) {
  return crmGet<{ messages: ChatMessage[] }>("chat/messages", params)
}

export function sendChatMessage(params: {
  channel_id?: number
  recipient_id?: number
  message: string
  message_type?: string
}) {
  return crmPost<{ message_id: number; item?: ChatMessage }>("chat/messages", params)
}

export function markChatRead(params: { channel_id?: number; message_id?: number }) {
  return crmPost("chat/read", params)
}

export function getChatUnreadCount(params?: { channel_id?: number; sender_id?: number }) {
  return crmGet<{ count: number }>("chat/unread-count", params)
}

export function searchChatMessages(query: string, channel_id?: number) {
  return crmGet<{ messages: ChatMessage[] }>("chat/search", { query, channel_id })
}

export function deleteChatMessage(id: number) {
  return crmDelete(`chat/messages/${id}`)
}

export function createChatChannel(params: {
  name: string
  description?: string
  type?: string
  members?: number[]
}) {
  return crmPost<{ channel_id: number }>("chat/channels", params)
}
