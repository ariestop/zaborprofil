export interface BuilderSnapshot {
  html: string
  css: string
}

export interface BuilderStorageAdapter {
  load: (pageId: string) => Promise<BuilderSnapshot>
  save: (pageId: string, snapshot: BuilderSnapshot) => Promise<void>
}

export interface BuilderVersionRecord {
  id: string
  createdAt: string
  author: string
  comment: string
}

export interface BuilderBlockDefinition {
  type: string
  title: string
  category: string
}

export interface BuilderBlockItem {
  id: string
  pageId: string
  type: string
  name: string
  position: number
  isEnabled: boolean
  visibility: 'public' | 'hidden' | 'unlisted'
  content: Record<string, unknown>
  settings: Record<string, unknown>
}
