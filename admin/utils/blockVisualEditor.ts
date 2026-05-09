export type BlockEditorMode = 'visual' | 'json'

export interface TextBlockVisualData {
  title: string
  text: string
}

export interface TextImageBlockVisualData extends TextBlockVisualData {
  image: string
  alt: string
}

export interface VisualBlockFormState {
  mode: BlockEditorMode
  text: TextBlockVisualData
  textImage: TextImageBlockVisualData
}

type UnknownObject = Record<string, unknown>

function asString(value: unknown): string {
  return typeof value === 'string' ? value : ''
}

function pickObject(value: unknown): UnknownObject {
  if (value === null || typeof value !== 'object' || Array.isArray(value)) {
    return {}
  }

  return value as UnknownObject
}

function mergeUnknown(base: UnknownObject, extra: UnknownObject): UnknownObject {
  return {
    ...base,
    ...extra,
  }
}

export function supportsVisualEditor(blockType: string): boolean {
  return blockType === 'text' || blockType === 'text_image'
}

export function detectEditorMode(blockType: string, content: UnknownObject): BlockEditorMode {
  if (!supportsVisualEditor(blockType)) {
    return 'json'
  }

  const text = content.text
  if (text === undefined || typeof text === 'string') {
    return 'visual'
  }

  return 'json'
}

export function createVisualState(blockType: string, contentInput: unknown): VisualBlockFormState {
  const content = pickObject(contentInput)
  const textBase: TextBlockVisualData = {
    title: asString(content.title),
    text: asString(content.text),
  }

  return {
    mode: detectEditorMode(blockType, content),
    text: textBase,
    textImage: {
      ...textBase,
      image: asString(content.image),
      alt: asString(content.alt),
    },
  }
}

export function toBlockContent(
  blockType: string,
  state: VisualBlockFormState,
  existingContentInput: unknown,
): UnknownObject {
  const existingContent = pickObject(existingContentInput)

  if (!supportsVisualEditor(blockType) || state.mode === 'json') {
    return existingContent
  }

  if (blockType === 'text') {
    return mergeUnknown(existingContent, {
      title: state.text.title,
      text: state.text.text,
    })
  }

  return mergeUnknown(existingContent, {
    title: state.textImage.title,
    text: state.textImage.text,
    image: state.textImage.image,
    alt: state.textImage.alt,
  })
}
