import { useSearchParams } from 'react-router-dom'
import { PageHeader, Tabs } from '../shared/ui'
import { useDocumentTitle } from '../shared/hooks/use-document-title'
import { AuditTab } from '../features/seo/audit/AuditTab'
import { NotFoundTab } from '../features/seo/not-found/NotFoundTab'
import { RedirectsTab } from '../features/seo/redirects/RedirectsTab'
import { RobotsTab } from '../features/seo/robots/RobotsTab'

const TABS = ['redirects', 'robots', 'not-found', 'audit'] as const
type SeoTab = (typeof TABS)[number]

function resolveTab(value: string | null): SeoTab {
  return TABS.find((tab) => tab === value) ?? 'redirects'
}

export default function SeoPage() {
  useDocumentTitle('SEO — админ-панель')
  const [searchParams, setSearchParams] = useSearchParams()
  const tab = resolveTab(searchParams.get('tab'))

  return (
    <div>
      <PageHeader
        title="SEO-панель"
        description="Редиректы со старых адресов, robots.txt, журнал 404 и аудит страниц."
      />
      <Tabs
        value={tab}
        onValueChange={(next) => setSearchParams(next === 'redirects' ? {} : { tab: next }, { replace: true })}
        items={[
          { value: 'redirects', label: 'Редиректы', content: <RedirectsTab /> },
          { value: 'robots', label: 'robots.txt', content: <RobotsTab /> },
          { value: 'not-found', label: 'Журнал 404', content: <NotFoundTab /> },
          { value: 'audit', label: 'SEO-аудит', content: <AuditTab /> },
        ]}
      />
    </div>
  )
}
