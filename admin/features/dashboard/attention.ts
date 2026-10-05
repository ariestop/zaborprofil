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
  /** Действие выполняется на месте, а не переходом по ссылке (пересборка фронтенда). */
  action?: 'rebuild'
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
  /** Последняя сборка фронтенда завершилась ошибкой; `undefined` — статус неизвестен. */
  buildFailed?: boolean
  /** Как давно ждёт самая ранняя новая заявка, в миллисекундах. */
  oldestWaitMs?: number | null
}

/** «45 мин», «3 ч 12 мин», «2 д 4 ч»: сколько заявка ждёт ответа. */
export function formatWaiting(ms: number): string {
  const minutes = Math.max(0, Math.floor(ms / 60_000))
  if (minutes < 60) {
    return `${Math.max(minutes, 1)} мин`
  }
  const hours = Math.floor(minutes / 60)
  if (hours < 24) {
    const rest = minutes % 60

    return rest === 0 ? `${hours} ч` : `${hours} ч ${rest} мин`
  }
  const days = Math.floor(hours / 24)
  const restHours = hours % 24

  return restHours === 0 ? `${days} д` : `${days} д ${restHours} ч`
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

function warningTitle(warning: SystemWarningItem): string {
  const critical = warning.severity === 'critical'
  if (warning.code.startsWith('health_disk_')) {
    return critical ? 'Не хватает места на диске' : 'Заканчивается место на диске'
  }

  return critical ? 'Критичная проблема на сервере' : 'Предупреждение сервера'
}

/**
 * Список «Требует внимания» на сводке: сначала то, что приносит деньги (заявки),
 * затем то, что может уронить сайт (критичные предупреждения, отсутствие резервных копий).
 */
export function buildAttentionItems({ newLeads, warnings = [], hasBackups, failedMessages, serverErrorsLastHour, buildFailed, oldestWaitMs }: AttentionInput): AttentionItem[] {
  const items: AttentionItem[] = []

  if (newLeads !== undefined && newLeads > 0) {
    items.push({
      id: 'leads-new',
      marker: String(newLeads),
      title: `${newLeads} ${pluralLeads(newLeads)} без ответа`,
      description: oldestWaitMs === undefined || oldestWaitMs === null
        ? 'Позвоните клиенту или возьмите заявку в работу.'
        : `Самая ранняя ждёт ${formatWaiting(oldestWaitMs)}`,
      actionLabel: 'Разобрать',
      href: '/admin/crm?status=new',
      tone: 'leads',
    })
  }

  if (buildFailed === true) {
    items.push({
      id: 'build-failed',
      marker: '!',
      title: 'Сборка админки завершилась с ошибкой',
      description: 'Последняя пересборка не прошла: свежие правки интерфейса могут не отображаться',
      actionLabel: 'Пересобрать',
      href: '/admin/system/deploy',
      tone: 'critical',
      action: 'rebuild',
    })
  }

  if (hasBackups === false) {
    items.push({
      id: 'backups-missing',
      marker: '!',
      title: 'Резервных копий нет',
      description: 'Каталог бэкапов пуст: при сбое восстановить сайт будет не из чего',
      actionLabel: 'Настроить',
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
      title: warningTitle(warning),
      description: warning.message,
      actionLabel: 'Подробнее',
      href: '/admin/system',
      tone: warning.severity === 'critical' ? 'critical' : 'warning',
    })
  }

  return items
}
