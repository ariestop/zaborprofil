export interface PageEntity {
  id: string
  title: string
  slug: string
  status: 'draft' | 'published' | 'archived'
  updatedAt: string
}
