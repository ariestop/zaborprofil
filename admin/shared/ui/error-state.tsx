interface ErrorStateProps {
  title: string
  description: string
}

export function ErrorState({ title, description }: ErrorStateProps) {
  return (
    <div className="rounded-xl border border-red-300 bg-red-50 px-4 py-4 dark:border-red-900 dark:bg-red-950/30">
      <p className="text-sm font-semibold text-red-800 dark:text-red-300">{title}</p>
      <p className="mt-1 text-sm text-red-700 dark:text-red-400">{description}</p>
    </div>
  )
}
