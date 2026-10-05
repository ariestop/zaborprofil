import { useState } from 'react'
import { useToast } from '../../app/providers/toast-provider'
import { useSaveTemplateMutation } from '../../entities/page/api'
import type { BuilderBlock } from '../../modules/page-builder/types'
import { Button, Dialog, Input, Textarea } from '../../shared/ui'
import { describeApiError } from '../seo/redirects/redirect-rules'

export interface SaveTemplateRequest {
  kind: 'page' | 'section'
  blocks: BuilderBlock[]
}

interface SaveTemplateDialogProps {
  request: SaveTemplateRequest | null
  pageType: string
  onClose: () => void
}

export function SaveTemplateDialog({ request, pageType, onClose }: SaveTemplateDialogProps) {
  const isSection = request?.kind === 'section'

  return (
    <Dialog
      open={request !== null}
      onOpenChange={(open) => { if (!open) onClose() }}
      title={isSection ? 'Сохранить секцию как шаблон' : 'Сохранить страницу как шаблон'}
      description={isSection
        ? 'Секцию можно будет вставлять на другие страницы из диалога «Вставить секцию».'
        : 'Шаблон появится в списке при создании новой страницы такого же типа.'}
    >
      {request === null ? null : <SaveTemplateForm request={request} pageType={pageType} onClose={onClose} />}
    </Dialog>
  )
}

function SaveTemplateForm({ request, pageType, onClose }: { request: SaveTemplateRequest; pageType: string; onClose: () => void }) {
  const { push } = useToast()
  const save = useSaveTemplateMutation()
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [error, setError] = useState<string | null>(null)

  return (
    <form
      className="space-y-4"
      onSubmit={(event) => {
        event.preventDefault()
        const trimmed = name.trim()
        if (trimmed === '') {
          setError('Укажите название шаблона.')
          return
        }

        setError(null)
        save.mutate(
          { name: trimmed, description: description.trim() === '' ? null : description.trim(), kind: request.kind, pageType, blocks: request.blocks },
          {
            onSuccess: () => {
              push({ title: 'Шаблон сохранён', description: trimmed })
              onClose()
            },
            onError: (cause) => setError(describeApiError(cause, 'Не удалось сохранить шаблон. Проверьте заполнение блоков и права доступа.')),
          },
        )
      }}
    >
      <label className="block text-sm font-medium text-ink dark:text-slate-200">
        Название шаблона
        <Input className="mt-1" value={name} maxLength={180} onChange={(event) => setName(event.target.value)} />
      </label>
      <label className="block text-sm font-medium text-ink dark:text-slate-200">
        Описание (необязательно)
        <Textarea className="mt-1" value={description} onChange={(event) => setDescription(event.target.value)} />
      </label>
      <p className="text-xs text-graphite dark:text-slate-500">
        Блоков в шаблоне: {request.blocks.length}. Тексты и изображения копируются как есть — замените их на странице после вставки.
      </p>
      {error !== null ? <p role="alert" className="text-sm text-red-700">{error}</p> : null}
      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onClose}>Отмена</Button>
        <Button type="submit" disabled={save.isPending}>{save.isPending ? 'Сохранение…' : 'Сохранить шаблон'}</Button>
      </div>
    </form>
  )
}
