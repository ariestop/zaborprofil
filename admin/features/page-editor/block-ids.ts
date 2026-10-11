import type { BuilderBlock } from '../../modules/page-builder/types'

/**
 * Новый блок получает id в браузере (`crypto.randomUUID()`), а сервер при первом сохранении создаёт его со своим ULID.
 * Если дальше отправлять браузерный id, сервер не узнаёт блок: удаляет созданный и создаёт его заново при каждом
 * сохранении (новый id, сброс даты создания, лишние записи в журнале аудита).
 *
 * Поэтому редактор запоминает соответствие «id в браузере → id на сервере» и подставляет серверный id при отправке.
 * В самом редакторе id блоков не меняются: от них зависят React-ключи, и смена id перемонтировала бы открытый
 * редактор текста прямо во время набора.
 */
export type ServerBlockIds = Map<string, string>

export function withServerIds(blocks: BuilderBlock[], serverIds: ServerBlockIds): BuilderBlock[] {
  return blocks.map((block) => {
    const serverId = serverIds.get(block.id)

    return serverId === undefined ? block : { ...block, id: serverId }
  })
}

/**
 * Сервер возвращает блоки в порядке отправки. Если число или типы блоков не совпали, ответ не сопоставляется:
 * лучше один раз пересоздать блок, чем привязать правку к чужому блоку.
 */
export function rememberServerIds(sent: BuilderBlock[], saved: BuilderBlock[], serverIds: ServerBlockIds): void {
  if (sent.length !== saved.length || sent.some((block, index) => block.type !== saved[index]?.type)) {
    return
  }

  sent.forEach((block, index) => {
    const serverId = saved[index]?.id
    if (serverId !== undefined && serverId !== '' && serverId !== block.id) {
      serverIds.set(block.id, serverId)
    }
  })
}
