export type ProductAttribute = {
  name: string
  options: string[] | number[]
  variation?: boolean
  visible?: boolean
  attribute_id?: number
  taxonomy?: boolean
}

export type ProductTag = { id: number; name: string; slug?: string }

export type GalleryImage = { id: number; url: string }

export type WfcpPrices = {
  purchase_price?: number | null
  lock_price?: boolean
  retail?: number | null
  credit?: number | null
  wholesale?: number | null
  installment?: number | null
  settings_currency?: string
}

export type Product = {
  id: number
  name: string
  slug?: string
  sku: string
  status: string
  type?: string
  price?: string
  regular?: string
  sale?: string
  description?: string
  short_description?: string
  manage_stock?: boolean
  stock: number | null
  stock_status?: string
  backorders?: string
  image_id?: number
  image_url?: string
  gallery_ids?: number[]
  gallery_urls?: GalleryImage[]
  category_ids?: number[]
  brand_ids?: number[]
  tag_ids?: number[]
  tags?: ProductTag[]
  product_attributes?: ProductAttribute[]
  weight?: string
  length?: string
  width?: string
  height?: string
  catalog_visibility?: string
  featured?: boolean
  upsell_ids?: number[]
  cross_sell_ids?: number[]
  variation_ids?: number[]
  wfcp?: { purchase_price?: string | number; lock_price?: boolean; wholesale_rule?: string }
  wfcp_prices?: WfcpPrices
}

export type ProductVariation = {
  id: number
  sku: string
  regular_price: string
  sale_price: string
  price: string
  manage_stock: boolean
  stock_quantity: number | null
  stock_status: string
  image_id: number
  attributes: Record<string, string>
  status: string
}

export type AttributeRow = {
  name: string
  options: string
  variation: boolean
  visible: boolean
  attribute_id?: number
  taxonomy?: boolean
}

export type ProductLookup = {
  categories: { id: number; name: string; slug?: string }[]
  brands: { id: number; name: string; slug?: string }[]
  tags: { id: number; name: string; slug?: string }[]
}

