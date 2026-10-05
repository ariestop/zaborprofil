import { Badge } from '../../shared/ui/badge'
import { defaultRichTextExtensions } from './extensions'

export function ToolbarFoundation() {
  return (
    <div className="mb-3 flex flex-wrap gap-2">
      {defaultRichTextExtensions.map((extension) => (
        <Badge key={extension.name} tone={extension.enabled ? 'success' : 'neutral'}>
          {extension.name}
        </Badge>
      ))}
    </div>
  )
}
