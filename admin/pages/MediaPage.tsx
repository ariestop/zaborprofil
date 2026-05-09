import { PageHeader, EmptyState } from '../shared/ui'

export default function MediaPage() {
  return (
    <div>
      <PageHeader title="Media Library" description="Foundation для Media module и будущего drag-and-drop upload workflow." />
      <EmptyState
        title="Media module foundation"
        description="Следующий этап: интеграция каталога ассетов, upload pipeline и трансформаций."
      />
    </div>
  )
}
