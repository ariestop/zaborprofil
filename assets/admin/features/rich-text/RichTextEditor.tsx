import { useEffect, useState } from 'react'
import TiptapRichTextEditor from '../../components/TiptapRichTextEditor'
import { ToolbarFoundation } from './ToolbarFoundation'

interface RichTextEditorProps {
  initialValue?: string
  onChange?: (value: string) => void
}

export function RichTextEditor({ initialValue = '<p>Контент страницы</p>', onChange }: RichTextEditorProps) {
  const [value, setValue] = useState(initialValue)

  useEffect(() => {
    setValue(initialValue)
  }, [initialValue])

  return (
    <section>
      <ToolbarFoundation />
      <TiptapRichTextEditor
        modelValue={value}
        onChange={(nextValue) => {
          setValue(nextValue)
          onChange?.(nextValue)
        }}
      />
    </section>
  )
}
