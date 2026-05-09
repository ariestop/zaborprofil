import { useSystemDeployQuery } from '../entities/system/api'
import { Card, ErrorState, PageHeader, PageLoadingState } from '../shared/ui'

export default function SystemDeployPage() {
  const deployQuery = useSystemDeployQuery()

  if (deployQuery.isPending) {
    return <PageLoadingState />
  }

  if (deployQuery.isError || deployQuery.data === undefined) {
    return (
      <ErrorState
        title="Не удалось загрузить deploy-информацию"
        description="Проверьте endpoint /admin/api/system/deploy и права system.view."
      />
    )
  }

  return (
    <div>
      <PageHeader title="Деплой" description="Текущая release-информация из release manifest." />
      <Card title="Release info">
        <div className="space-y-1 text-sm">
          <p>release: {deployQuery.data.release ?? '-'}</p>
          <p>commit: {deployQuery.data.commit ?? '-'}</p>
          <p>builtAt: {deployQuery.data.builtAt ?? '-'}</p>
          <p>deployedAt: {deployQuery.data.deployedAt ?? '-'}</p>
        </div>
      </Card>
    </div>
  )
}
