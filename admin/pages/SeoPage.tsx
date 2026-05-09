import { PageHeader, EmptyState } from '../shared/ui'

export default function SeoPage() {
  return (
    <div>
      <PageHeader title="SEO Panel" description="Foundation для SEO audit, redirects, robots и контентной оптимизации." />
      <EmptyState
        title="SEO foundation"
        description="Следующий этап: виджеты проверок, canonical/noindex, redirect management и метрики индексации."
      />
    </div>
  )
}
