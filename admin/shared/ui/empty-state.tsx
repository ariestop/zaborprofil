interface EmptyStateProps {
  title: string
  description: string
}

export function EmptyState({ title, description }: EmptyStateProps) {
  return (
    <div className="rounded-xl border border-dashed border-line-strong px-4 py-10 text-center dark:border-slate-700">
      <p className="text-base font-semibold">{title}</p>
      <p className="mt-2 text-sm text-graphite dark:text-slate-400">{description}</p>
    </div>
  )
}
