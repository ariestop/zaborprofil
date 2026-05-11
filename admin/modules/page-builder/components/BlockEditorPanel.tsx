import { useEffect } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { RichTextEditor } from '../../../features/rich-text/RichTextEditor'
import { Button } from '../../../shared/ui'
import { blockModules } from '../blocks'
import { blockRegistryByType } from '../registry/blockRegistry'
import type { BuilderBlock } from '../types'
import { BlockToolbar } from './BlockToolbar'

const editorSchema = z.object({
  contentJson: z.string().min(2),
  settingsJson: z.string().min(2),
})

interface BlockEditorPanelProps {
  block: BuilderBlock | null
  onUpdate: (nextBlock: BuilderBlock) => void
  onDelete: (blockId: string) => void
  onDuplicate: (blockId: string) => void
}

export function BlockEditorPanel({ block, onUpdate, onDelete, onDuplicate }: BlockEditorPanelProps) {
  const form = useForm<z.infer<typeof editorSchema>>({
    resolver: zodResolver(editorSchema),
    defaultValues: {
      contentJson: '{}',
      settingsJson: '{}',
    },
  })

  useEffect(() => {
    if (block === null) {
      return
    }

    form.reset({
      contentJson: JSON.stringify(block.content, null, 2),
      settingsJson: JSON.stringify(block.settings, null, 2),
    })
  }, [block, form])

  if (block === null) {
    return (
      <div className="rounded-xl border border-slate-200 p-4 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
        Выберите блок в списке, чтобы отредактировать его параметры.
      </div>
    )
  }

  const definition = blockRegistryByType.get(block.type)
  const ModuleEditor = blockModules[block.type]?.Editor
  const richTextValue = typeof block.content.html === 'string'
    ? block.content.html
    : typeof block.content.text === 'string'
      ? block.content.text
      : ''

  return (
    <section className="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
      <div>
        <h3 className="text-sm font-semibold">{definition?.title ?? block.type}</h3>
        <p className="text-xs text-slate-500 dark:text-slate-400">{definition?.description ?? 'Редактирование блока'}</p>
      </div>

      <BlockToolbar
        enabled={block.enabled}
        onToggle={() => onUpdate({ ...block, enabled: !block.enabled })}
        onDelete={() => onDelete(block.id)}
        onDuplicate={() => onDuplicate(block.id)}
      />

      {(block.type === 'rich-text' || typeof block.content.html === 'string' || typeof block.content.text === 'string') ? (
        <div className="space-y-2">
          <p className="text-xs font-medium uppercase text-slate-500">Rich text</p>
          <RichTextEditor
            initialValue={richTextValue}
            onChange={(value) => {
              const key = typeof block.content.html === 'string' || block.type === 'rich-text' ? 'html' : 'text'
              onUpdate({
                ...block,
                content: {
                  ...block.content,
                  [key]: value,
                },
              })
            }}
          />
        </div>
      ) : null}

      {ModuleEditor !== undefined ? <ModuleEditor block={block} onChange={onUpdate} /> : null}

      <form
        className="space-y-3"
        onSubmit={form.handleSubmit((values) => {
          let nextContent: Record<string, unknown>
          let nextSettings: Record<string, unknown>

          try {
            nextContent = JSON.parse(values.contentJson) as Record<string, unknown>
            nextSettings = JSON.parse(values.settingsJson) as Record<string, unknown>
          } catch {
            form.setError('contentJson', { message: 'Невалидный JSON' })
            return
          }

          onUpdate({
            ...block,
            content: nextContent,
            settings: nextSettings,
            metadata: {
              ...block.metadata,
              updatedAt: new Date().toISOString(),
            },
          })
        })}
      >
        <label className="block text-xs font-medium uppercase text-slate-500">Content JSON</label>
        <textarea
          className="min-h-36 w-full rounded-md border border-slate-300 bg-white p-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-900"
          {...form.register('contentJson')}
        />
        <label className="block text-xs font-medium uppercase text-slate-500">Settings JSON</label>
        <textarea
          className="min-h-32 w-full rounded-md border border-slate-300 bg-white p-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-900"
          {...form.register('settingsJson')}
        />
        <Button type="submit" size="sm">Применить JSON</Button>
      </form>
    </section>
  )
}
