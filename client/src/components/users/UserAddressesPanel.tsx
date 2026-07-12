import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { toastApiError } from '@/lib/apiError'

import { OrderSidebarPanel } from '@/components/orders/OrderSidebarPanel'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { localizeDigits } from '@/lib/digits'
import { apiFetch } from '@/lib/api'

export type UserAddress = {
  id: string
  label: string
  first_name: string
  last_name: string
  state: string
  city: string
  address_1: string
  address_2?: string
  postcode: string
  phone: string
}

type UserAddressesPanelProps = {
  userId: number
  addresses: UserAddress[]
  defaultAddressId?: string
  onChanged: () => void
}

const emptyForm = (): Omit<UserAddress, 'id'> => ({
  label: '',
  first_name: '',
  last_name: '',
  state: '',
  city: '',
  address_1: '',
  address_2: '',
  postcode: '',
  phone: '',
})

export function UserAddressesPanel({ userId, addresses, defaultAddressId, onChanged }: UserAddressesPanelProps) {
  const { t, i18n } = useTranslation()
  const qc = useQueryClient()
  const [editingId, setEditingId] = useState<string | null>(null)
  const [form, setForm] = useState(emptyForm())
  const [showForm, setShowForm] = useState(false)

  const save = useMutation({
    mutationFn: () => {
      const url = editingId
        ? `users/${userId}/addresses/${encodeURIComponent(editingId)}`
        : `users/${userId}/addresses`
      return apiFetch(url, {
        method: editingId ? 'PATCH' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form),
      })
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['user', userId] })
      toast.success(t('common.saved'))
      setShowForm(false)
      setEditingId(null)
      setForm(emptyForm())
      onChanged()
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const remove = useMutation({
    mutationFn: (addressId: string) =>
      apiFetch(`users/${userId}/addresses/${encodeURIComponent(addressId)}`, { method: 'DELETE' }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['user', userId] })
      toast.success(t('common.deleted'))
      onChanged()
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  const setDefault = useMutation({
    mutationFn: (addressId: string) =>
      apiFetch(`users/${userId}/addresses/${encodeURIComponent(addressId)}/default`, { method: 'POST' }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['user', userId] })
      onChanged()
    },
    onError: (e: Error) => toastApiError(t, e),
  })

  function startEdit(addr: UserAddress) {
    setEditingId(addr.id)
    setForm({
      label: addr.label,
      first_name: addr.first_name,
      last_name: addr.last_name,
      state: addr.state,
      city: addr.city,
      address_1: addr.address_1,
      address_2: addr.address_2 ?? '',
      postcode: addr.postcode,
      phone: addr.phone,
    })
    setShowForm(true)
  }

  return (
    <OrderSidebarPanel title={t('users.sectionAddresses')} defaultOpen>
      <div className="space-y-3 text-start">
        {addresses.map((addr) => (
          <div key={addr.id} className="rounded-md border border-border p-3 text-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <p className="font-medium">
                {addr.label}
                {defaultAddressId === addr.id ? (
                  <Badge variant="secondary" className="ms-2">
                    {t('users.defaultAddress')}
                  </Badge>
                ) : null}
              </p>
              <div className="flex gap-1">
                {defaultAddressId !== addr.id ? (
                  <Button type="button" size="sm" variant="ghost" onClick={() => void setDefault.mutateAsync(addr.id)}>
                    {t('users.setDefaultAddress')}
                  </Button>
                ) : null}
                <Button type="button" size="sm" variant="ghost" onClick={() => startEdit(addr)}>
                  {t('common.edit')}
                </Button>
                <Button type="button" size="sm" variant="ghost" onClick={() => void remove.mutateAsync(addr.id)}>
                  {t('common.delete')}
                </Button>
              </div>
            </div>
            <p className="text-muted-foreground mt-1">
              {addr.first_name} {addr.last_name} — {addr.state}، {addr.city}
            </p>
            <p className="text-muted-foreground">{addr.address_1}</p>
            <p className="text-muted-foreground text-xs">
              {localizeDigits(addr.postcode, i18n.language)} · {localizeDigits(addr.phone, i18n.language)}
            </p>
          </div>
        ))}

        {showForm ? (
          <div className="space-y-2 rounded-md border border-dashed p-3">
            {(['label', 'first_name', 'last_name', 'state', 'city', 'address_1', 'address_2', 'postcode', 'phone'] as const).map(
              (key) => (
                <div key={key}>
                  <Label className="text-xs">{t(`users.addr.${key}`)}</Label>
                  <Input
                    className="mt-1"
                    value={form[key] ?? ''}
                    onChange={(e) => setForm((f) => ({ ...f, [key]: e.target.value }))}
                  />
                </div>
              ),
            )}
            <div className="flex gap-2">
              <Button type="button" size="sm" disabled={save.isPending} onClick={() => void save.mutateAsync()}>
                {t('common.save')}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="ghost"
                onClick={() => {
                  setShowForm(false)
                  setEditingId(null)
                  setForm(emptyForm())
                }}
              >
                {t('common.cancel')}
              </Button>
            </div>
          </div>
        ) : (
          <Button type="button" size="sm" variant="outline" onClick={() => setShowForm(true)}>
            {t('users.addAddress')}
          </Button>
        )}
      </div>
    </OrderSidebarPanel>
  )
}
