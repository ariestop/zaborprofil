import { useRef, useState, type ChangeEvent } from 'react'
import { Badge, Button, Checkbox, Dialog, Textarea } from '../../../shared/ui'
import { useToast } from '../../../app/providers/toast-provider'
import { importRedirects, useImportRedirectsMutation } from '../../../entities/seo/api'
import type { RedirectImportReport } from '../../../entities/seo/model'
import { downloadTextFile } from '../../../shared/lib/download'
import { decodeForDisplay, buildImportErrorsCsv, describeApiError, redirectCsvTemplate } from './redirect-rules'

interface RedirectImportDialogProps {
  open: boolean
  onClose: () => void
}

const ACTION_LABELS = { create: 'Создать', update: 'Обновить', skip: 'Пропустить' } as const

const MAX_FILE_BYTES = 1024 * 1024

export function RedirectImportDialog({ open, onClose }: RedirectImportDialogProps) {
  const { push } = useToast()
  const applyMutation = useImportRedirectsMutation()
  const fileInput = useRef<HTMLInputElement>(null)
  const [csv, setCsv] = useState('')
  const [updateExisting, setUpdateExisting] = useState(false)
  const [report, setReport] = useState<RedirectImportReport | null>(null)
  const [checking, setChecking] = useState(false)
  const [applied, setApplied] = useState(false)

  const reset = () => {
    setCsv('')
    setReport(null)
    setApplied(false)
    setUpdateExisting(false)
  }

  const close = () => {
    reset()
    onClose()
  }

  const changeCsv = (value: string) => {
    setCsv(value)
    setReport(null)
    setApplied(false)
  }

  const readFile = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (file === undefined) {
      return
    }

    if (file.size > MAX_FILE_BYTES) {
      push({ title: 'Файл слишком большой', description: 'Максимальный размер CSV — 1 МБ.' })
      return
    }

    changeCsv(await file.text())
  }

  const check = async () => {
    setChecking(true)
    try {
      setReport(await importRedirects(csv, { dryRun: true, updateExisting }))
      setApplied(false)
    } catch (error) {
      setReport(null)
      push({ title: 'CSV не прошёл проверку', description: describeApiError(error, 'Не удалось разобрать файл.') })
    } finally {
      setChecking(false)
    }
  }

  const apply = async () => {
    try {
      const result = await applyMutation.mutateAsync({ csv, dryRun: false, updateExisting })
      setReport(result)
      setApplied(true)
      push({
        title: 'Импорт выполнен',
        description: `Создано: ${result.created}, обновлено: ${result.updated}, пропущено: ${result.skipped}, ошибок: ${result.failed}.`,
      })
    } catch (error) {
      push({ title: 'Не удалось выполнить импорт', description: describeApiError(error, 'Повторите попытку.') })
    }
  }

  const importable = report !== null && !applied && report.created + report.updated > 0

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) {
          close()
        }
      }}
      title="Импорт редиректов из CSV"
      description="Колонки: source (старый URL), target (новый URL), status (301/302/307/308, необязательно), active (1/0, необязательно). Разделитель — запятая, точка с запятой или табуляция."
      contentClassName="max-w-3xl max-h-[90vh] overflow-y-auto"
    >
      <div className="grid gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <input ref={fileInput} type="file" accept=".csv,.txt,text/csv,text/plain" className="hidden" aria-label="CSV-файл" onChange={(event) => void readFile(event)} />
          <Button type="button" variant="outline" size="sm" onClick={() => fileInput.current?.click()}>Выбрать файл</Button>
          <Button type="button" variant="ghost" size="sm" onClick={() => downloadTextFile('redirects-template.csv', redirectCsvTemplate())}>Скачать шаблон</Button>
        </div>
        <Textarea
          aria-label="Содержимое CSV"
          className="min-h-40 font-mono text-xs"
          placeholder={'source,target,status\n/old-page/,/new-page/,301'}
          value={csv}
          onChange={(event) => changeCsv(event.target.value)}
        />
        <Checkbox
          checked={updateExisting}
          onCheckedChange={(value) => {
            setUpdateExisting(value)
            setReport(null)
          }}
          label="Обновлять уже существующие правила (иначе они пропускаются)"
        />
        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="outline" disabled={csv.trim() === '' || checking} onClick={() => void check()}>
            {checking ? 'Проверка...' : 'Проверить'}
          </Button>
          <Button type="button" disabled={!importable || applyMutation.isPending} onClick={() => void apply()}>
            {applyMutation.isPending ? 'Импорт...' : 'Применить импорт'}
          </Button>
        </div>

        {report !== null ? <ImportReport report={report} applied={applied} /> : null}

        <div className="flex justify-end">
          <Button type="button" variant="ghost" onClick={close}>Закрыть</Button>
        </div>
      </div>
    </Dialog>
  )
}

function ImportReport({ report, applied }: { report: RedirectImportReport, applied: boolean }) {
  return (
    <section className="grid gap-3 rounded-lg border border-line p-3 dark:border-slate-700" aria-label="Отчёт импорта">
      <div className="flex flex-wrap items-center gap-2 text-sm">
        <strong>{applied ? 'Результат импорта' : 'Предпросмотр (ничего не записано)'}</strong>
        <Badge>Строк: {report.totalRows}</Badge>
        <Badge tone="success">Создать: {report.created}</Badge>
        <Badge tone="success">Обновить: {report.updated}</Badge>
        <Badge>Пропустить: {report.skipped}</Badge>
        <Badge tone={report.failed > 0 ? 'warning' : 'neutral'}>Ошибок: {report.failed}</Badge>
      </div>

      {report.errors.length > 0 ? (
        <div>
          <div className="mb-1 flex items-center justify-between">
            <p className="text-sm font-medium text-red-700 dark:text-red-400">Строки с ошибками не будут импортированы</p>
            <Button type="button" size="sm" variant="outline" onClick={() => downloadTextFile('redirects-import-errors.csv', buildImportErrorsCsv(report.errors))}>
              Скачать отчёт об ошибках
            </Button>
          </div>
          <ul className="max-h-48 space-y-1 overflow-y-auto text-xs" aria-label="Ошибки импорта">
            {report.errors.map((error, index) => (
              <li key={`${error.line}-${index}`} className="rounded-sm bg-red-50 px-2 py-1 dark:bg-red-950/30">
                <span className="font-mono">строка {error.line}</span>
                {error.source !== '' ? <span className="font-mono"> · {decodeForDisplay(error.source)}</span> : null}
                {' — '}{error.message}
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {report.warnings.length > 0 ? (
        <div>
          <p className="mb-1 text-sm font-medium text-amber-700 dark:text-amber-400">Предупреждения</p>
          <ul className="max-h-32 space-y-1 overflow-y-auto text-xs" aria-label="Предупреждения импорта">
            {report.warnings.map((warning, index) => (
              <li key={`${warning.line}-${index}`} className="rounded-sm bg-amber-50 px-2 py-1 dark:bg-amber-950/30">
                <span className="font-mono">строка {warning.line}</span> — {warning.message}
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {report.preview.length > 0 ? (
        <div className="max-h-48 overflow-y-auto">
          <table className="min-w-full text-xs">
            <thead>
              <tr className="text-left text-graphite dark:text-slate-500">
                <th className="py-1 pr-2">Стр.</th>
                <th className="py-1 pr-2">Старый URL</th>
                <th className="py-1 pr-2">Новый URL</th>
                <th className="py-1 pr-2">Код</th>
                <th className="py-1">Действие</th>
              </tr>
            </thead>
            <tbody>
              {report.preview.map((row) => (
                <tr key={`${row.line}-${row.source}`} className="border-t border-surface-strong dark:border-slate-800">
                  <td className="py-1 pr-2">{row.line}</td>
                  <td className="py-1 pr-2 font-mono">{decodeForDisplay(row.source)}</td>
                  <td className="py-1 pr-2 font-mono">{decodeForDisplay(row.target)}</td>
                  <td className="py-1 pr-2">{row.status}</td>
                  <td className="py-1">{ACTION_LABELS[row.action]}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </section>
  )
}
