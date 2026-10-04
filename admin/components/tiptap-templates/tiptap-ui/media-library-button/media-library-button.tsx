import { forwardRef, useCallback, useState } from "react"

// --- Hooks ---
import { useTiptapEditor } from "@/hooks/use-tiptap-editor"

// --- Icons ---
import { ImagePlusIcon } from "@/components/tiptap-icons/image-plus-icon"

// --- UI Primitives ---
import type { ButtonProps } from "@/components/tiptap-ui-primitive/button"
import { Button } from "@/components/tiptap-ui-primitive/button"

import { MediaPickerDialog } from "../../../../features/media/MediaPickerDialog"
import type { MediaAssetItem } from "../../../../types/api"

export interface MediaLibraryButtonProps extends Omit<ButtonProps, "type"> {
  text?: string
}

export const MediaLibraryButton = forwardRef<
  HTMLButtonElement,
  MediaLibraryButtonProps
>(({ text, onClick, ...buttonProps }, ref) => {
  const { editor } = useTiptapEditor()
  const [open, setOpen] = useState(false)
  const label = "Вставить изображение из медиатеки"

  const handleClick = useCallback(
    (event: React.MouseEvent<HTMLButtonElement>) => {
      onClick?.(event)
      if (event.defaultPrevented) return
      setOpen(true)
    },
    [onClick]
  )

  const insert = useCallback(
    (asset: MediaAssetItem) => {
      editor
        ?.chain()
        .focus()
        .setImage({
          src: asset.publicPath,
          alt: asset.alt ?? "",
          title: asset.title ?? undefined,
        })
        .run()
    },
    [editor]
  )

  return (
    <>
      <Button
        type="button"
        variant="ghost"
        role="button"
        tabIndex={-1}
        disabled={!editor}
        aria-label={label}
        tooltip={label}
        onClick={handleClick}
        {...buttonProps}
        ref={ref}
      >
        <ImagePlusIcon className="tiptap-button-icon" />
        {text && <span className="tiptap-button-text">{text}</span>}
      </Button>
      <MediaPickerDialog open={open} onOpenChange={setOpen} onSelect={insert} />
    </>
  )
})

MediaLibraryButton.displayName = "MediaLibraryButton"
