import { apiFetch } from '@/lib/api'

export type AiOverview = {
  jobs_pending: number
  jobs_failed: number
  jobs_done: number
  jobs_cost_toman?: number
  calendar_upcoming: number
  incomplete_products: number
  sample_incomplete: { id: number; name: string; missing: string[] }[]
  settings: AiSettings
  module_enabled: boolean
}

export type AiFieldSpec = {
  enabled: boolean
  length: number
  unit: 'words' | 'paragraphs' | 'count'
}

export type AiEntityKey = 'product' | 'product_cat' | 'product_brand' | 'blog' | 'blog_cat' | 'coffee' | 'page'

export type AiSettings = {
  default_provider: string
  fallback_order: string[]
  grok_model: string
  gemini_model: string
  openai_model: string
  gapgpt_model: string
  site_name: string
  site_topic: string
  site_description?: string
  palette_mode?: 'site' | 'suggest'
  page_provider?: string
  page_model?: string
  page_max_tokens?: number
  tone: string
  language: string
  seo_sep: string
  temperature: number
  max_tokens: number
  daily_blog_quota: number
  daily_product_quota: number
  auto_publish: boolean
  publish_status: string
  min_blog_words: number
  min_product_words: number
  min_term_words: number
  min_page_words?: number
  require_site_name: boolean
  web_research?: boolean
  review_emojis?: boolean
  internal_links_min?: number
  internal_links_max?: number
  tones?: Record<string, boolean>
  usd_to_toman?: number
  gapgpt_rates?: Record<string, { in_per_1m: number; out_per_1m: number }>
  gapgpt_rates_updated_at?: string
  enabled: boolean
  do_product: boolean
  do_product_cat: boolean
  do_product_brand: boolean
  do_blog: boolean
  do_blog_cat: boolean
  do_page?: boolean
  do_coffee: boolean
  queue_paused?: boolean
  prompt_system: string
  prompt_product: string
  prompt_product_cat: string
  prompt_product_brand: string
  prompt_blog: string
  prompt_blog_cat: string
  prompt_coffee: string
  prompt_catalog?: string
  prompt_title?: string
  prompt_page?: string
  prompt_page_system?: string
  catalog_assign_categories?: boolean
  catalog_assign_brands?: boolean
  catalog_create_terms?: boolean
  catalog_only_missing?: boolean
  title_enabled?: boolean
  title_pattern?: string
  title_brand_script?: 'fa' | 'en'
  title_include_feature?: boolean
  coffee_module?: boolean
  fields: Record<AiEntityKey, Record<string, AiFieldSpec>>
  prompt_defaults?: Record<string, string>
  has_grok_key?: boolean
  has_gemini_key?: boolean
  has_openai_key?: boolean
  has_gapgpt_key?: boolean
  grok_api_key_masked?: string
  gemini_api_key_masked?: string
  openai_api_key_masked?: string
  gapgpt_api_key_masked?: string
  grok_api_key?: string
  gemini_api_key?: string
  openai_api_key?: string
  gapgpt_api_key?: string
}

export type AiJob = {
  id: number
  job_type: string
  target_type: string
  target_id: number
  target_title?: string
  status: string
  provider: string
  model?: string
  error_message: string
  result_summary: string
  attempts: number
  tokens_in: number
  tokens_out: number
  cost_toman?: number
  cost_estimated?: boolean
  created_at: string
  started_at: string | null
  updated_at: string
  finished_at: string | null
}

export type CalendarSlot = {
  id: number
  slot_date: string
  content_type: string
  topic: string
  focus_keyword: string
  secondary_keywords: string
  category_id: number
  product_id: number
  status: string
  notes: string
}

export function fetchAiOverview() {
  return apiFetch<AiOverview>('ai-content/overview')
}

export function fetchAiSettings() {
  return apiFetch<AiSettings>('ai-content/settings')
}

export function saveAiSettings(body: Partial<AiSettings>) {
  return apiFetch<AiSettings>('ai-content/settings', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })
}

export type GapGptModel = {
  id: string
  owned_by?: string
  in_per_1m?: number
  out_per_1m?: number
}

export type AiCostRange = { lo: number; mid: number; hi: number }

export type AiCostEntity = {
  source: string
  tokens_in: AiCostRange
  tokens_out: AiCostRange
  cost_toman: AiCostRange
}

export type AiCostModel = {
  id: string
  owned_by?: string
  in_per_1m: number
  out_per_1m: number
  source: string
  selected: boolean
  costs: Record<string, number>
}

export type AiCostEstimate = {
  currency: string
  usd_to_toman: number
  pricing_url: string
  rates_updated_at: string
  current: {
    provider: string
    model: string
    in_per_1m: number
    out_per_1m: number
    source: string
  }
  entities: Record<string, AiCostEntity>
  models: AiCostModel[]
  batch: Record<string, { count: number; cost_toman: number }>
}

export function fetchAiCostEstimate(draft?: Partial<AiSettings>) {
  const hasDraft = !!draft && Object.keys(draft).length > 0
  return apiFetch<AiCostEstimate>('ai-content/cost-estimate', {
    method: hasDraft ? 'POST' : 'GET',
    headers: hasDraft ? { 'Content-Type': 'application/json' } : undefined,
    body: hasDraft ? JSON.stringify(draft) : undefined,
  })
}

export function fetchGapGptModels(refresh = false) {
  const q = new URLSearchParams()
  if (refresh) q.set('refresh', '1')
  const suffix = q.toString() ? `?${q.toString()}` : ''
  return apiFetch<{ models: GapGptModel[] }>(`ai-content/gapgpt/models${suffix}`)
}

export function fetchAiJobs(params?: { status?: string; limit?: number }) {
  const q = new URLSearchParams()
  if (params?.status) q.set('status', params.status)
  if (params?.limit) q.set('limit', String(params.limit))
  const qs = q.toString()
  return apiFetch<{ items: AiJob[]; total: number }>(`ai-content/jobs${qs ? `?${qs}` : ''}`)
}

export function retryAiJob(id: number) {
  return apiFetch<{ ok: boolean }>(`ai-content/jobs/${id}/retry`, { method: 'POST' })
}

export function fetchAiJob(id: number) {
  return apiFetch<AiJob>(`ai-content/jobs/${id}`)
}

export function runDueJobs(limit = 1) {
  return apiFetch<{ processed: number[]; count: number; paused?: boolean }>(
    'ai-content/jobs/run-due',
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ limit }),
    },
  )
}

export function runAiJob(id: number) {
  return apiFetch<{ ok: boolean; job: AiJob; accepted?: boolean }>(
    `ai-content/jobs/${id}/run`,
    { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' },
  )
}

export function cancelAiJob(id: number) {
  return apiFetch<{ ok: boolean; job: AiJob }>(`ai-content/jobs/${id}/cancel`, { method: 'POST' })
}

export function cancelPendingAiJobs() {
  return apiFetch<{ ok: boolean; count: number }>('ai-content/jobs/cancel-pending', { method: 'POST' })
}

export function fetchAiQueue() {
  return apiFetch<{ paused: boolean }>('ai-content/queue')
}

export function setAiQueuePaused(paused: boolean) {
  return apiFetch<{ ok: boolean; paused: boolean }>('ai-content/queue', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ paused }),
  })
}

export function generateAi(body: Record<string, unknown>) {
  return apiFetch<{ ok: boolean; job_id: number; queued?: boolean; job?: AiJob }>('ai-content/generate', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })
}

export type AiDesignMemory = {
  palette: Record<string, string>
  kit_color_ids: Record<string, string>
  radius: Record<string, number>
  spacing: Record<string, number>
  shadow: string
  button_style: string
  approved_archetypes: string[]
  source: string
  locked: boolean
  updated_at: string
}

export function fetchAiDesignMemory() {
  return apiFetch<AiDesignMemory>('ai-content/design-memory')
}

export function saveAiDesignMemory(body: Partial<AiDesignMemory> & { force?: boolean }) {
  return apiFetch<AiDesignMemory>('ai-content/design-memory', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })
}

export function extractAiDesignMemory() {
  return apiFetch<AiDesignMemory>('ai-content/design-memory/extract', { method: 'POST' })
}

export function resetAiDesignMemory() {
  return apiFetch<AiDesignMemory>('ai-content/design-memory/reset', { method: 'POST' })
}

export type AiPageRow = {
  id: number
  title: string
  status: string
  modified: string
  url: string
  page_prompt: string
  has_elementor: boolean
  elementor_url: string
}

export function fetchAiPages(page = 1, search = '') {
  const q = new URLSearchParams({ page: String(page), per_page: '50' })
  if (search) q.set('search', search)
  return apiFetch<{ items: AiPageRow[]; page: number; found: number; elementor: boolean }>(
    `ai-content/pages?${q.toString()}`,
  )
}

export function fetchIncompleteProducts(limit = 50) {
  return apiFetch<{ items: { id: number; name: string; missing: string[] }[]; total: number }>(
    `ai-content/products/incomplete?limit=${limit}`,
  )
}

export function fillProductsBatch(ids?: number[]) {
  return apiFetch<{ ok: boolean; job_ids: number[]; count: number }>('ai-content/products/fill-batch', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(ids ? { ids } : {}),
  })
}

export function fetchCalendar(from?: string, to?: string) {
  const q = new URLSearchParams()
  if (from) q.set('from', from)
  if (to) q.set('to', to)
  const qs = q.toString()
  return apiFetch<{ items: CalendarSlot[] }>(`ai-content/calendar${qs ? `?${qs}` : ''}`)
}

export function createCalendarSlot(body: Partial<CalendarSlot>) {
  return apiFetch<CalendarSlot>('ai-content/calendar', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })
}

export function bulkCalendar(body: {
  topics: string
  start_date: string
  content_type: string
  focus_keyword?: string
  category_id?: number
}) {
  return apiFetch<{ created: number; items: CalendarSlot[] }>('ai-content/calendar/bulk', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })
}

export function deleteCalendarSlot(id: number) {
  return apiFetch<{ ok: boolean }>(`ai-content/calendar/${id}`, { method: 'DELETE' })
}

export function runCalendarDue() {
  return apiFetch<{ ok: boolean }>('ai-content/calendar/run-due', { method: 'POST' })
}

export function fetchAttrTemplates() {
  return apiFetch<{
    items: {
      id: number
      product_cat_id: number
      category_name: string
      attribute_ids: number[]
      labels: { attribute_id?: number; label: string; slug?: string; options?: string[] }[]
    }[]
  }>('ai-content/attribute-templates')
}

export function suggestAttrTemplate(catId: number) {
  return apiFetch<{ ok: boolean; job_id: number }>(`ai-content/attribute-templates/${catId}/suggest`, {
    method: 'POST',
  })
}

export function fetchAttrDraft(catId: number) {
  return apiFetch<{
    product_cat_id: number
    draft: { attributes: { label: string; slug: string; options: string[] }[] } | null
    template: { attribute_ids?: number[]; labels?: CatAttrLabel[] } | null
    discovered: CatAttrLabel[]
  }>(`ai-content/attribute-templates/${catId}/draft`)
}

export type CatAttrLabel = {
  attribute_id?: number
  label: string
  slug?: string
  options?: string[]
}

export function confirmAttrTemplate(product_cat_id: number, draft: unknown) {
  return apiFetch('ai-content/attribute-templates', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ product_cat_id, draft }),
  })
}

export function saveAttrMapping(product_cat_id: number, attribute_ids: number[]) {
  return apiFetch('ai-content/attribute-templates', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ product_cat_id, attribute_ids }),
  })
}

export function deleteAttrTemplate(catId: number) {
  return apiFetch<{ ok: boolean }>(`ai-content/attribute-templates/${catId}`, { method: 'DELETE' })
}

export function suggestCategories(kind: 'blog' | 'product') {
  return apiFetch<{ ok: boolean; job_id: number }>('ai-content/suggest-categories', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ kind }),
  })
}

export function getCategorySuggestions(kind: 'blog' | 'product') {
  return apiFetch<{ kind: string; suggestions: { categories: unknown[] } | null }>(
    `ai-content/suggest-categories/${kind}`,
  )
}

export function applyCategorySuggestions(kind: 'blog' | 'product') {
  return apiFetch<{ ok: boolean; count: number }>(`ai-content/suggest-categories/${kind}/apply`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({}),
  })
}

export function fillTermsBatch(taxonomy: 'product_cat' | 'product_brand' | 'category', ids?: number[]) {
  return apiFetch<{ ok: boolean; count: number }>('ai-content/terms/fill-batch', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ taxonomy, ids }),
  })
}

export type AiProposal = {
  id: number
  kind: 'title' | 'catalog'
  product_id: number
  product_name: string
  current: Record<string, unknown>
  proposed: Record<string, unknown>
  status: string
  created_at: string
  updated_at: string
}

export function fetchAiProposals(kind: 'title' | 'catalog', status = 'pending', limit = 100) {
  return apiFetch<{ items: AiProposal[]; total: number }>(
    `ai-content/proposals/${kind}?status=${encodeURIComponent(status)}&limit=${limit}`,
  )
}

export function enqueueAiProposals(kind: 'title' | 'catalog', ids?: number[]) {
  return apiFetch<{ ok: boolean; job_ids: number[]; count: number; chunks: number }>(
    `ai-content/proposals/${kind}/enqueue`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(ids ? { ids } : {}),
    },
  )
}

export function applyAiProposal(id: number, body?: { name?: string; proposed?: Record<string, unknown> }) {
  return apiFetch<AiProposal>(`ai-content/proposals/${id}/apply`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body ?? {}),
  })
}

export function skipAiProposal(id: number) {
  return apiFetch<AiProposal>(`ai-content/proposals/${id}/skip`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: '{}',
  })
}

export function requeueAiProposal(kind: 'title' | 'catalog', productId: number) {
  return apiFetch<{ ok: boolean; count: number }>(`ai-content/proposals/${kind}/product/${productId}/requeue`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: '{}',
  })
}
