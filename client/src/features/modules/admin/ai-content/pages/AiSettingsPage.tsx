import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { useQueryErrorToast } from '@/hooks/useQueryErrorToast'
import { toastApiError } from '@/lib/apiError'
import {
  type AiEntityKey,
  type AiFieldSpec,
  type AiSettings,
  extractAiDesignMemory,
  fetchAiCostEstimate,
  fetchAiDesignMemory,
  fetchAiSettings,
  fetchGapGptModels,
  resetAiDesignMemory,
  saveAiDesignMemory,
  saveAiSettings,
} from '../lib/ai-content-api'
import { AiCostCard } from '../components/AiCostCard'
import { AiContentLayout } from '../components/AiContentLayout'

const ENTITIES: {
  id: AiEntityKey
  doKey: keyof AiSettings
  promptKey: keyof AiSettings
  titleKey: string
  navKey: string
  fields: string[]
}[] = [
  {
    id: 'product',
    doKey: 'do_product',
    promptKey: 'prompt_product',
    titleKey: 'aiContent.settingsProductIshop',
    navKey: 'aiContent.settingsProduct',
    fields: [
      'name',
      'slug',
      'english_name',
      'short_description',
      'description',
      'ai_review_summary',
      'faqs',
      'custom_labels',
      'attributes',
      'tags',
      'seo',
    ],
  },
  {
    id: 'coffee',
    doKey: 'do_coffee',
    promptKey: 'prompt_coffee',
    titleKey: 'aiContent.settingsCoffee',
    navKey: 'aiContent.settingsCoffee',
    fields: [
      'blend_arabica',
      'blend_robusta',
      'acidity',
      'caffeine_mg',
      'bitterness',
      'sweetness',
      'body',
      'origin_ids',
      'visible',
    ],
  },
  {
    id: 'product_cat',
    doKey: 'do_product_cat',
    promptKey: 'prompt_product_cat',
    titleKey: 'aiContent.settingsProductCat',
    navKey: 'aiContent.settingsProductCat',
    fields: ['description', 'seo'],
  },
  {
    id: 'product_brand',
    doKey: 'do_product_brand',
    promptKey: 'prompt_product_brand',
    titleKey: 'aiContent.settingsBrand',
    navKey: 'aiContent.settingsBrand',
    fields: ['description', 'seo'],
  },
  {
    id: 'blog',
    doKey: 'do_blog',
    promptKey: 'prompt_blog',
    titleKey: 'aiContent.settingsBlog',
    navKey: 'aiContent.settingsBlog',
    fields: ['title', 'slug', 'excerpt', 'content', 'tags', 'seo'],
  },
  {
    id: 'blog_cat',
    doKey: 'do_blog_cat',
    promptKey: 'prompt_blog_cat',
    titleKey: 'aiContent.settingsBlogCat',
    navKey: 'aiContent.settingsBlogCat',
    fields: ['description', 'seo'],
  },
  {
    id: 'page',
    doKey: 'do_page',
    promptKey: 'prompt_page',
    titleKey: 'aiContent.settingsPageEntity',
    navKey: 'aiContent.settingsPageEntity',
    fields: ['title', 'slug', 'excerpt', 'content', 'seo'],
  },
]

const TONE_SLUGS = [
  'professional',
  'friendly',
  'expert',
  'educational',
  'sales',
  'storytelling',
  'luxury',
  'casual',
  'enthusiastic',
] as const

const LENGTH_FIELDS: Record<string, Array<AiFieldSpec['unit']>> = {
  short_description: ['paragraphs', 'words'],
  description: ['words', 'paragraphs'],
  ai_review_summary: ['words', 'paragraphs'],
  faqs: ['count'],
  custom_labels: ['count'],
  excerpt: ['paragraphs', 'words'],
  content: ['words', 'paragraphs'],
}

export default function AiSettingsPage() {
  const { t } = useTranslation()
  const qc = useQueryClient()
  const [draft, setDraft] = useState<Partial<AiSettings> | null>(null)

  const q = useQuery({
    queryKey: ['ai-content', 'settings'],
    queryFn: fetchAiSettings,
  })
  useQueryErrorToast(q)

  const hasGapKey = !!q.data?.has_gapgpt_key
  const modelsQ = useQuery({
    queryKey: ['ai-content', 'gapgpt-models'],
    queryFn: () => fetchGapGptModels(false),
    enabled: hasGapKey,
  })
  useQueryErrorToast(modelsQ)

  useEffect(() => {
    if (q.data) {
      setDraft({
        ...q.data,
        grok_api_key: '',
        gemini_api_key: '',
        openai_api_key: '',
        gapgpt_api_key: '',
      })
    }
  }, [q.data])

  const save = useMutation({
    mutationFn: () => saveAiSettings(draft ?? {}),
    onSuccess: async (res) => {
      toast.success(t('common.saved'))
      setDraft({
        ...res,
        grok_api_key: '',
        gemini_api_key: '',
        openai_api_key: '',
        gapgpt_api_key: '',
      })
      await qc.invalidateQueries({ queryKey: ['ai-content', 'settings'] })
      await qc.invalidateQueries({ queryKey: ['ai-content', 'gapgpt-models'] })
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const refreshModels = useMutation({
    mutationFn: () => fetchGapGptModels(true),
    onSuccess: (data) => {
      qc.setQueryData(['ai-content', 'gapgpt-models'], data)
      toast.success(t('aiContent.gapgptRefreshModels'))
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const modelIds = useMemo(() => {
    const ids = (modelsQ.data?.models ?? []).map((m) => m.id).filter(Boolean)
    const selected = (draft?.gapgpt_model || '').trim()
    if (selected && !ids.includes(selected)) ids.unshift(selected)
    return ids
  }, [modelsQ.data, draft?.gapgpt_model])

  const costQ = useQuery({
    queryKey: [
      'ai-content',
      'cost-estimate',
      draft?.gapgpt_model,
      draft?.default_provider,
      draft?.usd_to_toman,
      draft?.gapgpt_rates,
      draft?.fields,
      draft?.do_coffee,
      draft?.language,
    ],
    queryFn: () =>
      fetchAiCostEstimate({
        gapgpt_model: draft?.gapgpt_model,
        default_provider: draft?.default_provider,
        usd_to_toman: draft?.usd_to_toman,
        gapgpt_rates: draft?.gapgpt_rates,
        fields: draft?.fields,
        do_coffee: draft?.do_coffee,
        language: draft?.language,
        prompt_system: draft?.prompt_system,
      }),
    enabled: !!draft,
  })
  useQueryErrorToast(costQ)

  const memQ = useQuery({
    queryKey: ['ai-content', 'design-memory'],
    queryFn: fetchAiDesignMemory,
  })
  useQueryErrorToast(memQ)

  const extractMem = useMutation({
    mutationFn: extractAiDesignMemory,
    onSuccess: (res) => {
      qc.setQueryData(['ai-content', 'design-memory'], res)
      toast.success(t('aiContent.designExtracted'))
    },
    onError: (e: Error) => toastApiError(t, e),
  })
  const resetMem = useMutation({
    mutationFn: resetAiDesignMemory,
    onSuccess: (res) => {
      qc.setQueryData(['ai-content', 'design-memory'], res)
      toast.success(t('aiContent.designReset'))
    },
    onError: (e: Error) => toastApiError(t, e),
  })
  const unlockMem = useMutation({
    mutationFn: () => saveAiDesignMemory({ locked: false, force: true }),
    onSuccess: (res) => {
      qc.setQueryData(['ai-content', 'design-memory'], res)
      toast.success(t('common.saved'))
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const entities = useMemo(
    () => ENTITIES.filter((ent) => ent.id !== 'coffee' || !!draft?.coffee_module),
    [draft?.coffee_module],
  )

  const nav = useMemo(
    () => [
      { id: 'providers', label: t('aiContent.settingsProviders') },
      { id: 'cost', label: t('aiContent.settingsCost') },
      { id: 'site', label: t('aiContent.settingsProfile') },
      { id: 'design', label: t('aiContent.settingsDesign') },
      { id: 'tones', label: t('aiContent.settingsTones') },
      { id: 'system', label: t('aiContent.settingsSystemPrompt') },
      ...entities.map((ent) => ({ id: ent.id, label: t(ent.navKey) })),
      { id: 'automation', label: t('aiContent.settingsAutomation') },
    ],
    [entities, t],
  )

  if (!draft) {
    return <div className="text-sm text-muted-foreground">{t('common.loading')}</div>
  }

  const set = <K extends keyof AiSettings>(key: K, value: AiSettings[K]) => {
    setDraft((d) => ({ ...(d ?? {}), [key]: value }))
  }

  const patchField = (entity: AiEntityKey, field: string, patch: Partial<AiFieldSpec>) => {
    setDraft((d) => {
      const fields = { ...(d?.fields ?? {}) } as AiSettings['fields']
      const current = fields[entity]?.[field] ?? { enabled: true, length: 0, unit: 'words' as const }
      fields[entity] = { ...(fields[entity] ?? {}), [field]: { ...current, ...patch } }
      return { ...(d ?? {}), fields }
    })
  }

  return (
    <AiContentLayout>
    <div className="relative space-y-4 pb-20">
      <div>
        <h1 className="text-xl font-semibold tracking-tight">{t('aiContent.settingsPageTitle')}</h1>
        <p className="text-muted-foreground mt-1 text-sm">{t('aiContent.settingsPageLead')}</p>
      </div>

      <nav className="bg-background/95 sticky top-0 z-20 -mx-1 flex flex-wrap gap-1 border-b py-2 backdrop-blur">
        {nav.map((item) => (
          <a
            key={item.id}
            href={`#ai-sec-${item.id}`}
            className="hover:bg-muted rounded-md px-2.5 py-1.5 text-xs font-medium"
          >
            {item.label}
          </a>
        ))}
      </nav>

      <Card id="ai-sec-providers">
        <CardHeader>
          <CardTitle>{t('aiContent.settingsProviders')}</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          <div className="space-y-1">
            <Label>{t('aiContent.defaultProvider')}</Label>
            <select
              className="flex h-9 w-full rounded-md border bg-background px-3 text-sm"
              value={draft.default_provider ?? 'grok'}
              onChange={(e) => set('default_provider', e.target.value)}
            >
              <option value="grok">Grok</option>
              <option value="gemini">Gemini</option>
              <option value="openai">ChatGPT</option>
              <option value="gapgpt">{t('aiContent.gapgpt')}</option>
            </select>
          </div>
          <div className="space-y-1 md:col-span-2">
            <Label>Grok API key {draft.has_grok_key ? `(${draft.grok_api_key_masked})` : ''}</Label>
            <Input
              type="password"
              value={draft.grok_api_key ?? ''}
              onChange={(e) => set('grok_api_key', e.target.value)}
              placeholder={t('aiContent.leaveBlankKeep')}
            />
          </div>
          <div className="space-y-1 md:col-span-2">
            <Label>Gemini API key {draft.has_gemini_key ? `(${draft.gemini_api_key_masked})` : ''}</Label>
            <Input
              type="password"
              value={draft.gemini_api_key ?? ''}
              onChange={(e) => set('gemini_api_key', e.target.value)}
              placeholder={t('aiContent.leaveBlankKeep')}
            />
          </div>
          <div className="space-y-1 md:col-span-2">
            <Label>OpenAI API key {draft.has_openai_key ? `(${draft.openai_api_key_masked})` : ''}</Label>
            <Input
              type="password"
              value={draft.openai_api_key ?? ''}
              onChange={(e) => set('openai_api_key', e.target.value)}
              placeholder={t('aiContent.leaveBlankKeep')}
            />
          </div>
          <div className="space-y-1 md:col-span-2">
            <Label>
              {t('aiContent.gapgptKey')} {draft.has_gapgpt_key ? `(${draft.gapgpt_api_key_masked})` : ''}
            </Label>
            <Input
              type="password"
              value={draft.gapgpt_api_key ?? ''}
              onChange={(e) => set('gapgpt_api_key', e.target.value)}
              placeholder={t('aiContent.leaveBlankKeep')}
            />
          </div>
          <div className="space-y-1">
            <Label>Grok model</Label>
            <Input value={draft.grok_model ?? ''} onChange={(e) => set('grok_model', e.target.value)} />
          </div>
          <div className="space-y-1">
            <Label>Gemini model</Label>
            <Input value={draft.gemini_model ?? ''} onChange={(e) => set('gemini_model', e.target.value)} />
          </div>
          <div className="space-y-1">
            <Label>OpenAI model</Label>
            <Input value={draft.openai_model ?? ''} onChange={(e) => set('openai_model', e.target.value)} />
          </div>
          <div className="space-y-1 md:col-span-2">
            <Label>{t('aiContent.gapgptModel')}</Label>
            <div className="flex flex-col gap-2 sm:flex-row">
              <select
                className="flex h-9 w-full rounded-md border bg-background px-3 text-sm disabled:opacity-60"
                value={draft.gapgpt_model ?? ''}
                disabled={!hasGapKey || modelsQ.isLoading}
                onChange={(e) => set('gapgpt_model', e.target.value)}
              >
                {modelIds.length === 0 ? (
                  <option value={draft.gapgpt_model ?? ''}>{draft.gapgpt_model || '—'}</option>
                ) : (
                  modelIds.map((id) => (
                    <option key={id} value={id}>
                      {id}
                    </option>
                  ))
                )}
              </select>
              <Button
                type="button"
                variant="outline"
                disabled={!hasGapKey || refreshModels.isPending || modelsQ.isFetching}
                onClick={() => void refreshModels.mutateAsync()}
              >
                {t('aiContent.gapgptRefreshModels')}
              </Button>
            </div>
            {!hasGapKey ? (
              <p className="text-muted-foreground text-xs">{t('aiContent.gapgptSaveKeyFirst')}</p>
            ) : modelsQ.isFetched && modelIds.length === 0 ? (
              <p className="text-muted-foreground text-xs">{t('aiContent.gapgptNoModels')}</p>
            ) : null}
          </div>
        </CardContent>
      </Card>

      <Card id="ai-sec-cost">
        <CardHeader>
          <CardTitle>{t('aiContent.settingsCost')}</CardTitle>
        </CardHeader>
        <CardContent>
          <AiCostCard
            estimate={costQ.data}
            draft={draft}
            onPickModel={(id) => {
              set('gapgpt_model', id)
              set('default_provider', 'gapgpt')
            }}
            onUsdToToman={(n) => set('usd_to_toman', n)}
            onRate={(id, field, value) => {
              const current = draft.gapgpt_rates ?? {}
              const row = current[id] ?? {
                in_per_1m: costQ.data?.models.find((m) => m.id === id)?.in_per_1m ?? 0,
                out_per_1m: costQ.data?.models.find((m) => m.id === id)?.out_per_1m ?? 0,
              }
              set('gapgpt_rates', { ...current, [id]: { ...row, [field]: value } })
            }}
          />
        </CardContent>
      </Card>

      <Card id="ai-sec-site">
        <CardHeader>
          <CardTitle>{t('aiContent.settingsProfile')}</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          <div className="space-y-1 md:col-span-2">
            <Label>{t('aiContent.siteName')}</Label>
            <Input value={draft.site_name ?? ''} onChange={(e) => set('site_name', e.target.value)} />
          </div>
          <div className="space-y-1 md:col-span-2">
            <Label>{t('aiContent.siteTopic')}</Label>
            <Textarea rows={2} value={draft.site_topic ?? ''} onChange={(e) => set('site_topic', e.target.value)} />
          </div>
          <div className="space-y-1 md:col-span-2">
            <Label>{t('aiContent.siteDescription')}</Label>
            <Textarea
              rows={4}
              value={draft.site_description ?? ''}
              onChange={(e) => set('site_description', e.target.value)}
              placeholder={t('aiContent.siteDescriptionHint')}
            />
          </div>
          <div className="space-y-1">
            <Label>{t('aiContent.language')}</Label>
            <select
              className="flex h-9 w-full rounded-md border bg-background px-3 text-sm"
              value={draft.language ?? 'fa'}
              onChange={(e) => set('language', e.target.value)}
            >
              <option value="fa">{t('aiContent.lang.fa')}</option>
              <option value="en">{t('aiContent.lang.en')}</option>
            </select>
          </div>
          <div className="space-y-2 md:col-span-2">
            <div className="flex items-center justify-between">
              <Label>{t('aiContent.temperature')}</Label>
              <span className="text-muted-foreground text-sm tabular-nums">
                {Number(draft.temperature ?? 0.55).toFixed(2)}
              </span>
            </div>
            <input
              type="range"
              min={0}
              max={2}
              step={0.05}
              value={draft.temperature ?? 0.55}
              onChange={(e) => set('temperature', Number(e.target.value))}
              className="w-full accent-primary"
            />
            <p className="text-muted-foreground text-xs">{t('aiContent.temperatureHint')}</p>
          </div>
          <div className="space-y-1">
            <Label>{t('aiContent.maxTokens')}</Label>
            <Input
              type="number"
              min={0}
              value={draft.max_tokens ?? 0}
              onChange={(e) => set('max_tokens', Number(e.target.value))}
            />
            <p className="text-muted-foreground text-xs">{t('aiContent.maxTokensHint')}</p>
          </div>
          <div className="space-y-1">
            <Label>{t('aiContent.seoSep')}</Label>
            <Input value={draft.seo_sep ?? ' - '} onChange={(e) => set('seo_sep', e.target.value)} />
          </div>
          <div className="flex items-center gap-2 pt-6">
            <Checkbox
              checked={!!draft.require_site_name}
              onCheckedChange={(v) => set('require_site_name', Boolean(v))}
              id="require_site_name"
            />
            <Label htmlFor="require_site_name">{t('aiContent.requireSiteName')}</Label>
          </div>
          <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2 md:col-span-2">
            <div>
              <Label htmlFor="web_research">{t('aiContent.webResearch')}</Label>
              <p className="text-muted-foreground text-xs">{t('aiContent.webResearchHint')}</p>
            </div>
            <Switch
              id="web_research"
              checked={draft.web_research !== false}
              onCheckedChange={(v) => set('web_research', Boolean(v))}
            />
          </div>
          <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2 md:col-span-2">
            <div>
              <Label htmlFor="review_emojis">{t('aiContent.reviewEmojis')}</Label>
              <p className="text-muted-foreground text-xs">{t('aiContent.reviewEmojisHint')}</p>
            </div>
            <Switch
              id="review_emojis"
              checked={!!draft.review_emojis}
              onCheckedChange={(v) => set('review_emojis', Boolean(v))}
            />
          </div>
          <div className="space-y-1">
            <Label>{t('aiContent.internalLinksMin')}</Label>
            <Input
              type="number"
              min={0}
              value={draft.internal_links_min ?? 2}
              onChange={(e) => set('internal_links_min', Number(e.target.value))}
            />
          </div>
          <div className="space-y-1">
            <Label>{t('aiContent.internalLinksMax')}</Label>
            <Input
              type="number"
              min={0}
              value={draft.internal_links_max ?? 4}
              onChange={(e) => set('internal_links_max', Number(e.target.value))}
            />
          </div>
        </CardContent>
      </Card>

      <Card id="ai-sec-design">
        <CardHeader>
          <CardTitle>{t('aiContent.settingsDesign')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2">
            <div>
              <Label htmlFor="palette_mode">{t('aiContent.paletteMode')}</Label>
              <p className="text-muted-foreground text-xs">{t('aiContent.paletteModeHint')}</p>
            </div>
            <select
              id="palette_mode"
              className="flex h-9 rounded-md border bg-background px-3 text-sm"
              value={draft.palette_mode ?? 'site'}
              onChange={(e) => set('palette_mode', (e.target.value === 'suggest' ? 'suggest' : 'site') as 'site' | 'suggest')}
            >
              <option value="site">{t('aiContent.paletteModeSite')}</option>
              <option value="suggest">{t('aiContent.paletteModeSuggest')}</option>
            </select>
          </div>
          <div className="flex flex-wrap gap-2">
            {memQ.data?.palette
              ? Object.entries(memQ.data.palette).map(([slot, hex]) => (
                  <div key={slot} className="flex items-center gap-2 rounded-md border px-2 py-1 text-xs">
                    <span className="inline-block size-4 rounded-sm border" style={{ background: hex }} />
                    <span className="text-muted-foreground">{slot}</span>
                    <span className="font-mono">{hex}</span>
                  </div>
                ))
              : null}
          </div>
          <p className="text-muted-foreground text-xs">
            {t('aiContent.designSource')}: {memQ.data?.source || '—'} ·{' '}
            {memQ.data?.locked ? t('aiContent.designLocked') : t('aiContent.designUnlocked')}
          </p>
          <div className="flex flex-wrap gap-2">
            <Button type="button" size="sm" variant="outline" disabled={extractMem.isPending} onClick={() => void extractMem.mutateAsync()}>
              {t('aiContent.extractKit')}
            </Button>
            <Button type="button" size="sm" variant="outline" disabled={unlockMem.isPending} onClick={() => void unlockMem.mutateAsync()}>
              {t('aiContent.unlockDesign')}
            </Button>
            <Button type="button" size="sm" variant="ghost" disabled={resetMem.isPending} onClick={() => void resetMem.mutateAsync()}>
              {t('aiContent.resetDesign')}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card id="ai-sec-tones">
        <CardHeader>
          <CardTitle>{t('aiContent.settingsTones')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          <p className="text-muted-foreground text-sm">{t('aiContent.settingsTonesHint')}</p>
          <div className="grid gap-2 sm:grid-cols-2">
            {TONE_SLUGS.map((slug) => {
              const tones = draft.tones ?? {}
              const checked = !!tones[slug]
              const enabledCount = TONE_SLUGS.filter((s) => !!tones[s]).length
              return (
                <div
                  key={slug}
                  className="flex items-center justify-between gap-3 rounded-md border px-3 py-2"
                >
                  <Label htmlFor={`tone-${slug}`} className="text-sm font-normal">
                    {t(`aiContent.tone.${slug}`)}
                  </Label>
                  <Switch
                    id={`tone-${slug}`}
                    checked={checked}
                    onCheckedChange={(v) => {
                      const nextOn = Boolean(v)
                      if (!nextOn && enabledCount <= 1) return
                      set('tones', { ...tones, [slug]: nextOn })
                    }}
                  />
                </div>
              )
            })}
          </div>
        </CardContent>
      </Card>

      <Card id="ai-sec-system">
        <CardHeader className="flex flex-row items-center justify-between space-y-0">
          <CardTitle>{t('aiContent.settingsSystemPrompt')}</CardTitle>
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => set('prompt_system', draft.prompt_defaults?.prompt_system ?? '')}
          >
            {t('aiContent.resetPrompt')}
          </Button>
        </CardHeader>
        <CardContent>
          <details className="rounded-lg border p-3" open>
            <summary className="cursor-pointer text-sm font-medium">{t('aiContent.entityPrompt')}</summary>
            <Textarea
              className="mt-3"
              rows={10}
              value={draft.prompt_system ?? ''}
              onChange={(e) => set('prompt_system', e.target.value)}
            />
          </details>
        </CardContent>
      </Card>

      {entities.map((ent) => {
        const enabled = Boolean(draft[ent.doKey])
        const promptVal = String(draft[ent.promptKey] ?? '')
        return (
          <Card key={ent.id} id={`ai-sec-${ent.id}`}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
              <CardTitle>{t(ent.titleKey)}</CardTitle>
              <div className="flex items-center gap-2">
                <Label htmlFor={`do-${ent.id}`} className="text-sm font-normal">
                  {t('aiContent.doEntity')}
                </Label>
                <Switch
                  id={`do-${ent.id}`}
                  checked={enabled}
                  onCheckedChange={(v) => set(ent.doKey, Boolean(v) as never)}
                />
              </div>
            </CardHeader>
            <CardContent className="space-y-3">
              <details className="rounded-lg border p-3">
                <summary className="flex cursor-pointer items-center justify-between text-sm font-medium">
                  <span>{t('aiContent.entityPrompt')}</span>
                </summary>
                <div className="mt-2 flex justify-end">
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => set(ent.promptKey, (draft.prompt_defaults?.[String(ent.promptKey)] ?? '') as never)}
                  >
                    {t('aiContent.resetPrompt')}
                  </Button>
                </div>
                <Textarea
                  rows={4}
                  disabled={!enabled}
                  value={promptVal}
                  onChange={(e) => set(ent.promptKey, e.target.value as never)}
                />
              </details>
              {ent.id === 'page' ? (
                <>
                  <details className="rounded-lg border p-3">
                    <summary className="cursor-pointer text-sm font-medium">{t('aiContent.pageSystemPrompt')}</summary>
                    <Textarea
                      className="mt-3"
                      rows={8}
                      disabled={!enabled}
                      value={draft.prompt_page_system ?? ''}
                      onChange={(e) => set('prompt_page_system', e.target.value)}
                    />
                  </details>
                  <div className="grid gap-3 md:grid-cols-2">
                    <div className="space-y-1">
                      <Label>{t('aiContent.pageProvider')}</Label>
                      <p className="text-muted-foreground text-xs">{t('aiContent.pageProviderHint')}</p>
                      <select
                        className="flex h-9 w-full rounded-md border bg-background px-3 text-sm"
                        disabled={!enabled}
                        value={draft.page_provider ?? ''}
                        onChange={(e) => set('page_provider', e.target.value)}
                      >
                        <option value="">{t('aiContent.pageProviderDefault')}</option>
                        <option value="grok">Grok</option>
                        <option value="gemini">Gemini</option>
                        <option value="openai">ChatGPT</option>
                        <option value="gapgpt">{t('aiContent.gapgpt')}</option>
                      </select>
                    </div>
                    <div className="space-y-1">
                      <Label>{t('aiContent.pageModel')}</Label>
                      <p className="text-muted-foreground text-xs">{t('aiContent.pageModelHint')}</p>
                      <Input
                        disabled={!enabled}
                        value={draft.page_model ?? ''}
                        onChange={(e) => set('page_model', e.target.value)}
                        placeholder={t('aiContent.pageModelPlaceholder')}
                      />
                    </div>
                    <div className="space-y-1 md:col-span-2">
                      <Label>{t('aiContent.pageMaxTokens')}</Label>
                      <Input
                        type="number"
                        min={0}
                        disabled={!enabled}
                        value={draft.page_max_tokens ?? 64000}
                        onChange={(e) => set('page_max_tokens', Number(e.target.value))}
                      />
                    </div>
                  </div>
                </>
              ) : null}
              <div className="overflow-x-auto rounded-lg border">
                <div className="bg-muted/40 text-muted-foreground grid grid-cols-[auto_1fr_auto] gap-3 px-3 py-2 text-xs font-medium">
                  <span>{t('aiContent.doEntity')}</span>
                  <span>{t('aiContent.settingsFields')}</span>
                  <span>{t('aiContent.settingsLength')}</span>
                </div>
                {ent.fields.map((field) => {
                  const spec = draft.fields?.[ent.id]?.[field] ?? { enabled: true, length: 0, unit: 'words' as const }
                  const units = LENGTH_FIELDS[field]
                  return (
                    <div
                      key={field}
                      className="grid grid-cols-[auto_1fr_auto] items-center gap-3 border-t px-3 py-2"
                    >
                      <Switch
                        checked={!!spec.enabled}
                        disabled={!enabled}
                        onCheckedChange={(v) => patchField(ent.id, field, { enabled: Boolean(v) })}
                      />
                      <span className="text-sm">{t(`aiContent.field.${field}`)}</span>
                      {units ? (
                        <div className="flex items-center gap-2">
                          <Input
                            className="w-20"
                            type="number"
                            min={0}
                            disabled={!enabled || !spec.enabled}
                            value={spec.length}
                            onChange={(e) => patchField(ent.id, field, { length: Number(e.target.value) })}
                          />
                          <select
                            className="flex h-9 rounded-md border bg-background px-2 text-sm"
                            disabled={!enabled || !spec.enabled}
                            value={spec.unit}
                            onChange={(e) => patchField(ent.id, field, { unit: e.target.value as AiFieldSpec['unit'] })}
                          >
                            {units.map((u) => (
                              <option key={u} value={u}>
                                {t(`aiContent.unit.${u}`)}
                              </option>
                            ))}
                          </select>
                        </div>
                      ) : (
                        <span />
                      )}
                    </div>
                  )
                })}
              </div>
            </CardContent>
          </Card>
        )
      })}

      <Card id="ai-sec-automation">
        <CardHeader>
          <CardTitle>{t('aiContent.settingsAutomation')}</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2">
          <div className="space-y-1">
            <Label>{t('aiContent.dailyBlogQuota')}</Label>
            <Input
              type="number"
              value={draft.daily_blog_quota ?? 1}
              onChange={(e) => set('daily_blog_quota', Number(e.target.value))}
            />
          </div>
          <div className="space-y-1">
            <Label>{t('aiContent.dailyProductQuota')}</Label>
            <Input
              type="number"
              value={draft.daily_product_quota ?? 5}
              onChange={(e) => set('daily_product_quota', Number(e.target.value))}
            />
          </div>
          <div className="space-y-1">
            <Label>{t('aiContent.publishStatus')}</Label>
            <select
              className="flex h-9 w-full rounded-md border bg-background px-3 text-sm"
              value={draft.publish_status ?? 'draft'}
              onChange={(e) => set('publish_status', e.target.value)}
            >
              <option value="draft">draft</option>
              <option value="pending">pending</option>
              <option value="publish">publish</option>
            </select>
          </div>
          <div className="flex items-center gap-2 pt-6">
            <Checkbox
              checked={!!draft.auto_publish}
              onCheckedChange={(v) => set('auto_publish', Boolean(v))}
              id="auto_publish"
            />
            <Label htmlFor="auto_publish">{t('aiContent.autoPublish')}</Label>
          </div>
          <div className="flex items-center gap-2">
            <Checkbox checked={!!draft.enabled} onCheckedChange={(v) => set('enabled', Boolean(v))} id="enabled" />
            <Label htmlFor="enabled">{t('aiContent.enabled')}</Label>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t('aiContent.settingsCatalog')}</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4 md:grid-cols-2">
          <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2 md:col-span-2">
            <Label htmlFor="catalog_assign_categories">{t('aiContent.catalogAssignCats')}</Label>
            <Switch
              id="catalog_assign_categories"
              checked={draft.catalog_assign_categories !== false}
              onCheckedChange={(v) => set('catalog_assign_categories', Boolean(v))}
            />
          </div>
          <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2 md:col-span-2">
            <Label htmlFor="catalog_assign_brands">{t('aiContent.catalogAssignBrands')}</Label>
            <Switch
              id="catalog_assign_brands"
              checked={draft.catalog_assign_brands !== false}
              onCheckedChange={(v) => set('catalog_assign_brands', Boolean(v))}
            />
          </div>
          <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2 md:col-span-2">
            <div>
              <Label htmlFor="catalog_create_terms">{t('aiContent.catalogCreateTerms')}</Label>
              <p className="text-muted-foreground text-xs">{t('aiContent.catalogCreateTermsHint')}</p>
            </div>
            <Switch
              id="catalog_create_terms"
              checked={draft.catalog_create_terms !== false}
              onCheckedChange={(v) => set('catalog_create_terms', Boolean(v))}
            />
          </div>
          <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2 md:col-span-2">
            <div>
              <Label htmlFor="catalog_only_missing">{t('aiContent.catalogOnlyMissing')}</Label>
              <p className="text-muted-foreground text-xs">{t('aiContent.catalogOnlyMissingHint')}</p>
            </div>
            <Switch
              id="catalog_only_missing"
              checked={draft.catalog_only_missing !== false}
              onCheckedChange={(v) => set('catalog_only_missing', Boolean(v))}
            />
          </div>
          <div className="space-y-1 md:col-span-2">
            <Label>{t('aiContent.promptCatalog')}</Label>
            <Textarea
              rows={4}
              value={draft.prompt_catalog ?? ''}
              onChange={(e) => set('prompt_catalog', e.target.value)}
            />
          </div>
          <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2 md:col-span-2">
            <Label htmlFor="title_enabled">{t('aiContent.titleEnabled')}</Label>
            <Switch
              id="title_enabled"
              checked={draft.title_enabled !== false}
              onCheckedChange={(v) => set('title_enabled', Boolean(v))}
            />
          </div>
          <div className="space-y-1 md:col-span-2">
            <Label>{t('aiContent.titlePattern')}</Label>
            <Input
              value={draft.title_pattern ?? ''}
              onChange={(e) => set('title_pattern', e.target.value)}
            />
            <p className="text-muted-foreground text-xs">{t('aiContent.titlePatternHint')}</p>
          </div>
          <div className="space-y-1">
            <Label>{t('aiContent.titleBrandScript')}</Label>
            <select
              className="flex h-9 w-full rounded-md border bg-background px-3 text-sm"
              value={draft.title_brand_script ?? 'fa'}
              onChange={(e) => set('title_brand_script', (e.target.value === 'en' ? 'en' : 'fa') as 'fa' | 'en')}
            >
              <option value="fa">{t('aiContent.titleBrandFa')}</option>
              <option value="en">{t('aiContent.titleBrandEn')}</option>
            </select>
          </div>
          <div className="flex items-center justify-between gap-3 rounded-md border px-3 py-2">
            <div>
              <Label htmlFor="title_include_feature">{t('aiContent.titleIncludeFeature')}</Label>
              <p className="text-muted-foreground text-xs">{t('aiContent.titleIncludeFeatureHint')}</p>
            </div>
            <Switch
              id="title_include_feature"
              checked={draft.title_include_feature !== false}
              onCheckedChange={(v) => set('title_include_feature', Boolean(v))}
            />
          </div>
          <div className="space-y-1 md:col-span-2">
            <Label>{t('aiContent.promptTitle')}</Label>
            <Textarea
              rows={4}
              value={draft.prompt_title ?? ''}
              onChange={(e) => set('prompt_title', e.target.value)}
            />
          </div>
        </CardContent>
      </Card>

      <div className="bg-background/95 sticky bottom-0 z-20 -mx-1 flex justify-end border-t px-1 py-3 backdrop-blur">
        <Button onClick={() => void save.mutateAsync()} disabled={save.isPending}>
          {t('common.save')}
        </Button>
      </div>
    </div>
    </AiContentLayout>
  )
}
