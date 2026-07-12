export type AttributeType = 'select' | 'text' | 'color' | 'image' | 'button'

export type GlobalAttribute = {
  id: number
  label: string
  slug: string
  type: AttributeType
  order_by: string
  has_archives: boolean
  taxonomy: string
  term_count: number
}

export type AttributeTerm = {
  id: number
  name: string
  slug: string
  description: string
  menu_order: number
  count: number
  color?: string
  image_id?: number
  image_url?: string
}

export type AttributeFormState = {
  label: string
  slug: string
  type: AttributeType
  order_by: string
  has_archives: boolean
}

export type AttributeTermFormState = {
  name: string
  slug: string
  description: string
  menu_order: number
  color: string
  image_id: number
  image_url: string
}
