import { useState } from "react"

// --- Hooks ---
import { useTiptapEditor } from "@/hooks/use-tiptap-editor"

// --- UI Primitives ---
import { Button } from "@/components/tiptap-ui-primitive/button"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/tiptap-ui-primitive/popover"

import { EMOJI_GROUPS } from "./emoji-list"

/**
 * Кнопка «Эмодзи»: панель с подборкой по темам, выбранный символ вставляется в позицию курсора.
 * Эмодзи — обычный текст (utf8mb4), санитайзер HTML его не трогает.
 */
export function EmojiPopover() {
  const { editor } = useTiptapEditor()
  const [open, setOpen] = useState(false)
  const [groupKey, setGroupKey] = useState(EMOJI_GROUPS[0].key)
  const group = EMOJI_GROUPS.find((candidate) => candidate.key === groupKey) ?? EMOJI_GROUPS[0]

  if (!editor || !editor.isEditable) {
    return null
  }

  const insert = (emoji: string): void => {
    editor.chain().focus().insertContent(emoji).run()
    setOpen(false)
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          role="button"
          tabIndex={-1}
          aria-label="Вставить эмодзи"
          tooltip="Эмодзи"
          data-active-state={open ? "on" : "off"}
        >
          <span className="tiptap-button-icon text-[17px] leading-none" aria-hidden="true">
            🙂
          </span>
        </Button>
      </PopoverTrigger>
      <PopoverContent
        align="start"
        aria-label="Эмодзи"
        className="w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-line bg-white p-2 shadow-[0_16px_32px_rgba(16,24,40,0.14)]"
      >
        <div role="tablist" aria-label="Темы эмодзи" className="mb-2 flex gap-1 overflow-x-auto">
          {EMOJI_GROUPS.map((candidate) => (
            <button
              key={candidate.key}
              type="button"
              role="tab"
              aria-selected={candidate.key === group.key}
              onClick={() => setGroupKey(candidate.key)}
              className={
                "min-h-9 shrink-0 rounded-lg px-2.5 text-sm font-semibold transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500 " +
                (candidate.key === group.key
                  ? "bg-brand-50 text-brand-800"
                  : "text-graphite hover:bg-surface-strong hover:text-ink")
              }
            >
              {candidate.label}
            </button>
          ))}
        </div>
        <div role="tabpanel" aria-label={group.label} className="grid grid-cols-8 gap-0.5">
          {group.emojis.map((emoji, index) => (
            <button
              key={`${emoji}-${index}`}
              type="button"
              aria-label={`Вставить ${emoji}`}
              onClick={() => insert(emoji)}
              className="flex aspect-square items-center justify-center rounded-lg text-[22px] leading-none transition hover:bg-surface-strong focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500"
            >
              {emoji}
            </button>
          ))}
        </div>
      </PopoverContent>
    </Popover>
  )
}
