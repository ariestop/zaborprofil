import { useState } from 'react'
import { useWatch } from 'react-hook-form'
import { fetchPageSeoAudit } from '../../entities/seo/api'
import type { SeoAuditResult } from '../../entities/seo/model'
import { Badge, Button, Card, Input, Textarea } from '../../shared/ui'
import { MediaPicker } from '../media/MediaPicker'
import { countIssues, severityTone } from '../seo/audit/audit-summary'
import { LengthCounter } from '../seo/LengthCounter'
import { useSeoTitleSettings } from '../seo/seoSettings'
import { SnippetPreview } from '../seo/SnippetPreview'
import { descriptionLimits, titleLimits } from '../seo/snippet'
import { Field } from './fields'
import { useAdvancedMode } from './advanced-mode'
import type { EditorTab } from './form'
import type { PageEditorController } from './usePageEditorController'

interface SeoTabProps {
  controller: PageEditorController
  onOpenTab: (tab: EditorTab) => void
}

type AuditState = { status: 'idle' } | { status: 'loading' } | { status: 'error' } | { status: 'done', result: SeoAuditResult }

export function SeoTab({ controller, onOpenTab }: SeoTabProps) {
  const { form } = controller
  const { register, setValue, control, formState: { errors } } = form
  const seoTitleSettings = useSeoTitleSettings()
  const advanced = useAdvancedMode((state) => state.enabled)
  const [audit, setAudit] = useState<AuditState>({ status: 'idle' })

  const metaTitle = useWatch({ control, name: 'metaTitle' })
  const metaDescription = useWatch({ control, name: 'metaDescription' })
  const canonicalUrl = useWatch({ control, name: 'canonicalUrl' })
  const ogImage = useWatch({ control, name: 'ogImage' })
  const title = useWatch({ control, name: 'title' })
  const h1 = useWatch({ control, name: 'h1' })
  const path = useWatch({ control, name: 'path' })
  const isIndexable = useWatch({ control, name: 'isIndexable' })

  const runAudit = async (): Promise<void> => {
    setAudit({ status: 'loading' })
    try {
      if (!(await controller.saveAll())) {
        setAudit({ status: 'idle' })
        return
      }

      setAudit({ status: 'done', result: await fetchPageSeoAudit(controller.pageId) })
    } catch {
      setAudit({ status: 'error' })
    }
  }

  return (
    <div className="grid gap-4">
      <Card title="Сниппет в поиске" description={controller.autosaveEnabled
          ? 'Так страница может выглядеть в выдаче. Изменения SEO сохраняются автоматически.'
          : 'Так страница может выглядеть в выдаче. Страница опубликована: сохраняйте изменения вручную (Ctrl/Cmd+S).'}
      >
        <div className="grid gap-4">
          <SnippetPreview
            metaTitle={metaTitle}
            title={title}
            h1={h1}
            path={path}
            canonicalUrl={canonicalUrl}
            metaDescription={metaDescription}
            titleTemplate={seoTitleSettings.titleTemplate}
            siteName={seoTitleSettings.siteName}
          />
          <Field label="SEO-заголовок (title)" error={errors.metaTitle?.message}>
            {(id, describedBy) => (
              <>
                <Input id={id} aria-describedby={describedBy} placeholder={title} {...register('metaTitle')} />
                <LengthCounter value={metaTitle} limits={titleLimits} emptyHint="Если пусто, используется шаблон и название страницы." />
              </>
            )}
          </Field>
          <Field label="Описание (meta description)" error={errors.metaDescription?.message}>
            {(id, describedBy) => (
              <>
                <Textarea id={id} aria-describedby={describedBy} rows={3} {...register('metaDescription')} />
                <LengthCounter value={metaDescription} limits={descriptionLimits} emptyHint="Без описания поисковик подставит фрагмент текста." />
              </>
            )}
          </Field>
          <Field label="Canonical URL" error={errors.canonicalUrl?.message} hint="Оставьте пустым, чтобы использовать адрес страницы.">
            {(id, describedBy) => <Input id={id} aria-describedby={describedBy} placeholder="https://" {...register('canonicalUrl')} />}
          </Field>
          <p className="text-sm text-slate-600 dark:text-slate-300">
            Индексация: <strong>{isIndexable ? 'разрешена' : 'запрещена (noindex)'}</strong>.{' '}
            <button type="button" className="text-brand-700 underline dark:text-brand-400" onClick={() => onOpenTab('settings')}>
              Изменить во вкладке «Настройки»
            </button>
          </p>
        </div>
      </Card>

      <Card title="Open Graph" description="Заголовок, описание и изображение для превью в соцсетях и мессенджерах.">
        <div className="grid gap-4">
          <Field label="OG-заголовок" error={errors.ogTitle?.message}>
            {(id, describedBy) => <Input id={id} aria-describedby={describedBy} {...register('ogTitle')} />}
          </Field>
          <Field label="OG-описание" error={errors.ogDescription?.message}>
            {(id, describedBy) => <Textarea id={id} aria-describedby={describedBy} rows={2} {...register('ogDescription')} />}
          </Field>
          <MediaPicker
            label="Изображение для соцсетей"
            description="Рекомендуется 1200×630 px."
            value={ogImage}
            absoluteUrl
            onChange={(value) => setValue('ogImage', value, { shouldDirty: true, shouldValidate: true })}
          />
        </div>
      </Card>

      {advanced ? (
        <Card title="JSON-LD (расширенный режим)" description="Массив JSON-объектов schema.org. Ошибка в JSON блокирует сохранение SEO.">
          <Field label="JSON-LD" error={errors.jsonLd?.message}>
            {(id, describedBy) => <Textarea id={id} aria-describedby={describedBy} rows={8} className="font-mono text-xs" {...register('jsonLd')} />}
          </Field>
        </Card>
      ) : null}

      <Card title="SEO-аудит" description="Тот же аудит, который блокирует публикацию: P0/P1 — исправить обязательно, P2 — рекомендации.">
        <div className="flex flex-wrap items-center gap-3">
          <Button type="button" variant="outline" disabled={audit.status === 'loading'} onClick={() => void runAudit()}>
            {audit.status === 'loading' ? 'Проверка…' : 'Проверить SEO'}
          </Button>
          {audit.status === 'done' ? <AuditSummary result={audit.result} /> : null}
          {audit.status === 'error' ? <span className="text-sm text-red-700 dark:text-red-400">Не удалось выполнить аудит.</span> : null}
        </div>
        {audit.status === 'done' && audit.result.issues.length > 0 ? (
          <ul className="mt-3 grid gap-2" data-testid="seo-audit-issues">
            {audit.result.issues.map((issue) => (
              <li key={`${issue.code}-${issue.field}`} className="flex items-start gap-2 text-sm">
                <Badge tone={severityTone(issue.severity)}>{issue.severity}</Badge>
                <span>{issue.message}</span>
              </li>
            ))}
          </ul>
        ) : null}
      </Card>
    </div>
  )
}

function AuditSummary({ result }: { result: SeoAuditResult }) {
  if (result.issues.length === 0) {
    return <Badge tone="success">Замечаний нет</Badge>
  }

  const counts = countIssues(result.issues)
  return (
    <span className="text-sm text-slate-700 dark:text-slate-200">
      {result.passed ? 'Публикация разрешена' : 'Публикация заблокирована'}: P0 — {counts.P0}, P1 — {counts.P1}, P2 — {counts.P2}
    </span>
  )
}
