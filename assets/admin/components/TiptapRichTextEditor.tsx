import { SimpleEditor } from './tiptap-templates/tiptap-templates/simple/simple-editor'

interface Props {
  modelValue: string
  onChange: (value: string) => void
}

export default function TiptapRichTextEditor({ modelValue, onChange }: Props) {
  return <SimpleEditor content={modelValue} onChange={onChange} />
}
