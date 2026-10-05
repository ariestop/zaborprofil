import type { Editor } from '@tiptap/core'

type ToolbarActionId =
  | 'undo'
  | 'redo'
  | 'bold'
  | 'italic'
  | 'strike'
  | 'code'
  | 'underline'
  | 'subscript'
  | 'superscript'
  | 'bulletList'
  | 'orderedList'
  | 'blockquote'
  | 'alignLeft'
  | 'alignCenter'
  | 'alignRight'
  | 'alignJustify'
  | 'link'

interface ToolbarActionDefinition {
  id: ToolbarActionId
  label: string
  title: string
  isActive: (editor: Editor) => boolean
  run: (editor: Editor, runLinkPrompt: () => void) => void
}

export interface TiptapToolbarAction {
  id: ToolbarActionId
  label: string
  title: string
  isActive: boolean
  run: () => void
}

const toolbarActionDefinitions: ToolbarActionDefinition[] = [
  {
    id: 'undo',
    label: '↶',
    title: 'Отменить',
    isActive: () => false,
    run: (editor) => {
      editor.chain().focus().undo().run()
    },
  },
  {
    id: 'redo',
    label: '↷',
    title: 'Повторить',
    isActive: () => false,
    run: (editor) => {
      editor.chain().focus().redo().run()
    },
  },
  {
    id: 'bold',
    label: 'B',
    title: 'Жирный',
    isActive: (editor) => editor.isActive('bold'),
    run: (editor) => {
      editor.chain().focus().toggleBold().run()
    },
  },
  {
    id: 'italic',
    label: 'I',
    title: 'Курсив',
    isActive: (editor) => editor.isActive('italic'),
    run: (editor) => {
      editor.chain().focus().toggleItalic().run()
    },
  },
  {
    id: 'strike',
    label: 'S',
    title: 'Зачеркнутый',
    isActive: (editor) => editor.isActive('strike'),
    run: (editor) => {
      editor.chain().focus().toggleStrike().run()
    },
  },
  {
    id: 'code',
    label: '</>',
    title: 'Код',
    isActive: (editor) => editor.isActive('code'),
    run: (editor) => {
      editor.chain().focus().toggleCode().run()
    },
  },
  {
    id: 'underline',
    label: 'U',
    title: 'Подчеркнутый',
    isActive: (editor) => editor.isActive('underline'),
    run: (editor) => {
      editor.chain().focus().toggleUnderline().run()
    },
  },
  {
    id: 'subscript',
    label: 'x₂',
    title: 'Нижний индекс',
    isActive: (editor) => editor.isActive('subscript'),
    run: (editor) => {
      editor.chain().focus().toggleSubscript().run()
    },
  },
  {
    id: 'superscript',
    label: 'x²',
    title: 'Верхний индекс',
    isActive: (editor) => editor.isActive('superscript'),
    run: (editor) => {
      editor.chain().focus().toggleSuperscript().run()
    },
  },
  {
    id: 'bulletList',
    label: '•≡',
    title: 'Маркированный список',
    isActive: (editor) => editor.isActive('bulletList'),
    run: (editor) => {
      editor.chain().focus().toggleBulletList().run()
    },
  },
  {
    id: 'orderedList',
    label: '1≡',
    title: 'Нумерованный список',
    isActive: (editor) => editor.isActive('orderedList'),
    run: (editor) => {
      editor.chain().focus().toggleOrderedList().run()
    },
  },
  {
    id: 'blockquote',
    label: '❝',
    title: 'Цитата',
    isActive: (editor) => editor.isActive('blockquote'),
    run: (editor) => {
      editor.chain().focus().toggleBlockquote().run()
    },
  },
  {
    id: 'alignLeft',
    label: '≡',
    title: 'Выравнивание слева',
    isActive: (editor) => editor.isActive({ textAlign: 'left' }),
    run: (editor) => {
      editor.chain().focus().setTextAlign('left').run()
    },
  },
  {
    id: 'alignCenter',
    label: '≣',
    title: 'Выравнивание по центру',
    isActive: (editor) => editor.isActive({ textAlign: 'center' }),
    run: (editor) => {
      editor.chain().focus().setTextAlign('center').run()
    },
  },
  {
    id: 'alignRight',
    label: '☰',
    title: 'Выравнивание справа',
    isActive: (editor) => editor.isActive({ textAlign: 'right' }),
    run: (editor) => {
      editor.chain().focus().setTextAlign('right').run()
    },
  },
  {
    id: 'alignJustify',
    label: '☷',
    title: 'Выравнивание по ширине',
    isActive: (editor) => editor.isActive({ textAlign: 'justify' }),
    run: (editor) => {
      editor.chain().focus().setTextAlign('justify').run()
    },
  },
  {
    id: 'link',
    label: '🔗',
    title: 'Ссылка',
    isActive: (editor) => editor.isActive('link'),
    run: (_editor, runLinkPrompt) => {
      runLinkPrompt()
    },
  },
]

export function useTiptapToolbar(
  editor: Editor | null | undefined,
  runLinkPrompt: () => void,
): TiptapToolbarAction[] {
  return toolbarActionDefinitions.map((definition) => ({
    id: definition.id,
    label: definition.label,
    title: definition.title,
    isActive: editor ? definition.isActive(editor) : false,
    run: () => {
      if (!editor) {
        return
      }
      definition.run(editor, runLinkPrompt)
    },
  }))
}
