export interface RichTextExtensionDefinition {
  name: 'link' | 'image' | 'table' | 'list' | 'heading'
  enabled: boolean
}

export const defaultRichTextExtensions: RichTextExtensionDefinition[] = [
  { name: 'link', enabled: true },
  { name: 'image', enabled: true },
  { name: 'table', enabled: true },
  { name: 'list', enabled: true },
  { name: 'heading', enabled: true },
]
