import { crmPostForm } from "./client"

export async function updateProfile(data: {
  first_name: string
  last_name: string
  webino_mobile_phone?: string
  password?: string
  password_confirm?: string
  webino_profile_picture?: File
}) {
  const formData = new FormData()
  formData.set("first_name", data.first_name)
  formData.set("last_name", data.last_name)
  if (data.webino_mobile_phone != null) formData.set("webino_mobile_phone", data.webino_mobile_phone)
  if (data.password?.trim()) formData.set("password", data.password)
  if (data.password_confirm?.trim()) formData.set("password_confirm", data.password_confirm)
  if (data.webino_profile_picture) formData.set("webino_profile_picture", data.webino_profile_picture)

  return crmPostForm<{ message?: string; reload?: boolean }>("profile", formData)
}
