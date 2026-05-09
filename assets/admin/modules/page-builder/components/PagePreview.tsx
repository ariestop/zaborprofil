interface PagePreviewProps {
  previewHtml: string | null
}

export function PagePreview({ previewHtml }: PagePreviewProps) {
  return (
    <section className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
      <h3 className="text-sm font-semibold">Page preview</h3>
      {previewHtml === null ? (
        <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
          Нажмите «Preview», чтобы получить HTML предпросмотра от backend.
        </p>
      ) : (
        <iframe
          title="Page preview"
          className="mt-3 h-80 w-full rounded-md border border-slate-200 dark:border-slate-700"
          srcDoc={previewHtml}
        />
      )}
    </section>
  )
}
