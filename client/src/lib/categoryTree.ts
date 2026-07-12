export type Category = {
  id: number
  name: string
  slug: string
  parent: number
  description: string
  count: number
  url: string
}

export type CategoryTreeNode = {
  node: Category
  depth: number
}

export function slugifyFromName(name: string): string {
  return name
    .trim()
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, '-')
    .replace(/[^\p{L}\p{N}-]+/gu, '')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
}

export function buildCategoryTree(items: Category[]): CategoryTreeNode[] {
  const byParent = new Map<number, Category[]>()

  for (const item of items) {
    const parentId = item.parent || 0
    const bucket = byParent.get(parentId) ?? []
    bucket.push(item)
    byParent.set(parentId, bucket)
  }

  for (const bucket of byParent.values()) {
    bucket.sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }))
  }

  const out: CategoryTreeNode[] = []

  function walk(parentId: number, depth: number) {
    for (const node of byParent.get(parentId) ?? []) {
      out.push({ node, depth })
      walk(node.id, depth + 1)
    }
  }

  walk(0, 0)
  return out
}

export function parentSelectOptions(items: Category[], excludeId?: number): CategoryTreeNode[] {
  const filtered = excludeId ? items.filter((item) => item.id !== excludeId) : items
  return buildCategoryTree(filtered)
}
