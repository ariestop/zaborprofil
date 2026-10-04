import type { SystemWarningItem } from '../../types/api'

export type AttentionTone = 'leads' | 'critical' | 'warning'

export interface AttentionItem {
  id: string
  /** Число или «!» в цветном квадрате слева. */
  marker: string
  title: string
  description: string
  actionLabel: string
  href: string
  tone: AttentionTone
}

export interface AttentionInput {
  /** Число новых заявок; `undefined`, если сводка по заявкам недоступна. */
  newLeads?: number
  warnings?: SystemWarningItem[]
  /** `false` — каталог резервных копий пуст; `undefined` — данных нет (например, нет права system.view). */
  hasBackups?: boolean
  /** Сообщения в очереди `failed`; `undefined` — данных нет (нет права system.view). */
  failedMessages?: number
  /** Ответы 5xx за последний час; `undefined` — данных нет. */
  serverErrorsLastHour?: number
}

function pluralLeads(count: number): string {
  const mod10 = count % 10
  const mod100 = count % 100
  if (mod10 === 1 && mod100 !== 11) {
    return 'новая заявка'
  }
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
    return 'новые заявки'
  }

  return 'новых заявок'
}

/**
 * Список «Требует внимания» на сводке: сначала то, что приносит деньги (заявки),
 * затем то, что может уронить сайт (критичные предупреждения, отсутствие резервных копий).
 */
export function buildAttentionItems({ newLeads, warnings = [], hasBackups, failedMessages, serverErrorsLastHour }: AttentionInput): AttentionItem[] {
  const items: AttentionItem[] = []

  if (newLeads !== undefined && newLeads > 0) {
    items.push({
      id: 'leads-new',
      marker: String(newLeads),
      title: `${newLeads} ${pluralLeads(newLeads)} без ответа`,
      description: 'Разберите их: позвоните клиенту или назначьте ответственного.',
      actionLabel: 'Разобрать',
      href: '/admin/crm?status=new',
      tone: 'leads',
    })
  }

  if (hasBackups === false) {
    items.push({
      id: 'backups-missing',
      marker: '!',
      title: 'Резервных копий нет',
      description: 'При сбое восстановить сайт будет не из чего. Настройте регулярное копирование.',
      actionLabel: 'Открыть',
      href: '/admin/system/backups',
      tone: 'critical',
    })
  }

  if (failedMessages !== undefined && failedMessages > 0) {
    items.push({
      id: 'queue-failed',
      marker: String(failedMessages),
      title: 'Сообщения в очереди завершились ошибкой',
      description: 'Уведомления или фоновые задачи не выполнены. Проверьте очередь и повторите отправку.',
      actionLabel: 'К очередям',
      href: '/admin/system/queues',
      tone: 'critical',
    })
  }

  if (serverErrorsLastHour !== undefined && serverErrorsLastHour > 0) {
    items.push({
      id: 'server-errors',
      marker: String(serverErrorsLastHour),
      title: 'Ошибки сервера за последний час',
      description: 'Сайт или админка отвечали ошибкой 5xx. Загляните в логи.',
      actionLabel: 'К логам',
      href: '/admin/system/logs',
      tone: 'warning',
    })
  }

  const sortedWarnings = [...warnings].sort((a, b) => (a.severity === b.severity ? 0 : a.severity === 'critical' ? -1 : 1))
  for (const warning of sortedWarnings) {
    items.push({
      id: `warning-${warning.code}`,
      marker: '!',
      title: warning.severity === 'critical' ? 'Критичная проблема на сервере' : 'Предупреждение сервера',
      description: warning.message,
      actionLabel: 'Подробнее',
      href: '/admin/system',
      tone: warning.severity === 'critical' ? 'critical' : 'warning',
    })
  }

  return items
}
