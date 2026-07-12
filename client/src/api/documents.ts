import { crmDelete, crmGet, crmPatch, crmPost, crmPostForm, getConfigOrNull } from "./client"

export interface DocumentItem {
  id: number
  title: string
  file_name: string
  file_size?: number
  file_type?: string
  mime_type?: string
  entity_type?: string
  entity_id?: number
  folder_id?: number
  uploaded_at?: string
  uploader_name?: string
  downloads?: number
}

export function getDocuments(params?: {
  entity_type?: string
  entity_id?: number
  folder_id?: number
  search?: string
  limit?: number
  offset?: number
}) {
  return crmGet<{ documents: DocumentItem[] }>("documents", params)
}

export function uploadDocument(formData: FormData) {
  return crmPostForm<{ document_id: number }>("documents", formData)
}

export function deleteDocument(id: number, permanent = false) {
  return crmDelete(`documents/${id}`, { permanent: permanent ? 1 : 0 })
}

export function renameDocument(id: number, title: string) {
  return crmPatch(`documents/${id}`, { title })
}

export function moveDocument(id: number, folder_id: number) {
  return crmPatch(`documents/${id}`, { folder_id })
}

export function createDocumentFolder(name: string, parent_id = 0) {
  return crmPost<{ folder_id: number }>("documents/folders", { name, parent_id })
}

export function getDocumentDownloadUrl(id: number): string | null {
  const c = getConfigOrNull()
  if (!c?.restUrl || !c?.nonce) return null
  const base = c.restUrl.replace(/\/$/, "")
  const params = new URLSearchParams({ _wpnonce: c.nonce })
  return `${base}/documents/${id}/download?${params.toString()}`
}
