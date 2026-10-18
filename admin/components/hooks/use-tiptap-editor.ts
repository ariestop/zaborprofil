import type { Editor } from "@tiptap/react"
import { useCurrentEditor, useEditorState } from "@tiptap/react"

/**
 * Редактор из пропсов или из EditorContext. Подписка через useEditorState
 * перерисовывает кнопки панели при каждой смене состояния документа.
 */
export function useTiptapEditor(providedEditor?: Editor | null): {
  editor: Editor | null
  editorState?: Editor["state"]
} {
  const { editor: coreEditor } = useCurrentEditor()
  const mainEditor = providedEditor ?? coreEditor

  const editorState = useEditorState({
    editor: mainEditor,
    selector(context) {
      if (!context.editor) {
        return { editor: null, editorState: undefined }
      }

      return {
        editor: context.editor,
        editorState: context.editor.state,
      }
    },
  })

  return editorState ?? { editor: null }
}
