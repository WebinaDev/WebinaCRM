import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMatch, useNavigate, useParams } from 'react-router-dom'
import { toast } from 'sonner'
import { toastApiError } from '@/lib/apiError'

import { apiFetch } from '@/lib/api'
import type { AttributeRow, GalleryImage, Product, ProductLookup } from '@/types/product'

function emptyAttributeRow(): AttributeRow {
  return { name: '', options: '', variation: false, visible: true }
}

function slugifyName(name: string) {
  return name
    .trim()
    .toLowerCase()
    .replace(/\s+/g, '-')
    .replace(/[^a-z0-9\u0600-\u06FF-]/g, '')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
}

export function useProductEditorForm() {
  const { t } = useTranslation()
  const nav = useNavigate()
  const qc = useQueryClient()
  const isNew = Boolean(useMatch('/shop/products/new'))
  const { productId } = useParams<{ productId: string }>()
  const id = isNew ? undefined : productId ? parseInt(productId, 10) : undefined

  const [name, setName] = useState('')
  const [slug, setSlug] = useState('')
  const [slugTouched, setSlugTouched] = useState(false)
  const [productType, setProductType] = useState<'simple' | 'variable'>('simple')
  const [sku, setSku] = useState('')
  const [status, setStatus] = useState('draft')
  const [catalogVisibility, setCatalogVisibility] = useState('visible')
  const [featured, setFeatured] = useState(false)
  const [regular, setRegular] = useState('')
  const [sale, setSale] = useState('')
  const [stock, setStock] = useState('')
  const [manageStock, setManageStock] = useState(true)
  const [backorders, setBackorders] = useState('no')
  const [description, setDescription] = useState('')
  const [shortDescription, setShortDescription] = useState('')
  const [purchase, setPurchase] = useState('')
  const [lockPrice, setLockPrice] = useState(false)
  const [wfcpPrices, setWfcpPrices] = useState<Product['wfcp_prices']>()
  const [imageId, setImageId] = useState(0)
  const [imageUrl, setImageUrl] = useState('')
  const [gallery, setGallery] = useState<GalleryImage[]>([])
  const [categoryIds, setCategoryIds] = useState<number[]>([])
  const [brandIds, setBrandIds] = useState<number[]>([])
  const [tagIds, setTagIds] = useState<number[]>([])
  const [attributes, setAttributes] = useState<AttributeRow[]>([emptyAttributeRow()])
  const [weight, setWeight] = useState('')
  const [length, setLength] = useState('')
  const [width, setWidth] = useState('')
  const [height, setHeight] = useState('')
  const [upsellIds, setUpsellIds] = useState<number[]>([])
  const [crossSellIds, setCrossSellIds] = useState<number[]>([])
  const [variationCount, setVariationCount] = useState(0)

  const productQ = useQuery({
    queryKey: ['product', id],
    queryFn: () => apiFetch<Product>(`shop/products/${id}`),
    enabled: Boolean(id),
  })

  const lookupQ = useQuery({
    queryKey: ['product-lookup'],
    queryFn: () => apiFetch<ProductLookup>('shop/products/lookup'),
  })

  const hydrate = useCallback((p: Product) => {
    setName(p.name)
    setSlug(p.slug ?? '')
    setSlugTouched(Boolean(p.slug))
    setProductType(p.type === 'variable' ? 'variable' : 'simple')
    setSku(p.sku ?? '')
    setStatus(p.status || 'draft')
    setCatalogVisibility(p.catalog_visibility ?? 'visible')
    setFeatured(Boolean(p.featured))
    setRegular(p.regular ?? '')
    setSale(p.sale ?? '')
    setStock(p.stock != null ? String(p.stock) : '')
    setManageStock(Boolean(p.manage_stock))
    setBackorders(p.backorders ?? 'no')
    setDescription(p.description ?? '')
    setShortDescription(p.short_description ?? '')
    setPurchase(String(p.wfcp?.purchase_price ?? p.wfcp_prices?.purchase_price ?? ''))
    setLockPrice(Boolean(p.wfcp?.lock_price ?? p.wfcp_prices?.lock_price))
    setWfcpPrices(p.wfcp_prices)
    setImageId(p.image_id ?? 0)
    setImageUrl(p.image_url ?? '')
    const urls = p.gallery_urls ?? []
    const ids = p.gallery_ids ?? []
    if (urls.length) {
      setGallery(urls)
    } else {
      setGallery(ids.map((gid) => ({ id: gid, url: '' })))
    }
    setCategoryIds([...(p.category_ids ?? [])])
    setBrandIds([...(p.brand_ids ?? [])])
    setTagIds([...(p.tag_ids ?? [])])
    const pa = p.product_attributes ?? []
    setAttributes(
      pa.length
        ? pa.map((a) => ({
            name: a.name,
            options: (a.options ?? []).map(String).join(', '),
            variation: Boolean(a.variation),
            visible: a.visible !== false,
            attribute_id: a.attribute_id,
            taxonomy: a.taxonomy,
          }))
        : [emptyAttributeRow()],
    )
    setWeight(p.weight ?? '')
    setLength(p.length ?? '')
    setWidth(p.width ?? '')
    setHeight(p.height ?? '')
    setUpsellIds([...(p.upsell_ids ?? [])])
    setCrossSellIds([...(p.cross_sell_ids ?? [])])
    setVariationCount((p.variation_ids ?? []).length)
  }, [])

  useEffect(() => {
    if (productQ.data) hydrate(productQ.data)
  }, [productQ.data, hydrate])

  useEffect(() => {
    if (!slugTouched && name.trim() && isNew) {
      setSlug(slugifyName(name))
    }
  }, [name, slugTouched, isNew])

  const productAttributesPayload = attributes
    .filter((row) => row.name.trim() && row.options.trim())
    .map((row) => ({
      name: row.name.trim(),
      options: row.options
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean),
      variation: row.variation,
      visible: row.visible,
      ...(row.attribute_id ? { attribute_id: row.attribute_id } : {}),
    }))

  function buildPayload() {
    const body: Record<string, unknown> = {
      name: name || t('products.untitled'),
      slug: slug.trim() || undefined,
      sku,
      status,
      catalog_visibility: catalogVisibility,
      featured,
      description,
      short_description: shortDescription,
      image_id: imageId,
      gallery_ids: gallery.map((g) => g.id),
      category_ids: categoryIds,
      brand_ids: brandIds,
      tag_ids: tagIds,
      product_attributes: productAttributesPayload,
      weight: weight.trim(),
      length: length.trim(),
      width: width.trim(),
      height: height.trim(),
      upsell_ids: upsellIds,
      cross_sell_ids: crossSellIds,
      backorders,
      wfcp: {
        purchase_price: purchase,
        lock_price: lockPrice,
      },
    }

    if (productType === 'simple') {
      body.regular_price = regular
      body.sale_price = sale
      body.stock_quantity = stock === '' ? null : parseInt(stock, 10)
      body.manage_stock = manageStock
    }

    if (isNew) {
      body.type = productType
    }

    return body
  }

  const save = useMutation({
    mutationFn: async () => {
      const payload = buildPayload()
      if (id) {
        return apiFetch<Product>(`shop/products/${id}`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        })
      }
      return apiFetch<Product>('shop/products', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
    },
    onSuccess: (p) => {
      void qc.invalidateQueries({ queryKey: ['products'] })
      toast.success(t('common.saved'))
      if (!id && p.id) {
        void qc.setQueryData(['product', p.id], p)
        nav(`/shop/products/${p.id}`, { replace: true })
      } else if (id) {
        void qc.invalidateQueries({ queryKey: ['product', id] })
        hydrate(p)
      }
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const patchWfcp = useMutation({
    mutationFn: async () => {
      if (!id) return null
      await apiFetch(`shop/products/${id}/wfcp`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          wfcp: { purchase_price: purchase, lock_price: lockPrice },
        }),
      })
      return apiFetch<Product>(`shop/products/${id}`)
    },
    onSuccess: (p) => {
      if (p) {
        setWfcpPrices(p.wfcp_prices)
        setPurchase(String(p.wfcp?.purchase_price ?? p.wfcp_prices?.purchase_price ?? ''))
        setLockPrice(Boolean(p.wfcp?.lock_price ?? p.wfcp_prices?.lock_price))
      }
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const typeChangeDisabled = Boolean(id && variationCount > 0)

  return {
    id,
    isNew,
    loading: Boolean(id) && productQ.isLoading,
    productQ,
    lookupQ,
    name,
    setName,
    slug,
    setSlug,
    setSlugTouched,
    productType,
    setProductType,
    typeChangeDisabled,
    sku,
    setSku,
    status,
    setStatus,
    catalogVisibility,
    setCatalogVisibility,
    featured,
    setFeatured,
    regular,
    setRegular,
    sale,
    setSale,
    stock,
    setStock,
    manageStock,
    setManageStock,
    backorders,
    setBackorders,
    description,
    setDescription,
    shortDescription,
    setShortDescription,
    purchase,
    setPurchase,
    lockPrice,
    setLockPrice,
    wfcpPrices,
    imageId,
    imageUrl,
    setCover: (item: { id: number; url: string }) => {
      setImageId(item.id)
      setImageUrl(item.url)
    },
    clearCover: () => {
      setImageId(0)
      setImageUrl('')
    },
    gallery,
    setGallery,
    categoryIds,
    setCategoryIds,
    brandIds,
    setBrandIds,
    tagIds,
    setTagIds,
    attributes,
    setAttributes,
    weight,
    setWeight,
    length,
    setLength,
    width,
    setWidth,
    height,
    setHeight,
    upsellIds,
    setUpsellIds,
    crossSellIds,
    setCrossSellIds,
    save,
    patchWfcp,
  }
}
