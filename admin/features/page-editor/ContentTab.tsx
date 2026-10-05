import { lazy, Suspense } from 'react'
import { PageLoadingState } from '../../shared/ui'
import type { ContentWorkspaceProps } from './workspace/ContentWorkspace'

// Рабочее место блоков грузится отдельным чанком: маршрут редактора остаётся лёгким.
const ContentWorkspace = lazy(() => import('./workspace/ContentWorkspace'))

export type ContentTabProps = ContentWorkspaceProps

export function ContentTab(props: ContentTabProps) {
  return (
    <Suspense fallback={<PageLoadingState />}>
      <ContentWorkspace {...props} />
    </Suspense>
  )
}
