export type CommentStatusFilter = 'all' | 'hold' | 'approve' | 'spam' | 'trash'

export type CommentRow = {
  id: number
  parent_id: number
  author: string
  author_email: string
  content: string
  excerpt: string
  status: string
  date: string
  post_id: number
  post_title: string
  post_url: string
  post_type: string
  parent_excerpt: string
}

export type CommentCounts = {
  all: number
  hold: number
  approve: number
  spam: number
  trash: number
}

export type CommentColumnId = 'author' | 'excerpt' | 'post' | 'date' | 'status' | 'email'

export type CommentColumnVisibility = Record<CommentColumnId, boolean>
