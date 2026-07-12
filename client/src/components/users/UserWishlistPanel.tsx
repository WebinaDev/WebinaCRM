import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { OrderSidebarPanel } from '@/components/orders/OrderSidebarPanel'
import { MoneyDisplay } from '@/components/currency/MoneyDisplay'

export type WishlistItem = {
  id: number
  name: string
  thumbnail?: string
  price?: string | number
}

type UserWishlistPanelProps = {
  items: WishlistItem[]
  locale: string
}

export function UserWishlistPanel({ items, locale }: UserWishlistPanelProps) {
  const { t } = useTranslation()

  return (
    <OrderSidebarPanel title={t('users.sectionWishlist')} defaultOpen={items.length > 0}>
      {items.length === 0 ? (
        <p className="text-muted-foreground text-sm">{t('common.empty')}</p>
      ) : (
        <ul className="space-y-2 text-start">
          {items.map((item) => (
            <li key={item.id} className="flex items-center gap-3 border-b pb-2 last:border-0">
              {item.thumbnail ? (
                <img src={item.thumbnail} alt="" className="size-10 rounded object-cover" />
              ) : null}
              <div className="min-w-0 flex-1">
                <Link to={`/shop/products/${item.id}`} className="text-primary line-clamp-2 text-sm hover:underline">
                  {item.name}
                </Link>
                {item.price != null && item.price !== '' ? (
                  <p className="text-muted-foreground mt-0.5 text-xs">
                    <MoneyDisplay amount={parseFloat(String(item.price))} locale={locale} />
                  </p>
                ) : null}
              </div>
            </li>
          ))}
        </ul>
      )}
    </OrderSidebarPanel>
  )
}
