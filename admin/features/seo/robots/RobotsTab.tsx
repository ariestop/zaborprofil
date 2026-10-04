import { useEffect, useState } from 'react'
import { Badge, Button, Card, ConfirmDialog, ErrorState, PageLoadingState, Textarea } from '../../../shared/ui'
import { useToast } from '../../../app/providers/toast-provider'
import { previewRobots, useRobotsQuery, useSaveRobotsMutation } from '../../../entities/seo/api'
import type { RobotsPreview } from '../../../entities/seo/model'
import { useDebouncedValue } from '../../../shared/hooks/use-debounced-value'
import { describeApiError } from '../redirects/redirect-rules'

export function RobotsTab() {
  const robotsQuery = useRobotsQuery()

  if (robotsQuery.isPending) {
    return <PageLoadingState />
  }

  if (robotsQuery.isError) {
    return <ErrorState title="Не удалось загрузить robots.txt" description="Проверьте endpoint /admin/api/seo/robots и право seo.edit." />
  }

  return <RobotsEditor savedBody={robotsQuery.data.body} defaultBody={robotsQuery.data.defaultBody} environment={robotsQuery.data.environment} />
}

interface RobotsEditorProps {
  savedBody: string
  defaultBody: string
  environment: string
}

function RobotsEditor({ savedBody, defaultBody, environment }: RobotsEditorProps) {
  const { push } = useToast()
  const saveMutation = useSaveRobotsMutation()
  const [draft, setDraft] = useState(savedBody)
  const [preview, setPreview] = useState<RobotsPreview | null>(null)
  const [previewFailed, setPreviewFailed] = useState(false)
  const [resetOpen, setResetOpen] = useState(false)
  const debouncedDraft = useDebouncedValue(draft, 400)

  useEffect(() => {
    let cancelled = false
    previewRobots(debouncedDraft)
      .then((result) => {
        if (!cancelled) {
          setPreview(result)
          setPreviewFailed(false)
        }
      })
      .catch(() => {
        if (!cancelled) {
          setPreviewFailed(true)
        }
      })

    return () => {
      cancelled = true
    }
  }, [debouncedDraft])

  const dirty = draft.trim() !== savedBody.trim()
  const stalePreview = debouncedDraft !== draft
  const hasErrors = preview !== null && !preview.valid
  const errorCount = preview?.issues.filter((issue) => issue.severity === 'error').length ?? 0
  const warningCount = preview?.issues.filter((issue) => issue.severity === 'warning').length ?? 0

  const save = async (body: string | null, successTitle: string) => {
    try {
      const saved = await saveMutation.mutateAsync(body)
      setDraft(saved.body)
      push({ title: successTitle })
    } catch (error) {
      push({ title: 'Не удалось сохранить robots.txt', description: describeApiError(error, 'Исправьте ошибки в содержимом.') })
    }
  }

  return (
    <div className="grid gap-4">
      <Card
        title="robots.txt"
        description="Правила для поисковых роботов. Пустое содержимое — стандартный файл: сайт открыт, закрыты /admin/ и /api/, подключена карта sitemap.xml."
      >
        <div className="grid gap-3">
          {preview?.overriddenByEnvironment === true ? (
            <p className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200" role="note">
              Окружение «{environment}» не production: сервер всегда отдаёт роботам «Disallow: /», независимо от сохранённого содержимого. Настройки вступят в силу на production.
            </p>
          ) : null}

          <Textarea
            aria-label="Содержимое robots.txt"
            className="min-h-72 font-mono text-xs"
            spellCheck={false}
            placeholder={defaultBody}
            value={draft}
            onChange={(event) => setDraft(event.target.value)}
          />

          <div className="flex flex-wrap items-center gap-2">
            <Button type="button" disabled={!dirty || hasErrors || stalePreview || saveMutation.isPending} onClick={() => void save(draft, 'robots.txt сохранён')}>
              {saveMutation.isPending ? 'Сохранение...' : 'Сохранить'}
            </Button>
            <Button type="button" variant="outline" onClick={() => setDraft(defaultBody)}>Подставить стандартный шаблон</Button>
            <Button type="button" variant="outline" disabled={savedBody.trim() === ''} onClick={() => setResetOpen(true)}>Сбросить к стандартному</Button>
            <a className="text-sm text-emerald-700 underline dark:text-emerald-400" href="/robots.txt" target="_blank" rel="noreferrer">Открыть /robots.txt</a>
          </div>
        </div>
      </Card>

      <Card title="Проверка" description="Синтаксис проверяется на сервере при каждом изменении. Ошибки блокируют сохранение, предупреждения — только рекомендации.">
        {previewFailed ? <p className="text-sm text-red-600">Не удалось выполнить проверку на сервере.</p> : null}
        {preview === null && !previewFailed ? <p className="text-sm text-slate-500">Проверка...</p> : null}
        {preview !== null ? (
          <div className="grid gap-2" aria-live="polite">
            <div className="flex flex-wrap items-center gap-2 text-sm">
              <Badge tone={errorCount > 0 ? 'warning' : 'success'}>{errorCount > 0 ? `Ошибок: ${errorCount}` : 'Ошибок нет'}</Badge>
              <Badge tone={warningCount > 0 ? 'warning' : 'neutral'}>Предупреждений: {warningCount}</Badge>
            </div>
            {preview.issues.length > 0 ? (
              <ul className="space-y-1 text-sm" aria-label="Замечания к robots.txt">
                {preview.issues.map((issue, index) => (
                  <li
                    key={`${issue.line ?? 'file'}-${index}`}
                    className={issue.severity === 'error' ? 'text-red-700 dark:text-red-400' : 'text-amber-700 dark:text-amber-400'}
                  >
                    <strong>{issue.severity === 'error' ? 'Ошибка' : 'Предупреждение'}</strong>
                    {issue.line !== null ? ` (строка ${issue.line})` : ''}: {issue.message}
                  </li>
                ))}
              </ul>
            ) : null}
          </div>
        ) : null}
      </Card>

      <Card
        title="Предпросмотр"
        description={preview?.usesDefault === true ? 'Содержимое пустое — используется стандартный файл.' : 'Так файл будет сохранён и отдан роботам (на production).'}
      >
        <pre className="max-h-72 overflow-auto rounded-lg bg-slate-50 p-3 font-mono text-xs dark:bg-slate-950" aria-label="Предпросмотр robots.txt" data-testid="robots-preview">
          {preview === null ? '' : (preview.normalizedBody ?? defaultBody)}
        </pre>
      </Card>

      <ConfirmDialog
        open={resetOpen}
        title="Сбросить robots.txt?"
        description="Собственное содержимое будет удалено, сайт начнёт отдавать стандартный robots.txt."
        confirmLabel="Сбросить"
        onConfirm={() => {
          setResetOpen(false)
          void save(null, 'robots.txt сброшен к стандартному')
        }}
        onCancel={() => setResetOpen(false)}
      />
    </div>
  )
}
