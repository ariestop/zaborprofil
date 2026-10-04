import { useState } from 'react'
import { useToast } from '../../app/providers/toast-provider'
import { useDeleteTemplateMutation, useSectionTemplatesQuery } from '../../entities/page/api'
import { Badge, Button, Dialog, EmptyState } from '../../shared/ui'
import { useCan } from '../../stores/auth'
import type { PageTemplateItem } from '../../types/api'
import { describeApiError } from '../seo/redirects/redirect-rules'

interface SectionTemplatesDialogProps {
  open: boolean
  onClose: () => void
  onInsert: (template: PageTemplateItem) => void
}

export function SectionTemplatesDialog({ open, onClose, onInsert }: SectionTemplatesDialogProps) {
  const { push } = useToast()
  const query = useSectionTemplatesQuery()
  const remove = useDeleteTemplateMutation()
  const canManageTemplates = useCan('pages.manage_templates')
  const [confirmCode, setConfirmCode] = useState<string | null>(null)
  const templates = query.data ?? []

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => { if (!next) onClose() }}
      title="Вставить секцию из шаблона"
      description="Блоки секции добавятся в конец страницы."
      contentClassName="max-w-2xl"
    >
      {query.isPending ? <p className="text-sm text-slate-500">Загрузка…</p> : null}
      {query.isError ? <p role="alert" className="text-sm text-red-700">Не удалось загрузить шаблоны секций.</p> : null}
      {query.isSuccess && templates.length === 0 ? (
        <EmptyState title="Шаблонов секций пока нет" description="Выберите блок на странице и нажмите «Сохранить как шаблон секции»." />
      ) : null}
      <ul className="max-h-96 space-y-2 overflow-y-auto">
        {templates.map((template) => (
          <li key={template.code} className="flex items-start justify-between gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-800">
            <div className="text-sm">
              <p className="font-medium">
                {template.name}
                {template.isSystem ? <span className="ml-2"><Badge>системный</Badge></span> : null}
              </p>
              {template.description !== null && template.description !== '' ? <p className="text-slate-600 dark:text-slate-300">{template.description}</p> : null}
              <p className="text-xs text-slate-500">Блоков: {template.blocksSchema.length}</p>
            </div>
            <div className="flex shrink-0 gap-2">
              <Button type="button" size="sm" onClick={() => onInsert(template)}>Вставить</Button>
              {template.isSystem || !canManageTemplates ? null : confirmCode === template.code ? (
                <Button
                  type="button"
                  size="sm"
                  variant="danger"
                  disabled={remove.isPending}
                  onClick={() => remove.mutate(template.code, {
                    onSuccess: () => setConfirmCode(null),
                    onError: (cause) => push({ title: 'Не удалось удалить шаблон', description: describeApiError(cause, 'Проверьте права доступа.') }),
                  })}
                >
                  Точно удалить
                </Button>
              ) : (
                <Button type="button" size="sm" variant="outline" onClick={() => setConfirmCode(template.code)}>Удалить</Button>
              )}
            </div>
          </li>
        ))}
      </ul>
    </Dialog>
  )
}
