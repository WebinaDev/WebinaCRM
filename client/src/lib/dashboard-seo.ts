/** Marker so we only remove/update nodes we own. */
const SEO_MARK = 'data-webino-dashboard-seo'
const JSONLD_ID = 'webino-dashboard-jsonld'

function setOrCreateMeta(attr: 'name' | 'property', key: string, content: string) {
  const sel = `meta[${attr}="${CSS.escape(key)}"]`
  let el = document.head.querySelector(sel) as HTMLMetaElement | null
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(attr, key)
    el.setAttribute(SEO_MARK, '1')
    document.head.appendChild(el)
  }
  el.setAttribute('content', content)
}

function setCanonical(href: string) {
  let el = document.head.querySelector(`link[rel="canonical"][${SEO_MARK}]`) as HTMLLinkElement | null
  if (!el) {
    el = document.createElement('link')
    el.setAttribute('rel', 'canonical')
    el.setAttribute(SEO_MARK, '1')
    document.head.appendChild(el)
  }
  el.setAttribute('href', href)
}

function setJsonLd(payload: Record<string, unknown>) {
  let el = document.getElementById(JSONLD_ID) as HTMLScriptElement | null
  if (!el) {
    el = document.createElement('script')
    el.type = 'application/ld+json'
    el.id = JSONLD_ID
    el.setAttribute(SEO_MARK, '1')
    document.head.appendChild(el)
  }
  el.textContent = JSON.stringify(payload)
}

/**
 * Updates document head for SPA navigation: title, description, canonical, Open Graph, optional image, WebSite JSON-LD.
 * Shell may ship a static meta description; we replace the first generic meta description when present.
 */
export function applyDashboardDocumentSeo(opts: {
  title: string
  description: string
  canonicalUrl: string
  ogImageUrl?: string
  /** When set, injects a minimal WebSite schema.org graph. */
  structuredSite?: { name: string; url: string }
}) {
  document.title = opts.title

  const firstDesc = document.head.querySelector('meta[name="description"]') as HTMLMetaElement | null
  if (firstDesc && !firstDesc.hasAttribute(SEO_MARK)) {
    firstDesc.setAttribute('content', opts.description)
  } else {
    setOrCreateMeta('name', 'description', opts.description)
  }

  setCanonical(opts.canonicalUrl)

  setOrCreateMeta('property', 'og:title', opts.title)
  setOrCreateMeta('property', 'og:description', opts.description)
  setOrCreateMeta('property', 'og:url', opts.canonicalUrl)
  setOrCreateMeta('property', 'og:type', 'website')

  if (opts.ogImageUrl) {
    setOrCreateMeta('property', 'og:image', opts.ogImageUrl)
    setOrCreateMeta('name', 'twitter:card', 'summary_large_image')
    setOrCreateMeta('name', 'twitter:image', opts.ogImageUrl)
  }

  if (opts.structuredSite?.name && opts.structuredSite.url) {
    setJsonLd({
      '@context': 'https://schema.org',
      '@type': 'WebSite',
      name: opts.structuredSite.name,
      url: opts.structuredSite.url,
    })
  }
}
