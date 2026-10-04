import type { PageTemplateItem } from '../../types/api'

interface TemplatePreviewProps {
  template: PageTemplateItem
}

export function TemplatePreview({ template }: TemplatePreviewProps) {
  const blocks = [...template.blocksSchema].sort((left, right) => left.position - right.position)

  return (
    <section
      aria-label={`Состав шаблона «${template.name}»`}
      className="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-800 dark:bg-slate-900"
    >
      <h3 className="font-semibold">{template.name}</h3>
      {template.description !== null && template.description !== '' ? (
        <p className="mt-1 text-slate-600 dark:text-slate-300">{template.description}</p>
      ) : null}
      <p className="mt-3 text-xs font-medium uppercase tracking-wide text-slate-500">
        Блоки страницы ({blocks.length}) и что в них заполнить
      </p>
      <ol className="mt-2 space-y-2">
        {blocks.map((block, index) => (
          <li key={`${block.type}-${index}`} className="flex gap-3">
            <span className="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-emerald-100 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
              {index + 1}
            </span>
            <span>
              <span className="font-medium">{block.name}</span>
              {block.isEnabled ? null : <span className="ml-2 text-xs text-slate-500">(выключен)</span>}
              {block.hint !== undefined ? <span className="block text-slate-600 dark:text-slate-300">{block.hint}</span> : null}
            </span>
          </li>
        ))}
      </ol>
    </section>
  )
}
