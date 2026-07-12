function cfg() {
  return window.webinoDashboard
}

export function orderPrintUrl(orderId: number, type: 'invoice' | 'label' | 'receipt'): string {
  const c = cfg()
  const base = c.restUrl + `orders/${orderId}/print?type=${type}`
  const sep = base.includes('?') ? '&' : '?'
  return `${base}${sep}_wpnonce=${encodeURIComponent(c.nonce)}`
}

export function openOrderPrint(orderId: number, type: 'invoice' | 'label' | 'receipt') {
  window.open(orderPrintUrl(orderId, type), '_blank', 'noopener,noreferrer')
}
